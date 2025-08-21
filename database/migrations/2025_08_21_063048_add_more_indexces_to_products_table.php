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
                -- Speed up "status = active ORDER BY name"
                CREATE INDEX IF NOT EXISTS products_status_name_idx
                ON products (status, name) WHERE status = \'active\';
            ');

                        DB::statement('
                -- Speed up filtering by category + ordering by name
                CREATE INDEX IF NOT EXISTS products_status_category_name_idx
                ON products (status, category_id, name) WHERE status = \'active\';
            ');

                        DB::statement('
                -- Speed up filtering by brand + ordering by name
                CREATE INDEX IF NOT EXISTS products_status_brand_name_idx
                ON products (status, brand_id, name) WHERE status = \'active\';
            ');

                        DB::statement('
                -- Speed up filtering by manufacturer + ordering by name
                CREATE INDEX IF NOT EXISTS products_status_manufacturer_name_idx
                ON products (status, manufacturer_id, name) WHERE status = \'active\';
            ');

                        DB::statement('
                -- Partial index for active products to accelerate search queries
                CREATE INDEX IF NOT EXISTS products_active_search_vector_idx
                ON products USING GIN (search_vector) WHERE status = \'active\';
            ');

                        DB::statement('
                -- If you frequently sort search results by name after ranking
                CREATE INDEX IF NOT EXISTS products_active_name_idx
                ON products (name) WHERE status = \'active\';
            ');
        });
    }

};
