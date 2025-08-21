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
        Schema::table('products', function (Blueprint $table) {
            DB::statement('
            -- Sorting newest products
            CREATE INDEX IF NOT EXISTS products_status_created_at_idx
            ON products (status, created_at DESC) WHERE status = \'active\';
        ');

            DB::statement('
            -- Faster EXISTS for category filters
            CREATE INDEX IF NOT EXISTS products_status_category_idx
            ON products (status, category_id) WHERE status = \'active\';
        ');

            DB::statement('
            -- Faster EXISTS for brand filters
            CREATE INDEX IF NOT EXISTS products_status_brand_idx
            ON products (status, brand_id) WHERE status = \'active\';
        ');

            DB::statement('
            -- Faster EXISTS for manufacturer filters
            CREATE INDEX IF NOT EXISTS products_status_manufacturer_idx
            ON products (status, manufacturer_id) WHERE status = \'active\';
        ');

            DB::statement('
            -- Active-only trigram search for names
            CREATE INDEX IF NOT EXISTS products_active_name_trgm_idx
            ON products USING GIN (name gin_trgm_ops) WHERE status = \'active\';
        ');

            DB::statement('
            -- Active-only trigram search for SKU
            CREATE INDEX IF NOT EXISTS products_active_sku_trgm_idx
            ON products USING GIN (sku gin_trgm_ops) WHERE status = \'active\';
        ');

        });
    }
    
};
