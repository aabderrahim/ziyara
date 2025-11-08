<?php

// This file was renamed to ensure categories migration runs before tours migration.
// Kept as a placeholder to avoid duplicate migrations being accidentally run.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // no-op: original migration was renamed to 2025_11_07_123000_create_categories_table.php
    }

    public function down(): void
    {
        // no-op
    }
};
