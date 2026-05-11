<?php

namespace Tests\Feature;

use App\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoomReservationTest extends TestCase
{
    use RefreshDatabase;

    public function test_application_returns_the_reservation_dashboard(): void
    {
        $this->get('/')->assertOk();
    }

    public function test_guest_can_book_rooms_on_the_same_floor_first(): void
    {
        $response = $this->postJson('/api/rooms/book', [
            'rooms' => 4,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('last_booking.rooms', [101, 102, 103, 104])
            ->assertJsonPath('last_booking.travel_time', 3)
            ->assertJsonPath('summary.booked', 4);
    }

    public function test_booking_spans_floors_by_minimum_travel_time_when_needed(): void
    {
        $this->getJson('/api/rooms')->assertOk();

        Room::query()->update(['status' => Room::STATUS_OCCUPIED]);
        Room::query()
            ->whereIn('number', [101, 102, 201, 202, 301, 302])
            ->update(['status' => Room::STATUS_AVAILABLE]);

        $response = $this->postJson('/api/rooms/book', [
            'rooms' => 4,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('last_booking.rooms', [101, 102, 201, 202])
            ->assertJsonPath('last_booking.travel_time', 4);
    }

    public function test_guest_cannot_book_more_than_five_rooms(): void
    {
        $this->postJson('/api/rooms/book', [
            'rooms' => 6,
        ])->assertUnprocessable();
    }
}
