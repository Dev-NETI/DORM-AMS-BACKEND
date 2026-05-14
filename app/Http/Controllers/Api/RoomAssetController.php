<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Item;
use App\Models\RoomAsset;
use App\Models\RoomAssetDocument;
use App\Models\RoomAssetTransfer;
use App\Models\RoomLocation;
use App\Models\Supplier;
use App\Traits\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class RoomAssetController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $user  = $request->user();
        $query = RoomAsset::with(['item.category', 'location', 'supplier', 'department', 'documents', 'acquiredBy']);

        if (! $user->isSystemAdmin()) {
            $query->where('department_id', $user->department_id);
        } elseif ($request->filled('department_id')) {
            $query->where('department_id', $request->department_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('location_id')) {
            $query->where('location_id', $request->location_id);
        }

        if ($request->filled('item_id')) {
            $query->where('item_id', $request->item_id);
        }

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('asset_code', 'like', "%{$request->search}%")
                    ->orWhere('serial_number', 'like', "%{$request->search}%")
                    ->orWhere('purchase_document_no', 'like', "%{$request->search}%")
                    ->orWhereHas('item', fn ($iq) => $iq->where('name', 'like', "%{$request->search}%"))
                    ->orWhereHas('location', fn ($lq) => $lq->where('name', 'like', "%{$request->search}%"));
            });
        }

        return $this->success($query->orderBy('asset_code')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'item_id'              => 'required|exists:items,id',
            'asset_code'           => 'required|string|max:100|unique:room_assets,asset_code',
            'location_id'          => 'nullable|exists:room_locations,id',
            'supplier_id'          => 'nullable|exists:suppliers,id',
            'serial_number'        => 'nullable|string|max:255',
            'purchase_date'        => 'nullable|date',
            'purchase_price'       => 'nullable|numeric|min:0',
            'purchase_document_no' => 'nullable|string|max:255',
            'status'               => 'nullable|in:available,deployed,for_disposal',
            'condition'            => 'nullable|in:new,good,fair,poor,damaged',
            'notes'                => 'nullable|string',
            'department_id'        => $user->isSystemAdmin() ? 'nullable|exists:departments,id' : 'nullable',
        ]);

        if (! $user->isSystemAdmin()) {
            $validated['department_id'] = $user->department_id;
        }

        $validated['acquired_by'] = $user->id;

        // If location is provided and status not set, auto-deploy
        if (! empty($validated['location_id']) && empty($validated['status'])) {
            $validated['status'] = 'deployed';
        }

        $asset = RoomAsset::create($validated);

        // If placed in a location, record the initial transfer
        if ($asset->location_id) {
            RoomAssetTransfer::create([
                'room_asset_id'    => $asset->id,
                'from_location_id' => null,
                'to_location_id'   => $asset->location_id,
                'transferred_by'   => $user->id,
                'transferred_at'   => now(),
                'reason'           => 'Initial placement',
                'notes'            => null,
            ]);
        }

        $asset->load(['item.category', 'location', 'supplier', 'department', 'documents']);

        return $this->created($asset);
    }

    public function show(RoomAsset $roomAsset): JsonResponse
    {
        $roomAsset->load([
            'item.category',
            'item.unit',
            'location.department',
            'supplier',
            'department',
            'acquiredBy',
            'documents.uploadedBy',
            'transfers' => fn ($q) => $q->with(['fromLocation', 'toLocation', 'transferredBy'])->latest('transferred_at'),
        ]);

        return $this->success($roomAsset);
    }

    public function update(Request $request, RoomAsset $roomAsset): JsonResponse
    {
        $validated = $request->validate([
            'item_id'              => 'sometimes|exists:items,id',
            'supplier_id'          => 'nullable|exists:suppliers,id',
            'serial_number'        => 'nullable|string|max:255',
            'purchase_date'        => 'nullable|date',
            'purchase_price'       => 'nullable|numeric|min:0',
            'purchase_document_no' => 'nullable|string|max:255',
            'status'               => 'nullable|in:available,deployed,for_disposal',
            'condition'            => 'nullable|in:new,good,fair,poor,damaged',
            'notes'                => 'nullable|string',
        ]);

        $roomAsset->update($validated);
        $roomAsset->load(['item.category', 'location', 'supplier', 'department']);

        return $this->success($roomAsset, 'Asset updated successfully');
    }

    /**
     * Transfer asset to a new location.
     * POST /api/room-assets/{roomAsset}/transfer
     */
    public function transfer(Request $request, RoomAsset $roomAsset): JsonResponse
    {
        $validated = $request->validate([
            'to_location_id' => 'nullable|exists:room_locations,id',
            'reason'         => 'nullable|string|max:500',
            'notes'          => 'nullable|string',
        ]);

        $fromLocationId = $roomAsset->location_id;
        $toLocationId   = $validated['to_location_id'] ?? null;

        if ($fromLocationId === $toLocationId) {
            return $this->error('Asset is already at this location.', 422);
        }

        DB::transaction(function () use ($roomAsset, $validated, $fromLocationId, $toLocationId, $request) {
            RoomAssetTransfer::create([
                'room_asset_id'    => $roomAsset->id,
                'from_location_id' => $fromLocationId,
                'to_location_id'   => $toLocationId,
                'transferred_by'   => $request->user()->id,
                'transferred_at'   => now(),
                'reason'           => $validated['reason'] ?? null,
                'notes'            => $validated['notes'] ?? null,
            ]);

            $newStatus = $toLocationId ? 'deployed' : 'available';

            $roomAsset->update([
                'location_id' => $toLocationId,
                'status'      => $newStatus,
            ]);
        });

        $roomAsset->load(['item.category', 'location', 'supplier', 'department']);

        return $this->success($roomAsset, 'Asset transferred successfully');
    }

    /**
     * Upload purchase documents (accepts multiple PDF files).
     * POST /api/room-assets/{roomAsset}/documents
     */
    public function uploadDocument(Request $request, RoomAsset $roomAsset): JsonResponse
    {
        $request->validate([
            'files'   => 'required|array|min:1',
            'files.*' => 'required|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:10240',
            'document_type' => 'nullable|in:purchase_document,receipt,warranty,other',
        ]);

        $uploaded     = [];
        $documentType = $request->input('document_type', 'purchase_document');
        $userId       = $request->user()->id;

        foreach ($request->file('files') as $file) {
            $path = $file->store('room_asset_documents', 'public');
            $doc  = $roomAsset->documents()->create([
                'file_path'     => $path,
                'original_name' => $file->getClientOriginalName(),
                'document_type' => $documentType,
                'uploaded_by'   => $userId,
            ]);
            $uploaded[] = $doc;
        }

        return $this->success($uploaded, count($uploaded).' document(s) uploaded successfully.');
    }

    /**
     * Delete a single document.
     * DELETE /api/room-assets/{roomAsset}/documents/{document}
     */
    public function deleteDocument(RoomAsset $roomAsset, RoomAssetDocument $document): JsonResponse
    {
        if ($document->room_asset_id !== $roomAsset->id) {
            return $this->error('Document not found for this asset.', 404);
        }

        Storage::disk('public')->delete($document->file_path);
        $document->delete();

        return $this->success(null, 'Document deleted successfully.');
    }

    public function destroy(RoomAsset $roomAsset): JsonResponse
    {
        // Delete all associated document files
        foreach ($roomAsset->documents as $doc) {
            Storage::disk('public')->delete($doc->file_path);
        }

        $roomAsset->delete();

        return $this->success(null, 'Asset deleted successfully');
    }

    /**
     * Return summary statistics for the dashboard.
     * GET /api/room-assets/stats
     */
    public function stats(Request $request): JsonResponse
    {
        $user  = $request->user();
        $query = RoomAsset::query();

        if (! $user->isSystemAdmin()) {
            $query->where('department_id', $user->department_id);
        } elseif ($request->filled('department_id')) {
            $query->where('department_id', $request->department_id);
        }

        $total      = (clone $query)->count();
        $available  = (clone $query)->where('status', 'available')->count();
        $deployed   = (clone $query)->where('status', 'deployed')->count();
        $forDisposal = (clone $query)->where('status', 'for_disposal')->count();

        return $this->success(compact('total', 'available', 'deployed', 'forDisposal'));
    }
}
