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
        // Create trigger function to update category name in products when category name changes
        DB::statement("
            CREATE OR REPLACE FUNCTION sync_category_name_to_products()
            RETURNS TRIGGER AS $$
            BEGIN
                UPDATE ioa_products 
                SET category_name = NEW.name
                WHERE category_id = NEW.id;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        ");

        // Create trigger on categories table
        DB::statement("
            CREATE TRIGGER categories_sync_products_trigger
                AFTER UPDATE OF name ON ioa_categories
                FOR EACH ROW
                EXECUTE FUNCTION sync_category_name_to_products();
        ");

        // Create trigger function to update manufacturer name in products when manufacturer name changes
        DB::statement("
            CREATE OR REPLACE FUNCTION sync_manufacturer_name_to_products()
            RETURNS TRIGGER AS $$
            BEGIN
                UPDATE ioa_products 
                SET manufacturer_name = NEW.name
                WHERE manufacturer_id = NEW.id;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        ");

        // Create trigger on manufacturers table
        DB::statement("
            CREATE TRIGGER manufacturers_sync_products_trigger
                AFTER UPDATE OF name ON ioa_manufacturers
                FOR EACH ROW
                EXECUTE FUNCTION sync_manufacturer_name_to_products();
        ");

        // Create trigger function to populate denormalized fields on product insert/update
        DB::statement("
            CREATE OR REPLACE FUNCTION populate_product_denormalized_fields()
            RETURNS TRIGGER AS $$
            BEGIN
                -- Update category_name
                SELECT name INTO NEW.category_name
                FROM ioa_categories 
                WHERE id = NEW.category_id;

                -- Update manufacturer_name
                SELECT name INTO NEW.manufacturer_name
                FROM ioa_manufacturers 
                WHERE id = NEW.manufacturer_id;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        ");

        // Create trigger on products table to populate denormalized fields
        DB::statement("
            CREATE TRIGGER products_populate_denormalized_fields_trigger
                BEFORE INSERT OR UPDATE ON ioa_products
                FOR EACH ROW
                EXECUTE FUNCTION populate_product_denormalized_fields();
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop triggers and functions in reverse order
        DB::statement('DROP TRIGGER IF EXISTS products_populate_denormalized_fields_trigger ON ioa_products');
        DB::statement('DROP FUNCTION IF EXISTS populate_product_denormalized_fields()');

        DB::statement('DROP TRIGGER IF EXISTS manufacturers_sync_products_trigger ON ioa_manufacturers');
        DB::statement('DROP FUNCTION IF EXISTS sync_manufacturer_name_to_products()');

        DB::statement('DROP TRIGGER IF EXISTS categories_sync_products_trigger ON ioa_categories');
        DB::statement('DROP FUNCTION IF EXISTS sync_category_name_to_products()');
    }
};
