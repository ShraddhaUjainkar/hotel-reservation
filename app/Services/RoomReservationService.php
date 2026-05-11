<?php

namespace App\Services;

use App\Models\Room;
use App\Repositories\RoomRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RoomReservationService
{
    public function __construct(private readonly RoomRepositoryInterface $rooms) {}

    public function getState(): array
    {
        return $this->formatState($this->rooms->all());
    }

    public function book(int $roomCount): array
    {
        if ($roomCount < 1 || $roomCount > 5) {
            throw ValidationException::withMessages([
                'rooms' => 'A guest can book between 1 and 5 rooms at a time.',
            ]);
        }

        return DB::transaction(function () use ($roomCount): array {
            $availableRooms = $this->rooms->available();

            if ($availableRooms->count() < $roomCount) {
                throw ValidationException::withMessages([
                    'rooms' => 'Not enough rooms are available for this booking.',
                ]);
            }

            $selection = $this->selectRooms($availableRooms, $roomCount);
            $this->rooms->markBooked($selection['rooms']->pluck('id')->all());

            return $this->formatState($this->rooms->all(), $selection);
        });
    }

    public function randomize(): array
    {
        $this->rooms->randomizeOccupancy();

        return $this->formatState($this->rooms->all(), [
            'rooms' => collect(),
            'travel_time' => 0,
            'strategy' => 'Random occupancy generated',
        ]);
    }

    public function reset(): array
    {
        $this->rooms->reset();

        return $this->formatState($this->rooms->all(), [
            'rooms' => collect(),
            'travel_time' => 0,
            'strategy' => 'All rooms reset',
        ]);
    }

    private function selectRooms(Collection $availableRooms, int $roomCount): array
    {
        $sameFloorCandidate = $this->bestSameFloorCandidate($availableRooms, $roomCount);

        if ($sameFloorCandidate !== null) {
            return $sameFloorCandidate;
        }

        return $this->bestCrossFloorCandidate($availableRooms, $roomCount);
    }

    private function bestSameFloorCandidate(Collection $availableRooms, int $roomCount): ?array
    {
        $best = null;

        foreach ($availableRooms->groupBy('floor') as $floorRooms) {
            if ($floorRooms->count() < $roomCount) {
                continue;
            }

            $sortedRooms = $floorRooms->sortBy('position')->values();

            for ($start = 0; $start <= $sortedRooms->count() - $roomCount; $start++) {
                $rooms = $sortedRooms->slice($start, $roomCount)->values();
                $travelTime = $rooms->last()->position - $rooms->first()->position;

                $candidate = [
                    'rooms' => $rooms,
                    'travel_time' => $travelTime,
                    'strategy' => 'Same floor priority',
                    'sort' => [$travelTime, $rooms->first()->floor, $rooms->first()->position],
                ];

                if ($best === null || $candidate['sort'] < $best['sort']) {
                    $best = $candidate;
                }
            }
        }

        return $best ? $this->withoutSortKey($best) : null;
    }

    private function bestCrossFloorCandidate(Collection $availableRooms, int $roomCount): array
    {
        $roomsByFloor = $availableRooms
            ->groupBy('floor')
            ->map(fn (Collection $rooms): Collection => $rooms->sortBy('position')->values());
        $floors = $roomsByFloor->keys()->map(fn ($floor): int => (int) $floor)->values()->all();
        $best = null;

        foreach ($this->floorSubsets($floors) as $floorSubset) {
            $capacity = collect($floorSubset)->sum(fn (int $floor): int => $roomsByFloor[$floor]->count());

            if ($capacity < $roomCount) {
                continue;
            }

            foreach ($this->allocations($floorSubset, $roomsByFloor, $roomCount) as $allocation) {
                $rooms = collect();

                foreach ($allocation as $floor => $take) {
                    $rooms = $rooms->merge($roomsByFloor[$floor]->take($take));
                }

                $rooms = $rooms->sortBy([['floor', 'asc'], ['position', 'asc']])->values();
                $travelTime = $this->bookingTravelTime($rooms);
                $candidate = [
                    'rooms' => $rooms,
                    'travel_time' => $travelTime,
                    'strategy' => 'Minimum travel time across floors',
                    'sort' => [
                        $travelTime,
                        $rooms->max('floor') - $rooms->min('floor'),
                        $rooms->min('floor'),
                        $rooms->sum('position'),
                    ],
                ];

                if ($best === null || $candidate['sort'] < $best['sort']) {
                    $best = $candidate;
                }
            }
        }

        return $this->withoutSortKey($best);
    }

    private function bookingTravelTime(Collection $rooms): int
    {
        $maxTime = 0;

        foreach ($rooms as $fromRoom) {
            foreach ($rooms as $toRoom) {
                $maxTime = max($maxTime, $this->travelTimeBetween($fromRoom, $toRoom));
            }
        }

        return $maxTime;
    }

    private function travelTimeBetween(Room $fromRoom, Room $toRoom): int
    {
        if ($fromRoom->floor === $toRoom->floor) {
            return abs($fromRoom->position - $toRoom->position);
        }

        return (abs($fromRoom->floor - $toRoom->floor) * 2)
            + ($fromRoom->position - 1)
            + ($toRoom->position - 1);
    }

    private function floorSubsets(array $floors): array
    {
        $subsets = [];
        $floorCount = count($floors);

        for ($mask = 1; $mask < (1 << $floorCount); $mask++) {
            $subset = [];

            for ($index = 0; $index < $floorCount; $index++) {
                if ($mask & (1 << $index)) {
                    $subset[] = $floors[$index];
                }
            }

            if (count($subset) > 1) {
                $subsets[] = $subset;
            }
        }

        return $subsets;
    }

    private function allocations(array $floors, Collection $roomsByFloor, int $roomCount): array
    {
        $results = [];

        $walk = function (int $index, int $remaining, array $current) use (&$walk, &$results, $floors, $roomsByFloor): void {
            if ($index === count($floors)) {
                if ($remaining === 0) {
                    $results[] = $current;
                }

                return;
            }

            $floor = $floors[$index];
            $floorsLeft = count($floors) - $index - 1;
            $maxTake = min($roomsByFloor[$floor]->count(), $remaining - $floorsLeft);

            for ($take = 1; $take <= $maxTake; $take++) {
                $current[$floor] = $take;
                $walk($index + 1, $remaining - $take, $current);
            }
        };

        $walk(0, $roomCount, []);

        return $results;
    }

    private function formatState(Collection $rooms, ?array $selection = null): array
    {
        $selectedRoomNumbers = $selection
            ? $selection['rooms']->pluck('number')->values()->all()
            : [];

        return [
            'rooms' => $rooms->map(fn (Room $room): array => [
                'id' => $room->id,
                'floor' => $room->floor,
                'position' => $room->position,
                'number' => $room->number,
                'status' => $room->status,
                'selected' => in_array($room->number, $selectedRoomNumbers, true),
            ])->values(),
            'summary' => [
                'total' => $rooms->count(),
                'available' => $rooms->where('status', Room::STATUS_AVAILABLE)->count(),
                'booked' => $rooms->where('status', Room::STATUS_BOOKED)->count(),
                'occupied' => $rooms->where('status', Room::STATUS_OCCUPIED)->count(),
            ],
            'last_booking' => $selection ? [
                'rooms' => $selectedRoomNumbers,
                'travel_time' => $selection['travel_time'],
                'strategy' => $selection['strategy'],
            ] : null,
        ];
    }

    private function withoutSortKey(array $candidate): array
    {
        unset($candidate['sort']);

        return $candidate;
    }
}
