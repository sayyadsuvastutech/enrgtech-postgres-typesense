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
        Schema::create('ioa_products', function (Blueprint $table) {
            $table->id();

            // Basic product fields
            $table->string('name');
            $table->string('title')->nullable();
            $table->string('pnum')->unique();
            $table->string('mf_pnum')->nullable();
            $table->string('mf_pnum_slug')->nullable();
            $table->text('description')->nullable();

            // Foreign keys and denormalized names
            $table->foreignId('category_id')->nullable()->constrained('ioa_categories')->onDelete('cascade');
            $table->string('category_name')->nullable();
            $table->foreignId('manufacturer_id')->nullable()->constrained('ioa_manufacturers')->onDelete('cascade');
            $table->string('manufacturer_name')->nullable();
            $table->foreignId('brand_id')->nullable()->constrained('ioa_brands')->onDelete('set null');
            $table->string('brand_name')->nullable();

            // Additional fields
            $table->string('breadcrumb')->nullable();
            $table->string('meta_title')->nullable();
            $table->string('meta_description')->nullable();

            $table->boolean('is_rohs_compliant')->default(false);
            $table->boolean('pushed')->default(false);
            $table->integer('total_reviews')->default(0);
            $table->decimal('average_rating', 3, 2)->nullable();
            $table->string('video_url')->nullable();
            $table->foreignId('status_id')->default(2)->constrained('ioa_statuses')->onDelete('restrict');
            $table->boolean('is_updated')->default(false);
            $table->integer('sess_insrt_id')->nullable();
            $table->integer('sess_updt_id')->nullable();

            // PostgreSQL tsvector for full-text search - will be added via raw SQL

            $table->timestamps();
        });

        // Add tsvector column for full-text search using raw SQL
        DB::statement('ALTER TABLE ioa_products ADD COLUMN search_vector tsvector NULL');
    }
};
