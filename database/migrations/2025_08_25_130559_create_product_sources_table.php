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
        Schema::create('ioa_product_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('ioa_products')->onDelete('cascade');
            $table->string('source_name', 50); // dk, rs, ct, vp, et
            $table->bigInteger('source_product_id')->nullable();
            $table->text('source_url')->nullable();
            $table->jsonb('source_data')->nullable();

            $table->timestamps();
        });
    }
};
