<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tours', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->longText('description');
            $table->string('short_description')->nullable();
            $table->integer('duration')->comment('بالساعات');
            $table->integer('max_participants');
            $table->decimal('price', 8, 2);
            $table->enum('difficulty_level', ['easy', 'moderate', 'hard']);
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('guide_id')->constrained('users')->cascadeOnDelete();
            $table->string('location')->nullable();
            $table->string('meeting_point')->nullable();
            $table->json('included_items')->nullable();
            $table->json('excluded_items')->nullable();
            $table->json('requirements')->nullable();
            $table->enum('status', ['draft', 'active', 'inactive'])->default('draft');
            $table->boolean('featured')->default(false);
            $table->json('images')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['category_id', 'guide_id', 'status', 'featured']);
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tours');
    }
};
