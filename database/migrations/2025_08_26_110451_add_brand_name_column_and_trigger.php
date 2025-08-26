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

        // Update existing products with brand names
        DB::statement('
            UPDATE products
            SET brand_name = brands.name
            FROM brands
            WHERE products.brand_id = brands.id
        ');

        // Create function to update product brand_name when brand is updated
        DB::statement("
            CREATE OR REPLACE FUNCTION update_product_brand_name()
            RETURNS TRIGGER AS \$\$
            BEGIN
                -- Update products when brand name changes
                IF TG_OP = 'UPDATE' AND OLD.name IS DISTINCT FROM NEW.name THEN
                    UPDATE products
                    SET brand_name = NEW.name,
                        updated_at = NOW()
                    WHERE brand_id = NEW.id;
                END IF;

                -- Handle brand deletion
                IF TG_OP = 'DELETE' THEN
                    UPDATE products
                    SET brand_name = NULL,
                        updated_at = NOW()
                    WHERE brand_id = OLD.id;
                    RETURN OLD;
                END IF;

                RETURN NEW;
            END;
            \$\$ LANGUAGE plpgsql;
        ");

        // Create triggers for brands table
        DB::statement('
            CREATE TRIGGER trigger_update_product_brand_name_on_update
                AFTER UPDATE ON brands
                FOR EACH ROW
                EXECUTE FUNCTION update_product_brand_name();
        ');

        DB::statement('
            CREATE TRIGGER trigger_update_product_brand_name_on_delete
                AFTER DELETE ON brands
                FOR EACH ROW
                EXECUTE FUNCTION update_product_brand_name();
        ');

        // Create trigger to update product brand_name when brand_id is updated in products
        DB::statement("
            CREATE OR REPLACE FUNCTION update_product_brand_name_on_brand_id_change()
            RETURNS TRIGGER AS \$\$
            BEGIN
                IF OLD.brand_id IS DISTINCT FROM NEW.brand_id THEN
                    -- Set brand_name based on new brand_id
                    IF NEW.brand_id IS NULL THEN
                        NEW.brand_name = NULL;
                    ELSE
                        SELECT name INTO NEW.brand_name
                        FROM brands
                        WHERE id = NEW.brand_id;
                    END IF;
                END IF;

                RETURN NEW;
            END;
            \$\$ LANGUAGE plpgsql;
        ");

        DB::statement('
            CREATE TRIGGER trigger_update_brand_name_on_brand_id_change
                BEFORE UPDATE ON products
                FOR EACH ROW
                EXECUTE FUNCTION update_product_brand_name_on_brand_id_change();
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop triggers first
        DB::statement('DROP TRIGGER IF EXISTS trigger_update_product_brand_name_on_update ON brands;');
        DB::statement('DROP TRIGGER IF EXISTS trigger_update_product_brand_name_on_delete ON brands;');
        DB::statement('DROP TRIGGER IF EXISTS trigger_update_brand_name_on_brand_id_change ON products;');

        // Drop functions
        DB::statement('DROP FUNCTION IF EXISTS update_product_brand_name();');
        DB::statement('DROP FUNCTION IF EXISTS update_product_brand_name_on_brand_id_change();');

        // Remove brand_name column
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('brand_name');
        });
    }
};
