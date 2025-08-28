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
        Schema::create('ioa_product_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('ioa_products')->onDelete('cascade');
            $table->string('source_name', 50);
            $table->jsonb('pricing_ranges');
            $table->string('currency', 50)->nullable();
            $table->string('unit', 50)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ioa_product_prices');
    }
};
