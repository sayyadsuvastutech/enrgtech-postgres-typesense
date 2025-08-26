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
        Schema::create('product_attributes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->string('source_name', 50); // dk, rs, ct, vp, et
            $table->jsonb('attributes')->nullable();
            $table->timestamps();

            // Indexes for performance
            $table->index('product_id'); // For queries filtering by product_id
            $table->index('source_name'); // For queries filtering by source_name
            $table->index(['product_id', 'source_name']); // Composite index for queries combining both
            $table->index('created_at'); // For queries sorting or filtering by creation time
            // GIN index for JSONB attributes to support queries on specific keys/values
            $table->index('attributes', 'attributes_gin_idx', 'gin');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_attributes');
    }
};
