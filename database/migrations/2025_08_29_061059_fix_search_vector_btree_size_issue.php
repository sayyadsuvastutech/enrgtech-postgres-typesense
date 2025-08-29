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
     * This migration fixes the btree index size issue for product search vectors
     * by removing the problematic btree index and optimizing the search vector generation
     * to keep vectors within PostgreSQL's btree limitations.
     */
    public function up(): void
    {
        echo "Fixing search vector btree size issue...\n";

        // Step 1: Remove the problematic btree index on search_vector
        DB::statement('DROP INDEX IF EXISTS idx_ioa_products_search_vector');
        
        // Step 2: Create a more efficient search vector function that limits content size
        $this->createOptimizedSearchVectorFunction();
        
        // Step 3: Update existing search vectors to be within size limits
        $this->updateExistingSearchVectors();
        
        echo "Search vector btree size issue fixed successfully!\n";
    }

    /**
     * Create an optimized search vector function that limits content size
     */
    private function createOptimizedSearchVectorFunction(): void
    {
        echo "Creating optimized search vector function...\n";

        // Replace the existing function with a size-limited version
        DB::statement("
            CREATE OR REPLACE FUNCTION build_complete_product_search_vector(product_id_param bigint)
            RETURNS tsvector AS $$
            DECLARE
                product_data RECORD;
                attribute_text TEXT := '';
                source_text TEXT := '';
                document_text TEXT := '';
                complete_text TEXT := '';
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

                -- Collect limited attributes text (only first 500 chars to prevent size issues)
                SELECT COALESCE(LEFT(string_agg(
                    CASE 
                        WHEN pa.attributes IS NOT NULL AND jsonb_typeof(pa.attributes) = 'object' THEN
                            COALESCE(
                                (
                                    SELECT string_agg(
                                        CASE
                                            WHEN jsonb_typeof(attr.value) = 'string' THEN
                                                attr.key || ' ' || LEFT(REPLACE(attr.value::text, '\"', ''), 50)
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
                ), 500), '') 
                INTO attribute_text
                FROM ioa_product_attributes pa
                WHERE pa.product_id = product_id_param;

                -- Collect limited source data text (only first 300 chars)
                SELECT COALESCE(LEFT(string_agg(
                    CASE 
                        WHEN ps.source_data IS NOT NULL THEN LEFT(ps.source_data::text, 100)
                        ELSE ''
                    END, ' '
                ), 300), '')
                INTO source_text
                FROM ioa_product_sources ps
                WHERE ps.product_id = product_id_param;

                -- Collect limited document data text (only first 300 chars)
                SELECT COALESCE(LEFT(string_agg(
                    CASE 
                        WHEN pd.documents_data IS NOT NULL THEN LEFT(pd.documents_data::text, 100)
                        ELSE ''
                    END, ' '
                ), 300), '')
                INTO document_text
                FROM ioa_product_documents pd
                WHERE pd.product_id = product_id_param;

                -- Build complete search text with size limit (max 1500 chars total)
                complete_text := LEFT(
                    COALESCE(product_data.name, '') || ' ' ||
                    COALESCE(product_data.title, '') || ' ' ||
                    COALESCE(product_data.pnum, '') || ' ' ||
                    COALESCE(product_data.mf_pnum, '') || ' ' ||
                    COALESCE(product_data.category_name, '') || ' ' ||
                    COALESCE(product_data.manufacturer_name, '') || ' ' ||
                    COALESCE(product_data.brand_name, '') || ' ' ||
                    COALESCE(LEFT(product_data.description, 200), '') || ' ' ||
                    COALESCE(attribute_text, '') || ' ' ||
                    COALESCE(source_text, '') || ' ' ||
                    COALESCE(document_text, ''),
                    1500
                );

                -- Build search vector from the limited text
                complete_vector := to_tsvector('english', complete_text);

                RETURN complete_vector;
            END;
            $$ LANGUAGE plpgsql;
        ");

        // Also update the simpler function for INSERT operations
        DB::statement("
            CREATE OR REPLACE FUNCTION update_product_search_vector_fixed()
            RETURNS TRIGGER AS $$
            BEGIN
                -- For both INSERT and UPDATE operations, use the size-limited approach
                IF TG_OP = 'INSERT' THEN
                    -- Build search vector from product fields only (limited size)
                    NEW.search_vector := to_tsvector('english', 
                        LEFT(
                            COALESCE(NEW.name, '') || ' ' ||
                            COALESCE(NEW.title, '') || ' ' ||
                            COALESCE(NEW.pnum, '') || ' ' ||
                            COALESCE(NEW.mf_pnum, '') || ' ' ||
                            COALESCE(NEW.category_name, '') || ' ' ||
                            COALESCE(NEW.manufacturer_name, '') || ' ' ||
                            COALESCE(NEW.brand_name, '') || ' ' ||
                            COALESCE(LEFT(NEW.description, 200), ''),
                            1000
                        )
                    );
                ELSE
                    -- For UPDATE operations, use the complete but size-limited function
                    NEW.search_vector := build_complete_product_search_vector(NEW.id);
                END IF;
                
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        ");
    }

    /**
     * Update existing search vectors to be within size limits
     */
    private function updateExistingSearchVectors(): void
    {
        echo "Updating existing search vectors...\n";
        
        // Update all existing products in batches to avoid memory issues
        DB::statement("
            UPDATE ioa_products 
            SET search_vector = build_complete_product_search_vector(id)
            WHERE search_vector IS NOT NULL
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        echo "Reverting search vector btree size issue fix...\n";

        // Restore the original btree index
        DB::statement('CREATE INDEX IF NOT EXISTS idx_ioa_products_search_vector ON ioa_products USING btree(search_vector) WHERE search_vector IS NOT NULL');
        
        echo "Search vector btree size issue fix reverted.\n";
    }
};
