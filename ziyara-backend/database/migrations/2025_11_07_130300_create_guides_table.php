<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->longText('bio')->nullable();
            $table->json('languages')->nullable();
            $table->json('certifications')->nullable();
            $table->integer('experience_years')->default(0);
            $table->json('specialties')->nullable();
            $table->boolean('is_verified')->default(false);
            $table->boolean('is_available')->default(true);
            $table->decimal('rating', 3, 2)->nullable();
            $table->integer('total_tours')->default(0);
            $table->string('profile_image')->nullable();
            $table->timestamps();

            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guides');
    }
};
