<?php

namespace App\Repositories;

use Illuminate\Support\Collection;

interface RoomRepositoryInterface
{
    public function all(): Collection;

    public function available(): Collection;

    public function markBooked(array $roomIds): void;

    public function randomizeOccupancy(): void;

    public function reset(): void;
}
