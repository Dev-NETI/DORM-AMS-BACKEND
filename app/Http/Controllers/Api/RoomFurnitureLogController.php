<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RoomFurnitureLog;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoomFurnitureLogController extends Controller
{
    use ApiResponse;

    /**
     * GET /api/room-furniture-logs
     *
     * Filters: room_id, item_id, action_type, location_id, date_from, date_to
     * Pagination: page, per_page (default 50)
     */
    public function index(Request $request): JsonResponse
    {
        $query = RoomFurnitureLog::with([
            'room.location',
            'item',
            'purchase',
            'performedBy',
        ])->orderByDesc('created_at');

        if ($request->filled('room_id')) {
            $query->where('room_id', $request->room_id);
        }

        if ($request->filled('item_id')) {
            $query->where('item_id', $request->item_id);
        }

        if ($request->filled('action_type')) {
            $query->where('action_type', $request->action_type);
        }

        // Filter by location area (join through rooms)
        if ($request->filled('location_id')) {
            $query->whereHas('room', fn ($q) =>
                $q->where('room_location_id', $request->location_id)
            );
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $perPage = min((int) ($request->per_page ?? 50), 200);
        $result  = $query->paginate($perPage);

        return $this->success($result);
    }
}
