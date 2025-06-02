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
        Schema::create('videos', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('original_filename');
            $table->string('original_path');
            $table->string('hls_path')->nullable();
            $table->json('resolutions')->nullable(); // Store available resolutions
            $table->enum('status', ['uploading', 'uploaded', 'processing', 'completed', 'failed'])->default('uploading');
            $table->integer('duration')->nullable(); // Duration in seconds
            $table->bigInteger('file_size')->nullable(); // File size in bytes
            $table->string('thumbnail_path')->nullable();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('videos');
    }
};
