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
            $table->string('title');
            $table->string('code')->nullable();
            $table->string('product_number')->unique();
            $table->string('manufacturer_product_number')->nullable();
            $table->string('manufacturer_product_slug')->nullable();
            $table->text('description')->nullable();

            // Foreign keys
            $table->foreignId('category_id')->constrained()->onDelete('cascade');
            $table->foreignId('manufacturer_id')->constrained()->onDelete('cascade');

            // Additional fields
            $table->string('breadcrumb')->nullable();
            $table->string('meta_title')->nullable();
            $table->string('meta_description')->nullable();
            
            // Denormalized fields for performance (synced via triggers)
            $table->string('category_name')->nullable();
            $table->string('manufacturer_name')->nullable();
            $table->boolean('is_rohs_compliant')->default(false);
            $table->boolean('is_verified')->default(false);
            $table->boolean('is_pushed')->default(false);
            $table->integer('total_reviews')->default(0);
            $table->decimal('average_rating', 3, 2)->nullable();
            $table->string('video_url')->nullable();
            $table->foreignId('status_id')->default(1)->constrained('statuses')->onDelete('restrict');
            $table->integer('session_insert_id')->nullable();
            $table->integer('session_update_id')->nullable();
            $table->boolean('is_updated')->default(false);
            $table->bigInteger('created_by')->nullable();
            $table->bigInteger('updated_by')->nullable();

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
