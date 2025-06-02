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
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g., "Basic", "Pro", "Enterprise"
            $table->string('slug')->unique(); // e.g., "basic", "pro", "enterprise"
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2); // Monthly price
            $table->json('features'); // JSON array of features
            $table->json('limits'); // JSON object with limits (api_calls_per_month, storage_gb, etc.)
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscription_plans');
    }
};
