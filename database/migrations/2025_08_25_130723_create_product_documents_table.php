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
        Schema::create('ioa_product_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('ioa_products')->onDelete('cascade');
            $table->string('source_name', 50); // dk, rs, ct, vp, et
            $table->jsonb('documents_data')->nullable(); // JSON structure for documents array
            $table->timestamps();

        });
    }

};
