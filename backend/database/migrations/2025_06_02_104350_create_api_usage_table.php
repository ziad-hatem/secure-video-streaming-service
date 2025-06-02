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
        Schema::create('api_usage', function (Blueprint $table) {
            $table->id();
            $table->foreignId('api_key_id')->constrained()->onDelete('cascade');
            $table->string('endpoint'); // The API endpoint called
            $table->string('method', 10); // HTTP method (GET, POST, etc.)
            $table->ipAddress('ip_address');
            $table->text('user_agent')->nullable();
            $table->integer('response_status'); // HTTP response status code
            $table->integer('response_time_ms')->nullable(); // Response time in milliseconds
            $table->bigInteger('bytes_transferred')->nullable(); // For bandwidth tracking
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->index(['api_key_id', 'created_at']);
            $table->index(['endpoint', 'created_at']);
            $table->index('created_at'); // For cleanup/archival
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_usage');
    }
};
