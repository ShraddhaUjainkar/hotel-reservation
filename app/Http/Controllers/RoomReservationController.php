<?php

namespace App\Http\Controllers;

use App\Services\RoomReservationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoomReservationController extends Controller
{
    public function __construct(private readonly RoomReservationService $reservations) {}

    public function index(): JsonResponse
    {
        return response()->json($this->reservations->getState());
    }

    public function book(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'rooms' => ['required', 'integer', 'min:1', 'max:5'],
        ]);

        return response()->json($this->reservations->book((int) $validated['rooms']));
    }

    public function randomize(): JsonResponse
    {
        return response()->json($this->reservations->randomize());
    }

    public function reset(): JsonResponse
    {
        return response()->json($this->reservations->reset());
    }
}
