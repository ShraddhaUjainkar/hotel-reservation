<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    public const STATUS_AVAILABLE = 'available';

    public const STATUS_BOOKED = 'booked';

    public const STATUS_OCCUPIED = 'occupied';

    protected $fillable = [
        'floor',
        'position',
        'number',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'floor' => 'integer',
            'position' => 'integer',
            'number' => 'integer',
        ];
    }
}
