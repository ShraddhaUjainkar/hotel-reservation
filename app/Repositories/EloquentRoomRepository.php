<?php

namespace App\Repositories;

use App\Models\Room;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EloquentRoomRepository implements RoomRepositoryInterface
{
    public function all(): Collection
    {
        $this->ensureRoomsExist();

        return Room::query()
            ->orderBy('floor')
            ->orderBy('position')
            ->get();
    }

    public function available(): Collection
    {
        $this->ensureRoomsExist();

        return Room::query()
            ->where('status', Room::STATUS_AVAILABLE)
            ->orderBy('floor')
            ->orderBy('position')
            ->get();
    }

    public function markBooked(array $roomIds): void
    {
        Room::query()
            ->whereIn('id', $roomIds)
            ->update(['status' => Room::STATUS_BOOKED]);
    }

    public function randomizeOccupancy(): void
    {
        $this->ensureRoomsExist();

        DB::transaction(function (): void {
            Room::query()->update(['status' => Room::STATUS_AVAILABLE]);

            $occupiedRoomIds = Room::query()
                ->inRandomOrder()
                ->limit(random_int(28, 55))
                ->pluck('id')
                ->all();

            Room::query()
                ->whereIn('id', $occupiedRoomIds)
                ->update(['status' => Room::STATUS_OCCUPIED]);
        });
    }

    public function reset(): void
    {
        $this->ensureRoomsExist();

        Room::query()->update(['status' => Room::STATUS_AVAILABLE]);
    }

    private function ensureRoomsExist(): void
    {
        if (Room::query()->exists()) {
            return;
        }

        DB::transaction(function (): void {
            for ($floor = 1; $floor <= 10; $floor++) {
                $roomsOnFloor = $floor === 10 ? 7 : 10;

                for ($position = 1; $position <= $roomsOnFloor; $position++) {
                    Room::query()->create([
                        'floor' => $floor,
                        'position' => $position,
                        'number' => ($floor * 100) + $position,
                        'status' => Room::STATUS_AVAILABLE,
                    ]);
                }
            }
        });
    }
}
