<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // For MySQL, we need to use raw SQL to modify enum
        DB::statement("ALTER TABLE videos MODIFY COLUMN status ENUM('uploading', 'uploaded', 'processing', 'completed', 'failed') DEFAULT 'uploading'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert back to original enum
        DB::statement("ALTER TABLE videos MODIFY COLUMN status ENUM('uploading', 'processing', 'completed', 'failed') DEFAULT 'uploading'");
    }
};
