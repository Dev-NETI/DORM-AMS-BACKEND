<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RoomLocation;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoomLocationController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $user  = $request->user();
        $query = RoomLocation::with(['department']);

        if (! $user->isSystemAdmin()) {
            $query->where(function ($q) use ($user) {
                $q->where('department_id', $user->department_id)
                    ->orWhereNull('department_id');
            });
        } elseif ($request->filled('department_id')) {
            $query->where('department_id', $request->department_id);
        }

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                    ->orWhere('location_type', 'like', "%{$request->search}%")
                    ->orWhere('floor', 'like', "%{$request->search}%");
            });
        }

        if ($request->filled('location_type')) {
            $query->where('location_type', $request->location_type);
        }

        if ($request->filled('scope') && $request->scope === 'ndb') {
            $query->where(function ($q) {
                $q->whereNull('location_type')
                    ->orWhereNotIn('location_type', ['fdc', 'cdc']);
            });
        }

        return $this->success($query->orderBy('name')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name'          => 'required|string|max:255',
            'location_type' => 'nullable|string|max:255',
            'floor'         => 'nullable|string|max:100',
            'capacity'      => 'nullable|integer|min:0',
            'description'   => 'nullable|string',
            'department_id' => $user->isSystemAdmin() ? 'nullable|exists:departments,id' : 'nullable',
        ]);

        if (! $user->isSystemAdmin()) {
            $validated['department_id'] = $user->department_id;
        }

        $location = RoomLocation::create($validated);
        $location->load(['department']);

        return $this->created($location);
    }

    public function show(RoomLocation $roomLocation): JsonResponse
    {
        $roomLocation->load(['department', 'rooms']);

        return $this->success($roomLocation);
    }

    public function update(Request $request, RoomLocation $roomLocation): JsonResponse
    {
        $validated = $request->validate([
            'name'          => 'sometimes|string|max:255',
            'location_type' => 'nullable|string|max:255',
            'floor'         => 'nullable|string|max:100',
            'capacity'      => 'nullable|integer|min:0',
            'description'   => 'nullable|string',
            'department_id' => 'nullable|exists:departments,id',
        ]);

        $roomLocation->update($validated);
        $roomLocation->load(['department']);

        return $this->success($roomLocation, 'Room location updated successfully');
    }

    public function destroy(RoomLocation $roomLocation): JsonResponse
    {
        if ($roomLocation->rooms()->exists()) {
            return $this->error('Cannot delete a location that still has rooms assigned to it.', 422);
        }

        $roomLocation->delete();

        return $this->success(null, 'Room location deleted successfully');
    }
}
