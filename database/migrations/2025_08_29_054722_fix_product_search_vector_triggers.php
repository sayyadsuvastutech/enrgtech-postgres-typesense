<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * This migration fixes the search vector trigger issues where:
     * 1. Multiple conflicting triggers exist on the same table
     * 2. Search vector is completely replaced instead of updated
     * 3. Attribute updates cause loss of existing search vector data
     */
    public function up(): void
    {
        echo "Fixing product search vector triggers...\n";

        // Step 1: Remove conflicting triggers
        $this->removeConflictingTriggers();

        // Step 2: Create improved search vector functions
        $this->createImprovedSearchVectorFunctions();

        // Step 3: Create new optimized triggers
        $this->createOptimizedTriggers();

        echo "Search vector triggers fixed successfully!\n";
    }

    /**
     * Remove all existing conflicting triggers and functions
     */
    private function removeConflictingTriggers(): void
    {
        echo "Removing conflicting triggers and functions...\n";

        // Drop all existing triggers related to search vector
        $triggersToRemove = [
            'ioa_ioa_products_search_vector_trigger ON ioa_products',
            'products_search_vector_trigger ON ioa_products',
            'product_attributes_search_sync_trigger ON ioa_product_attributes',
            'product_sources_search_sync_trigger ON ioa_product_sources',
            'product_documents_search_sync_trigger ON ioa_product_documents'
        ];

        foreach ($triggersToRemove as $trigger) {
            DB::statement("DROP TRIGGER IF EXISTS {$trigger}");
        }

        // Drop old functions
        $functionsToRemove = [
            'update_product_search_vector()',
            'update_product_search_from_attributes()',
            'update_product_search_from_sources()',
            'update_product_search_from_documents()'
        ];

        foreach ($functionsToRemove as $function) {
            DB::statement("DROP FUNCTION IF EXISTS {$function}");
        }
    }

    /**
     * Create improved search vector functions that properly update instead of replace
     */
    private function createImprovedSearchVectorFunctions(): void
    {
        echo "Creating improved search vector functions...\n";

        // Function to build complete search vector (for product updates)
        DB::statement("
            CREATE OR REPLACE FUNCTION build_complete_product_search_vector(product_id_param bigint)
            RETURNS tsvector AS $$
            DECLARE
                product_data RECORD;
                attribute_text TEXT := '';
                source_text TEXT := '';
                document_text TEXT := '';
                complete_vector tsvector;
            BEGIN
                -- Get product data
                SELECT name, title, pnum, mf_pnum, category_name, manufacturer_name, brand_name, description
                INTO product_data
                FROM ioa_products 
                WHERE id = product_id_param;

                IF NOT FOUND THEN
                    RETURN to_tsvector('english', '');
                END IF;

                -- Collect attributes text from JSONB - extract both keys and values
                SELECT COALESCE(string_agg(
                    CASE 
                        WHEN pa.attributes IS NOT NULL AND jsonb_typeof(pa.attributes) = 'object' THEN
                            COALESCE(
                                (
                                    SELECT string_agg(
                                        CASE
                                            WHEN jsonb_typeof(attr.value) = 'string' THEN
                                                attr.key || ' ' || REPLACE(attr.value::text, '\"', '')
                                            WHEN jsonb_typeof(attr.value) IN ('number', 'boolean') THEN
                                                attr.key || ' ' || attr.value::text
                                            ELSE
                                                attr.key
                                        END,
                                        ' '
                                    )
                                    FROM jsonb_each(pa.attributes) AS attr(key, value)
                                ), ''
                            )
                        ELSE ''
                    END, ' '
                ), '') 
                INTO attribute_text
                FROM ioa_product_attributes pa
                WHERE pa.product_id = product_id_param;

                -- Collect source data text
                SELECT COALESCE(string_agg(
                    CASE 
                        WHEN ps.source_data IS NOT NULL THEN ps.source_data::text
                        ELSE ''
                    END, ' '
                ), '')
                INTO source_text
                FROM ioa_product_sources ps
                WHERE ps.product_id = product_id_param;

                -- Collect document data text
                SELECT COALESCE(string_agg(
                    CASE 
                        WHEN pd.documents_data IS NOT NULL THEN pd.documents_data::text
                        ELSE ''
                    END, ' '
                ), '')
                INTO document_text
                FROM ioa_product_documents pd
                WHERE pd.product_id = product_id_param;

                -- Build complete search vector
                complete_vector := to_tsvector('english', 
                    COALESCE(product_data.name, '') || ' ' ||
                    COALESCE(product_data.title, '') || ' ' ||
                    COALESCE(product_data.pnum, '') || ' ' ||
                    COALESCE(product_data.mf_pnum, '') || ' ' ||
                    COALESCE(product_data.category_name, '') || ' ' ||
                    COALESCE(product_data.manufacturer_name, '') || ' ' ||
                    COALESCE(product_data.brand_name, '') || ' ' ||
                    COALESCE(product_data.description, '') || ' ' ||
                    COALESCE(attribute_text, '') || ' ' ||
                    COALESCE(source_text, '') || ' ' ||
                    COALESCE(document_text, '')
                );

                RETURN complete_vector;
            END;
            $$ LANGUAGE plpgsql;
        ");

        // Function to update product search vector (for product table changes)
        DB::statement("
            CREATE OR REPLACE FUNCTION update_product_search_vector_fixed()
            RETURNS TRIGGER AS $$
            BEGIN
                -- For INSERT operations, we can't use NEW.id yet, so build the vector from available fields
                IF TG_OP = 'INSERT' THEN
                    -- Build search vector from product fields only (no related data yet)
                    NEW.search_vector := to_tsvector('english', 
                        COALESCE(NEW.name, '') || ' ' ||
                        COALESCE(NEW.title, '') || ' ' ||
                        COALESCE(NEW.pnum, '') || ' ' ||
                        COALESCE(NEW.mf_pnum, '') || ' ' ||
                        COALESCE(NEW.category_name, '') || ' ' ||
                        COALESCE(NEW.manufacturer_name, '') || ' ' ||
                        COALESCE(NEW.brand_name, '') || ' ' ||
                        COALESCE(NEW.description, '')
                    );
                ELSE
                    -- For UPDATE operations, we can use the complete function with related data
                    NEW.search_vector := build_complete_product_search_vector(NEW.id);
                END IF;
                
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        ");

        // Function to refresh product search when attributes change
        DB::statement("
            CREATE OR REPLACE FUNCTION refresh_product_search_from_attributes()
            RETURNS TRIGGER AS $$
            DECLARE
                target_product_id bigint;
            BEGIN
                -- Get the product ID from the changed record
                target_product_id := COALESCE(NEW.product_id, OLD.product_id);
                
                IF target_product_id IS NULL THEN
                    RETURN COALESCE(NEW, OLD);
                END IF;

                -- Update the product's search vector with complete rebuild
                -- This ensures attributes are properly included
                UPDATE ioa_products 
                SET search_vector = build_complete_product_search_vector(target_product_id)
                WHERE id = target_product_id;
                
                RETURN COALESCE(NEW, OLD);
            END;
            $$ LANGUAGE plpgsql;
        ");

        // Function to refresh product search when sources change
        DB::statement("
            CREATE OR REPLACE FUNCTION refresh_product_search_from_sources()
            RETURNS TRIGGER AS $$
            DECLARE
                target_product_id bigint;
            BEGIN
                target_product_id := COALESCE(NEW.product_id, OLD.product_id);
                
                IF target_product_id IS NULL THEN
                    RETURN COALESCE(NEW, OLD);
                END IF;

                UPDATE ioa_products 
                SET search_vector = build_complete_product_search_vector(target_product_id)
                WHERE id = target_product_id;
                
                RETURN COALESCE(NEW, OLD);
            END;
            $$ LANGUAGE plpgsql;
        ");

        // Function to refresh product search when documents change
        DB::statement("
            CREATE OR REPLACE FUNCTION refresh_product_search_from_documents()
            RETURNS TRIGGER AS $$
            DECLARE
                target_product_id bigint;
            BEGIN
                target_product_id := COALESCE(NEW.product_id, OLD.product_id);
                
                IF target_product_id IS NULL THEN
                    RETURN COALESCE(NEW, OLD);
                END IF;

                UPDATE ioa_products 
                SET search_vector = build_complete_product_search_vector(target_product_id)
                WHERE id = target_product_id;
                
                RETURN COALESCE(NEW, OLD);
            END;
            $$ LANGUAGE plpgsql;
        ");
    }

    /**
     * Create optimized triggers that properly handle search vector updates
     */
    private function createOptimizedTriggers(): void
    {
        echo "Creating optimized triggers...\n";

        // Main product search vector trigger
        DB::statement("
            CREATE TRIGGER products_search_vector_update_trigger
                BEFORE INSERT OR UPDATE ON ioa_products
                FOR EACH ROW
                EXECUTE FUNCTION update_product_search_vector_fixed();
        ");

        // Attribute changes trigger
        DB::statement("
            CREATE TRIGGER product_attributes_refresh_search_trigger
                AFTER INSERT OR UPDATE OR DELETE ON ioa_product_attributes
                FOR EACH ROW
                EXECUTE FUNCTION refresh_product_search_from_attributes();
        ");

        // Source changes trigger
        DB::statement("
            CREATE TRIGGER product_sources_refresh_search_trigger
                AFTER INSERT OR UPDATE OR DELETE ON ioa_product_sources
                FOR EACH ROW
                EXECUTE FUNCTION refresh_product_search_from_sources();
        ");

        // Document changes trigger
        DB::statement("
            CREATE TRIGGER product_documents_refresh_search_trigger
                AFTER INSERT OR UPDATE OR DELETE ON ioa_product_documents
                FOR EACH ROW
                EXECUTE FUNCTION refresh_product_search_from_documents();
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        echo "Reverting search vector trigger fixes...\n";

        // Remove the new triggers
        DB::statement('DROP TRIGGER IF EXISTS product_documents_refresh_search_trigger ON ioa_product_documents');
        DB::statement('DROP TRIGGER IF EXISTS product_sources_refresh_search_trigger ON ioa_product_sources');
        DB::statement('DROP TRIGGER IF EXISTS product_attributes_refresh_search_trigger ON ioa_product_attributes');
        DB::statement('DROP TRIGGER IF EXISTS products_search_vector_update_trigger ON ioa_products');

        // Remove the new functions
        DB::statement('DROP FUNCTION IF EXISTS refresh_product_search_from_documents()');
        DB::statement('DROP FUNCTION IF EXISTS refresh_product_search_from_sources()');
        DB::statement('DROP FUNCTION IF EXISTS refresh_product_search_from_attributes()');
        DB::statement('DROP FUNCTION IF EXISTS update_product_search_vector_fixed()');
        DB::statement('DROP FUNCTION IF EXISTS build_complete_product_search_vector(bigint)');

        echo "Search vector trigger fixes reverted.\n";
    }
};