<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRoomRequest;
use App\Http\Requests\UpdateRoomRequest;
use App\Http\Resources\RoomResource;
use App\Models\Room;
use Illuminate\Http\Request;

class RoomController extends Controller
{
    public function index(Request $request)
    {
        $rooms = Room::query()
            ->when($request->query('hotel_id'), fn ($q, $hotelId) => $q->where('hotel_id', $hotelId))
            ->orderBy('id')
            ->paginate(15);

        return RoomResource::collection($rooms);
    }

    public function store(StoreRoomRequest $request)
    {
        $room = Room::create($request->validated());

        return (new RoomResource($room->load('hotel')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Room $room)
    {
        return new RoomResource($room->load('hotel'));
    }

    public function update(UpdateRoomRequest $request, Room $room)
    {
        $room->update($request->validated());

        return new RoomResource($room->load('hotel'));
    }

    public function destroy(Room $room)
    {
        if ($room->reserves()->exists()) {
            return response()->json([
                'message' => 'Não é possível excluir um quarto que possui reservas.',
            ], 409);
        }

        $room->delete();

        return response()->noContent();
    }
}
