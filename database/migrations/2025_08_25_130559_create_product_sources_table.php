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
        Schema::create('product_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->string('source_name', 50); // dk, rs, ct, vp, et
            $table->bigInteger('source_product_id');
            $table->text('source_url')->nullable();
            $table->timestamps();

            // Indexes for performance
            $table->index(['product_id', 'source_name']);
            $table->index('source_product_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_sources');
    }
};
