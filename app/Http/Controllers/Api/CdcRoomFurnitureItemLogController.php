<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CdcRoomFurnitureItemLog;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CdcRoomFurnitureItemLogController extends Controller
{
    use ApiResponse;

    /** GET /api/cdc-room-furniture-item-logs */
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) ($request->per_page ?? 50), 200);

        $query = CdcRoomFurnitureItemLog::with(['performedBy', 'purchase.documents'])
            ->orderByDesc('created_at');

        if ($request->filled('action_type')) {
            $query->where('action_type', $request->action_type);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        if ($request->filled('search')) {
            $query->where('item_name', 'like', '%' . $request->search . '%');
        }

        $paginated = $query->paginate($perPage);

        return $this->success([
            'data'         => $paginated->items(),
            'current_page' => $paginated->currentPage(),
            'last_page'    => $paginated->lastPage(),
            'total'        => $paginated->total(),
            'per_page'     => $paginated->perPage(),
        ]);
    }
}
