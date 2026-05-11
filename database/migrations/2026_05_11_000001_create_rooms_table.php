<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rooms', function (Blueprint $table): void {
            $table->id();
            $table->unsignedTinyInteger('floor');
            $table->unsignedTinyInteger('position');
            $table->unsignedSmallInteger('number')->unique();
            $table->string('status')->default('available');
            $table->timestamps();

            $table->unique(['floor', 'position']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};
