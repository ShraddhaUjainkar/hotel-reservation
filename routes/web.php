<?php

use App\Http\Controllers\RoomReservationController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::prefix('api/rooms')->controller(RoomReservationController::class)->group(function (): void {
    Route::get('/', 'index');
    Route::post('/book', 'book');
    Route::post('/randomize', 'randomize');
    Route::post('/reset', 'reset');
});
