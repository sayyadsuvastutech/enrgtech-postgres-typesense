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
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->integer('parent_category')->nullable();
            $table->boolean('is_main')->default(false);
            $table->boolean('pushed')->default(false);
            $table->string('meta_title')->nullable();
            $table->string('meta_description')->nullable();
            $table->foreignId('status_id')->default(1)->constrained('statuses')->onDelete('restrict');
            $table->integer('products_count')->default(0);
            $table->bigInteger('created_by')->nullable();
            $table->bigInteger('updated_by')->nullable();
            $table->timestamps();

            // Core indexes for performance
            $table->index('parent_category'); // For recursive queries
            $table->index('slug');
            $table->index('is_main'); // For main category filtering
            $table->index('pushed'); // For published categories
            $table->index('name'); // For name searches
            $table->index('status_id'); // For status filtering
            $table->index('products_count'); // For sorting by popularity
            
            // Composite indexes for complex queries
            $table->index(['parent_category', 'is_main']); // Main categories under parent
            $table->index(['parent_category', 'name']); // Ordered children
            $table->index(['is_main', 'pushed']); // Published main categories
            $table->index(['status_id', 'pushed']); // Active published categories
            $table->index(['parent_category', 'products_count']); // Popular children
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
