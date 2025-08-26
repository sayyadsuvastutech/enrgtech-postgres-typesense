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
        Schema::create('product_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->string('source_name', 50); // dk, rs, ct, vp, et
            $table->jsonb('pricing_ranges');
            $table->string('currency', 50)->nullable();
            $table->string('unit', 50)->nullable();
            $table->timestamps();

            // Composite index for filtering by product_id and source_name
//            $table->index(['product_id', 'source_name'], 'idx_product_id_source_name');

            // Index for product_id-only queries (optional, as composite covers it)
            $table->index('product_id', 'idx_product_id');

            // Index for currency filtering
            $table->index('currency', 'idx_currency');

            // Composite index for product_id and currency
            $table->index(['product_id', 'currency'], 'idx_product_id_currency');

            // Composite index for source_name and currency
            $table->index(['source_name', 'currency'], 'idx_source_name_currency');

            // GIN index for efficient JSONB queries on pricing_ranges
            $table->index('pricing_ranges', 'idx_pricing_ranges', 'gin');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_prices');
    }
};
