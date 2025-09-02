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
        Schema::create('ioa_product_quantities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('ioa_products')->onDelete('cascade');
            $table->string('source_name', 50);
            $table->string('unit', 50)->nullable();
            $table->integer('quantity')->default(0);
            $table->string('availability_status', 50)->nullable();
            $table->timestamps();
        });
    }
};
