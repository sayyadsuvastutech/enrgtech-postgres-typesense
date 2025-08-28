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
     * This migration creates a comprehensive PostgreSQL-based product search system
     * with semantic search capabilities, fuzzy matching, and performance-optimized indexes.
     *
     * Index Creation Order:
     * 1. PostgreSQL Extensions
     * 2. Search Vector Triggers
     * 3. Core Performance Indexes (most frequently used)
     * 4. Full-Text Search Indexes
     * 5. Fuzzy Matching Indexes
     * 6. Sorting & Filtering Indexes
     * 7. Composite Indexes (multi-field)
     * 8. JSONB Indexes
     */
    public function up(): void
    {
        // Step 1: Enable PostgreSQL extensions
        $this->enablePostgreSQLExtensions();

        // Step 2: Create search vector trigger
        $this->createSearchVectorTrigger();

        // Step 3: Core performance indexes (most critical)
        $this->createCorePerformanceIndexes();

        // Step 4: Full-text search indexes
        $this->createFullTextSearchIndexes();

        // Step 5: Fuzzy matching indexes
        $this->createFuzzyMatchingIndexes();

        // Step 6: Sorting and filtering indexes
        $this->createSortingAndFilteringIndexes();

        // Step 7: Composite indexes for complex queries
        $this->createCompositeIndexes();

        // Step 8: JSONB indexes for structured data
        $this->createJsonbIndexes();
    }

    /**
     * Step 1: Enable PostgreSQL extensions required for advanced search functionality
     */
    private function enablePostgreSQLExtensions(): void
    {
        echo "Creating PostgreSQL extensions...\n";

        // pg_trgm: Provides trigram matching for similarity searches and fuzzy text matching
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm;');

        // unaccent: Removes accents from text for better international search support
        DB::statement('CREATE EXTENSION IF NOT EXISTS unaccent;');

        // fuzzystrmatch: Provides Levenshtein distance and other fuzzy string matching algorithms
        DB::statement('CREATE EXTENSION IF NOT EXISTS fuzzystrmatch;');
    }

    /**
     * Step 2: Create trigger function and trigger to automatically maintain search vectors
     */
    private function createSearchVectorTrigger(): void
    {
        echo "Creating search vector trigger...\n";

//        // Create the trigger function
//        DB::statement("
//            CREATE OR REPLACE FUNCTION update_product_search_vector()
//            RETURNS TRIGGER AS $$
//            BEGIN
//                NEW.search_vector = to_tsvector('english',
//                    coalesce(NEW.name, '') || ' ' ||
//                    coalesce(NEW.description, '') || ' ' ||
//                    coalesce(NEW.sku, '') || ' ' ||
//                    coalesce(NEW.category_name, '') || ' ' ||
//                    coalesce(NEW.brand_name, '') || ' ' ||
//                    coalesce(NEW.manufacturer_name, '')
//                );
//                RETURN NEW;
//            END;
//            $$ LANGUAGE plpgsql;
//        ");

        DB::statement("
            CREATE OR REPLACE FUNCTION update_product_search_vector()
            RETURNS TRIGGER AS $$
            DECLARE
                attributes_text TEXT := '';
            BEGIN
                -- Extract attributes from related product_attributes table
                SELECT string_agg(
                    CASE
                        WHEN pa.attributes IS NOT NULL THEN
                            COALESCE(
                                (
                                    SELECT string_agg(
                                        CASE
                                            WHEN jsonb_typeof(attr.value) = 'string' THEN
                                                attr.key || ' ' || REPLACE(attr.value::text, '\"', '')
                                            WHEN jsonb_typeof(attr.value) = 'number' THEN
                                                attr.key || ' ' || attr.value::text
                                            WHEN jsonb_typeof(attr.value) = 'boolean' THEN
                                                attr.key || ' ' || attr.value::text
                                            ELSE
                                                attr.key
                                        END,
                                        ' '
                                    )
                                    FROM jsonb_each(pa.attributes) AS attr(key, value)
                                    WHERE jsonb_typeof(pa.attributes) = 'object'
                                ), ''
                            )
                        ELSE
                            ''
                    END,
                    ' '
                )
                INTO attributes_text
                FROM ioa_product_attributes pa
                WHERE pa.product_id = NEW.id;

                -- Create the search vector including all searchable fields
                NEW.search_vector = to_tsvector('english',
                    coalesce(NEW.name, '') || ' ' ||
                    coalesce(NEW.title, '') || ' ' ||
                    coalesce(NEW.description, '') || ' ' ||
                    coalesce(NEW.pnum, '') || ' ' ||
                    coalesce(NEW.mf_pnum, '') || ' ' ||
                    coalesce(NEW.category_name, '') || ' ' ||
                    coalesce(NEW.brand_name, '') || ' ' ||
                    coalesce(NEW.manufacturer_name, '') || ' ' ||
                    coalesce(attributes_text, '')
                );

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        ");

        // Create the trigger
        DB::statement("
            CREATE TRIGGER ioa_ioa_products_search_vector_trigger
                BEFORE INSERT OR UPDATE ON ioa_products
                FOR EACH ROW
                EXECUTE FUNCTION update_product_search_vector();
        ");
    }

    /**
     * Step 3: Core performance indexes - most frequently used, create first
     */
    private function createCorePerformanceIndexes(): void
    {
        echo "Creating core performance indexes...\n";

        // 3.1: Core status filter index - used in virtually all product queries
        DB::statement('
            CREATE INDEX IF NOT EXISTS ioa_ioa_products_status_id_idx
            ON ioa_products (status_id)
        ');

        // 3.2: Combined status + name for most common sorting pattern
        DB::statement('
            CREATE INDEX IF NOT EXISTS ioa_ioa_products_status_name_idx
            ON ioa_products (status_id, name) WHERE status_id = 1
        ');

        // 3.3: Price filtering is now handled in separate product_prices table
        // This index is commented out as prices are normalized
        // DB::statement('
        //     CREATE INDEX IF NOT EXISTS ioa_products_active_price_idx
        //     ON ioa_products (price) WHERE status_id = 1
        // ');

        // 3.4: Created date sorting for "newest products"
        DB::statement('
            CREATE INDEX IF NOT EXISTS ioa_ioa_products_status_created_at_idx
            ON ioa_products (status_id, created_at DESC) WHERE status_id = 1
        ');
    }

    /**
     * Step 4: Full-text search indexes for comprehensive text matching
     */
    private function createFullTextSearchIndexes(): void
    {
        echo "Creating full-text search indexes...\n";

        // 4.1: Primary GIN index on search_vector for all full-text queries
        DB::statement('
            CREATE INDEX IF NOT EXISTS ioa_products_search_vector_gin_idx
            ON ioa_products USING GIN (search_vector)
        ');

        // 4.2: Partial GIN index for active products only (covers 90%+ of queries)
        DB::statement('
            CREATE INDEX IF NOT EXISTS ioa_products_active_search_vector_idx
            ON ioa_products USING GIN (search_vector)
            WHERE status_id = 1
        ');

        // 4.3: Specialized text search indexes on individual fields
        DB::statement('
            CREATE INDEX IF NOT EXISTS ioa_products_name_text_gin_idx
            ON ioa_products USING GIN (to_tsvector(\'english\', name))
            WHERE status_id = 1
        ');

        DB::statement('
            CREATE INDEX IF NOT EXISTS ioa_products_pnum_text_gin_idx
            ON ioa_products USING GIN (to_tsvector(\'english\', pnum))
            WHERE status_id = 1
        ');
    }

    /**
     * Step 5: Fuzzy matching indexes for typo tolerance and similarity search
     */
    private function createFuzzyMatchingIndexes(): void
    {
        echo "Creating fuzzy matching indexes...\n";

        // 5.1: Trigram index for product names (typo tolerance)
        DB::statement('
            CREATE INDEX IF NOT EXISTS ioa_products_name_trgm_gin_idx
            ON ioa_products USING GIN (name gin_trgm_ops)
            WHERE status_id = 1
        ');

        // 5.2: Active-only trigram search for names (duplicate prevention)
        DB::statement('
            CREATE INDEX IF NOT EXISTS ioa_products_active_name_trgm_idx
            ON ioa_products USING GIN (name gin_trgm_ops) WHERE status_id = 1
        ');

        // 5.3: Trigram index for pnum fuzzy matching
        DB::statement('
            CREATE INDEX IF NOT EXISTS ioa_products_active_pnum_trgm_idx
            ON ioa_products USING GIN (pnum gin_trgm_ops) WHERE status_id = 1
        ');
    }

    /**
     * Step 6: Sorting and filtering indexes for common UI patterns
     */
    private function createSortingAndFilteringIndexes(): void
    {
        echo "Creating sorting and filtering indexes...\n";

        // 6.1: Simple name sorting for active products
        DB::statement('
            CREATE INDEX IF NOT EXISTS ioa_products_active_name_idx
            ON ioa_products (name) WHERE status_id = 1
        ');

        // 6.2: Category filtering (EXISTS queries)
        DB::statement('
            CREATE INDEX IF NOT EXISTS ioa_products_status_category_idx
            ON ioa_products (status_id, category_id) WHERE status_id = 1
        ');

        // 6.3: Brand filtering is now handled via manufacturer_id (brands are tied to manufacturers)
        // Removed: brand_id doesn't exist in products table

        // 6.4: Manufacturer filtering (EXISTS queries)
        DB::statement('
            CREATE INDEX IF NOT EXISTS ioa_products_status_manufacturer_idx
            ON ioa_products (status_id, manufacturer_id) WHERE status_id = 1
        ');
    }

    /**
     * Step 7: Composite indexes for complex query patterns and multi-field filtering
     */
    private function createCompositeIndexes(): void
    {
        echo "Creating composite indexes...\n";

        // 7.1: Category-based filtering with name sorting
        DB::statement('
            CREATE INDEX IF NOT EXISTS ioa_products_status_category_name_idx
            ON ioa_products (status_id, category_id, name) WHERE status_id = 1
        ');

        DB::statement('
            CREATE INDEX IF NOT EXISTS ioa_products_active_category_name_idx
            ON ioa_products (category_id, name) WHERE status_id = 1
        ');

        // 7.2: Price filtering is now handled in separate product_prices table
        // Removed: price column doesn't exist in products table

        // 7.3: Brand-based filtering is now handled via manufacturer relationships
        // Removed: brand_id doesn't exist in products table

        // 7.5: Manufacturer-based filtering with name sorting
        DB::statement('
            CREATE INDEX IF NOT EXISTS ioa_products_status_manufacturer_name_idx
            ON ioa_products (status_id, manufacturer_id, name) WHERE status_id = 1
        ');

        // 7.6: Multi-dimensional filtering combinations (using manufacturer instead of brand)
        DB::statement('
            CREATE INDEX IF NOT EXISTS ioa_products_category_manufacturer_status_idx
            ON ioa_products (category_id, manufacturer_id, status_id) WHERE status_id = 1
        ');

        // 7.7: Price and stock are now handled in separate tables
        // Removed: price and stock_quantity columns don't exist in products table

        // 7.8: Complex search + filter scenarios (simplified for normalized schema)
        DB::statement('
            CREATE INDEX IF NOT EXISTS ioa_products_search_filters_idx
            ON ioa_products (category_id, manufacturer_id, status_id)
            WHERE status_id = 1
        ');
    }

    /**
     * Step 8: JSONB indexes for attributes and structured data queries
     */
    private function createJsonbIndexes(): void
    {
        echo "Creating JSONB indexes...\n";

        // Note: JSONB columns (attributes, images) are now handled in separate tables
        // product_attributes table has attributes_data JSONB column
        // product_images table handles image data
        // These indexes are no longer needed on the products table

        echo "JSONB indexes skipped - data normalized to separate tables.\n";
        echo "All indexes created successfully!\n";
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        echo "Dropping all product search indexes...\n";

        // Drop indexes in reverse order
        $indexes = [
            // JSONB indexes - removed as columns no longer exist
            // 'ioa_products_images_gin_idx',
            // 'ioa_products_attributes_gin_idx',

            // Composite indexes
            'ioa_products_search_filters_idx',
            'ioa_products_category_manufacturer_status_idx',
            'ioa_products_status_manufacturer_name_idx',
            'ioa_products_active_category_name_idx',
            'ioa_products_status_category_name_idx',

            // Sorting and filtering indexes
            'ioa_products_status_manufacturer_idx',
            'ioa_products_status_category_idx',
            'ioa_products_active_name_idx',

            // Fuzzy matching indexes
            'ioa_products_active_pnum_trgm_idx',
            'ioa_products_active_name_trgm_idx',
            'ioa_products_name_trgm_gin_idx',

            // Full-text search indexes
            'ioa_products_pnum_text_gin_idx',
            'ioa_products_name_text_gin_idx',
            'ioa_products_active_search_vector_idx',
            'ioa_products_search_vector_gin_idx',

            // Core performance indexes
            'ioa_products_status_created_at_idx',
            'ioa_products_active_price_idx',
            'ioa_products_status_name_idx',
            'ioa_products_status_idx',
        ];

        foreach ($indexes as $index) {
            DB::statement("DROP INDEX IF EXISTS {$index}");
        }

        // Drop trigger and function
        DB::statement('DROP TRIGGER IF EXISTS ioa_products_search_vector_trigger ON ioa_products');
        DB::statement('DROP FUNCTION IF EXISTS update_product_search_vector()');

        echo "All indexes dropped successfully!\n";
    }
};
