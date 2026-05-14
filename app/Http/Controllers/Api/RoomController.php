<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Models\RoomLocation;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoomController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $query = Room::with(['location']);

        if ($request->filled('room_location_id')) {
            $query->where('room_location_id', $request->room_location_id);
        }

        if ($request->filled('search')) {
            $query->where('room_number', 'like', "%{$request->search}%");
        }

        return $this->success($query->orderBy('room_location_id')->orderBy('room_number')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'room_number'      => 'required|string|max:50',
            'room_location_id' => 'required|exists:room_locations,id',
            'notes'            => 'nullable|string',
        ]);

        // Ensure unique per location
        $exists = Room::where('room_number', $validated['room_number'])
            ->where('room_location_id', $validated['room_location_id'])
            ->exists();

        if ($exists) {
            return $this->error('Room number already exists in this location.', 422);
        }

        $room = Room::create($validated);
        $room->load(['location']);

        return $this->created($room);
    }

    public function show(Room $room): JsonResponse
    {
        $room->load(['location', 'furniture.item.unit']);

        return $this->success($room);
    }

    public function update(Request $request, Room $room): JsonResponse
    {
        $validated = $request->validate([
            'room_number'      => 'sometimes|string|max:50',
            'room_location_id' => 'sometimes|exists:room_locations,id',
            'notes'            => 'nullable|string',
        ]);

        $room->update($validated);
        $room->load(['location']);

        return $this->success($room, 'Room updated successfully');
    }

    public function destroy(Room $room): JsonResponse
    {
        if ($room->furniture()->exists()) {
            return $this->error('Cannot delete a room that still has furniture assigned to it.', 422);
        }

        $room->delete();

        return $this->success(null, 'Room deleted successfully');
    }
}
