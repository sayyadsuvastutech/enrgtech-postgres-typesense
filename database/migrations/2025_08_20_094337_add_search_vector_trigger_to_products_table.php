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
        // Create the trigger function
        DB::statement("
            CREATE OR REPLACE FUNCTION update_product_search_vector()
            RETURNS TRIGGER AS $$
            BEGIN
                NEW.search_vector = to_tsvector('english',
                    coalesce(NEW.name, '') || ' ' ||
                    coalesce(NEW.description, '') || ' ' ||
                    coalesce(NEW.sku, '') || ' ' ||
                    coalesce(NEW.category_name, '') || ' ' ||
                    coalesce(NEW.brand_name, '') || ' ' ||
                    coalesce(NEW.manufacturer_name, '')
                );
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        ");

        // Create the trigger
        DB::statement("
            CREATE TRIGGER products_search_vector_trigger
                BEFORE INSERT OR UPDATE ON products
                FOR EACH ROW
                EXECUTE FUNCTION update_product_search_vector();
        ");

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop the trigger
        DB::statement("DROP TRIGGER IF EXISTS products_search_vector_trigger ON products;");

        // Drop the function
        DB::statement("DROP FUNCTION IF EXISTS update_product_search_vector();");

    }
};
