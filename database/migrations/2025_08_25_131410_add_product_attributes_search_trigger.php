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
        // Create trigger function to update product search_vector when attributes change
        DB::statement("
            CREATE OR REPLACE FUNCTION update_product_search_from_attributes()
            RETURNS TRIGGER AS $$
            BEGIN
                -- Update the search_vector for the related product
                -- This will trigger the existing product search vector update function
                UPDATE products 
                SET updated_at = NOW()  -- Minimal update to trigger the search vector update
                WHERE id = COALESCE(NEW.product_id, OLD.product_id);
                
                RETURN COALESCE(NEW, OLD);
            END;
            $$ LANGUAGE plpgsql;
        ");

        // Create trigger on product_attributes table
        DB::statement("
            CREATE TRIGGER product_attributes_search_sync_trigger
                AFTER INSERT OR UPDATE OR DELETE ON product_attributes
                FOR EACH ROW
                EXECUTE FUNCTION update_product_search_from_attributes();
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS product_attributes_search_sync_trigger ON product_attributes');
        DB::statement('DROP FUNCTION IF EXISTS update_product_search_from_attributes()');
    }
};
