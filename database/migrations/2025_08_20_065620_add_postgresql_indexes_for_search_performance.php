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
        DB::statement('
            -- GIN index for full-text search on tsvector
            CREATE INDEX CONCURRENTLY IF NOT EXISTS products_search_vector_gin_idx 
            ON products USING GIN (search_vector);
        ');

        DB::statement('
            -- GIN index for JSONB attributes column
            CREATE INDEX CONCURRENTLY IF NOT EXISTS products_attributes_gin_idx 
            ON products USING GIN (attributes);
        ');

        DB::statement('
            -- GIN index for JSONB images column
            CREATE INDEX CONCURRENTLY IF NOT EXISTS products_images_gin_idx 
            ON products USING GIN (images);
        ');

        DB::statement('
            -- Composite B-tree indexes for common query patterns
            CREATE INDEX CONCURRENTLY IF NOT EXISTS products_status_category_price_idx 
            ON products (status, category_id, price) WHERE status = \'active\';
        ');

        DB::statement('
            -- Composite index for brand and category filtering
            CREATE INDEX CONCURRENTLY IF NOT EXISTS products_brand_category_status_idx 
            ON products (brand_id, category_id, status) WHERE status = \'active\';
        ');

        DB::statement('
            -- Index for price range queries
            CREATE INDEX CONCURRENTLY IF NOT EXISTS products_price_status_idx 
            ON products (price, status) WHERE status = \'active\';
        ');

        DB::statement('
            -- Index for stock availability
            CREATE INDEX CONCURRENTLY IF NOT EXISTS products_stock_status_idx 
            ON products (stock_quantity, status) WHERE status = \'active\' AND stock_quantity > 0;
        ');

        DB::statement('
            -- Text search index for product names
            CREATE INDEX CONCURRENTLY IF NOT EXISTS products_name_gin_trgm_idx 
            ON products USING GIN (name gin_trgm_ops);
        ');

        DB::statement('
            -- Text search index for SKU
            CREATE INDEX CONCURRENTLY IF NOT EXISTS products_sku_gin_trgm_idx 
            ON products USING GIN (sku gin_trgm_ops);
        ');

        DB::statement('
            -- Denormalized field indexes for faster JOINless queries
            CREATE INDEX CONCURRENTLY IF NOT EXISTS products_category_name_idx 
            ON products (category_name);
        ');

        DB::statement('
            CREATE INDEX CONCURRENTLY IF NOT EXISTS products_brand_name_idx 
            ON products (brand_name);
        ');

        DB::statement('
            CREATE INDEX CONCURRENTLY IF NOT EXISTS products_manufacturer_name_idx 
            ON products (manufacturer_name);
        ');

        // Enable trigram extension for fuzzy text search
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm;');
        
        // Enable unaccent extension for accent-insensitive search
        DB::statement('CREATE EXTENSION IF NOT EXISTS unaccent;');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS products_search_vector_gin_idx;');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS products_attributes_gin_idx;');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS products_images_gin_idx;');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS products_status_category_price_idx;');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS products_brand_category_status_idx;');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS products_price_status_idx;');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS products_stock_status_idx;');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS products_name_gin_trgm_idx;');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS products_sku_gin_trgm_idx;');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS products_category_name_idx;');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS products_brand_name_idx;');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS products_manufacturer_name_idx;');
    }
};
