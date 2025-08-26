<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('product_quantities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->string('source_name', 50); // dk, rs, ct, vp, et
            $table->string('unit', 50)->nullable();
            $table->integer('quantity')->default(0);
            $table->string('availability_status', 50)->nullable();
            $table->timestamps();

            // Composite index for filtering by product_id and source_name
            $table->index(['product_id', 'source_name'], 'idx_product_id_source_name');

            // Composite index for filtering by product_id and availability_status
            $table->index(['product_id', 'availability_status'], 'idx_product_id_availability');

            // Composite index for filtering by source_name and availability_status
            $table->index(['source_name', 'availability_status'], 'idx_source_name_availability');

            // Index for quantity range queries
            $table->index('quantity', 'idx_quantity');

            $table->index(['product_id', 'quantity'], 'idx_product_id_quantity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_quantities');
    }
};
