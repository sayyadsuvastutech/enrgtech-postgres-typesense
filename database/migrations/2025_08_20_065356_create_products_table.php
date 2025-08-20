<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

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
            
            // JSONB fields for flexible data (better performance for PostgreSQL)
            $table->jsonb('images')->nullable();
            $table->jsonb('thumbnails')->nullable();
            $table->jsonb('attributes')->nullable();
            
            // PostgreSQL tsvector for full-text search - will be added via raw SQL
            
            $table->timestamps();
        });
        
        // Add tsvector column for full-text search using raw SQL
        DB::statement('ALTER TABLE products ADD COLUMN search_vector tsvector');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
