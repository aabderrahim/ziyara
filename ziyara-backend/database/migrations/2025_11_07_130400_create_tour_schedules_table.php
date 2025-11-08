<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tour_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tour_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->integer('available_spots')->default(0);
            $table->enum('status', ['available', 'full', 'cancelled'])->default('available');
            $table->timestamps();

            $table->index(['tour_id', 'date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tour_schedules');
    }
};
