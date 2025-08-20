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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            
            // Basic product fields
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('sku')->unique();
            $table->decimal('price', 10, 2);
            $table->integer('stock_quantity')->default(0);
            $table->enum('status', ['active', 'inactive', 'draft'])->default('active');
            
            // Foreign keys
            $table->foreignId('category_id')->constrained()->onDelete('cascade');
            $table->foreignId('brand_id')->constrained()->onDelete('cascade');
            $table->foreignId('manufacturer_id')->constrained()->onDelete('cascade');
            
            // Denormalized fields for performance
            $table->string('category_name');
            $table->string('brand_name');
            $table->string('manufacturer_name');
            
            // JSON fields for flexible data
            $table->json('images')->nullable();
            $table->json('thumbnails')->nullable();
            $table->json('attributes')->nullable();
            
            // PostgreSQL tsvector for full-text search
            $table->tsvector('search_vector')->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
