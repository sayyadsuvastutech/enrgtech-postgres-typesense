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
                -- Extract attributes keys and values from JSONB (only if it's an object, not array or null)
                IF NEW.attributes IS NOT NULL AND jsonb_typeof(NEW.attributes) = 'object' THEN
                    SELECT string_agg(
                        CASE
                            WHEN jsonb_typeof(value) = 'string' THEN
                                key || ' ' || REPLACE(value::text, '\"', '')
                            WHEN jsonb_typeof(value) = 'number' THEN
                                key || ' ' || value::text
                            WHEN jsonb_typeof(value) = 'boolean' THEN
                                key || ' ' || value::text
                            ELSE
                                key
                        END,
                        ' '
                    )
                    INTO attributes_text
                    FROM jsonb_each(NEW.attributes);
                END IF;

                -- Create the search vector including all searchable fields
                NEW.search_vector = to_tsvector('english',
                    coalesce(NEW.name, '') || ' ' ||
                    coalesce(NEW.description, '') || ' ' ||
                    coalesce(NEW.sku, '') || ' ' ||
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
            CREATE TRIGGER products_search_vector_trigger
                BEFORE INSERT OR UPDATE ON products
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
            CREATE INDEX IF NOT EXISTS products_status_idx
            ON products (status)
        ');

        // 3.2: Combined status + name for most common sorting pattern
        DB::statement('
            CREATE INDEX IF NOT EXISTS products_status_name_idx
            ON products (status, name) WHERE status = \'active\'
        ');

        // 3.3: Price filtering for e-commerce price range queries
        DB::statement('
            CREATE INDEX IF NOT EXISTS products_active_price_idx
            ON products (price) WHERE status = \'active\'
        ');

        // 3.4: Created date sorting for "newest products"
        DB::statement('
            CREATE INDEX IF NOT EXISTS products_status_created_at_idx
            ON products (status, created_at DESC) WHERE status = \'active\'
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
            CREATE INDEX IF NOT EXISTS products_search_vector_gin_idx
            ON products USING GIN (search_vector)
        ');

        // 4.2: Partial GIN index for active products only (covers 90%+ of queries)
        DB::statement('
            CREATE INDEX IF NOT EXISTS products_active_search_vector_idx
            ON products USING GIN (search_vector)
            WHERE status = \'active\'
        ');

        // 4.3: Specialized text search indexes on individual fields
        DB::statement('
            CREATE INDEX IF NOT EXISTS products_name_text_gin_idx
            ON products USING GIN (to_tsvector(\'english\', name))
            WHERE status = \'active\'
        ');

        DB::statement('
            CREATE INDEX IF NOT EXISTS products_sku_text_gin_idx
            ON products USING GIN (to_tsvector(\'english\', sku))
            WHERE status = \'active\'
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
            CREATE INDEX IF NOT EXISTS products_name_trgm_gin_idx
            ON products USING GIN (name gin_trgm_ops)
            WHERE status = \'active\'
        ');

        // 5.2: Active-only trigram search for names (duplicate prevention)
        DB::statement('
            CREATE INDEX IF NOT EXISTS products_active_name_trgm_idx
            ON products USING GIN (name gin_trgm_ops) WHERE status = \'active\'
        ');

        // 5.3: Trigram index for SKU fuzzy matching
        DB::statement('
            CREATE INDEX IF NOT EXISTS products_active_sku_trgm_idx
            ON products USING GIN (sku gin_trgm_ops) WHERE status = \'active\'
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
            CREATE INDEX IF NOT EXISTS products_active_name_idx
            ON products (name) WHERE status = \'active\'
        ');

        // 6.2: Category filtering (EXISTS queries)
        DB::statement('
            CREATE INDEX IF NOT EXISTS products_status_category_idx
            ON products (status, category_id) WHERE status = \'active\'
        ');

        // 6.3: Brand filtering (EXISTS queries)
        DB::statement('
            CREATE INDEX IF NOT EXISTS products_status_brand_idx
            ON products (status, brand_id) WHERE status = \'active\'
        ');

        // 6.4: Manufacturer filtering (EXISTS queries)
        DB::statement('
            CREATE INDEX IF NOT EXISTS products_status_manufacturer_idx
            ON products (status, manufacturer_id) WHERE status = \'active\'
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
            CREATE INDEX IF NOT EXISTS products_status_category_name_idx
            ON products (status, category_id, name) WHERE status = \'active\'
        ');

        DB::statement('
            CREATE INDEX IF NOT EXISTS products_active_category_name_idx
            ON products (category_id, name) WHERE status = \'active\'
        ');

        // 7.2: Category-based filtering with price sorting
        DB::statement('
            CREATE INDEX IF NOT EXISTS products_active_category_price_idx
            ON products (category_id, price) WHERE status = \'active\'
        ');

        // 7.3: Brand-based filtering with name sorting
        DB::statement('
            CREATE INDEX IF NOT EXISTS products_status_brand_name_idx
            ON products (status, brand_id, name) WHERE status = \'active\'
        ');

        DB::statement('
            CREATE INDEX IF NOT EXISTS products_active_brand_name_idx
            ON products (brand_id, name) WHERE status = \'active\'
        ');

        // 7.4: Brand-based filtering with price sorting
        DB::statement('
            CREATE INDEX IF NOT EXISTS products_active_brand_price_idx
            ON products (brand_id, price) WHERE status = \'active\'
        ');

        // 7.5: Manufacturer-based filtering with name sorting
        DB::statement('
            CREATE INDEX IF NOT EXISTS products_status_manufacturer_name_idx
            ON products (status, manufacturer_id, name) WHERE status = \'active\'
        ');

        // 7.6: Multi-dimensional filtering combinations
        DB::statement('
            CREATE INDEX IF NOT EXISTS products_category_brand_status_idx
            ON products (category_id, brand_id, status) WHERE status = \'active\'
        ');

        // 7.7: Price range with category filtering
        DB::statement('
            CREATE INDEX IF NOT EXISTS products_active_price_category_idx
            ON products (price, category_id) WHERE status = \'active\'
        ');

        // 7.8: Complex search + filter scenarios
        DB::statement('
            CREATE INDEX IF NOT EXISTS products_search_filters_idx
            ON products (category_id, price, brand_id, stock_quantity)
            WHERE status = \'active\'
        ');
    }

    /**
     * Step 8: JSONB indexes for attributes and structured data queries
     */
    private function createJsonbIndexes(): void
    {
        echo "Creating JSONB indexes...\n";

        // 8.1: GIN index for JSONB attributes column
        DB::statement('
            CREATE INDEX IF NOT EXISTS products_attributes_gin_idx
            ON products USING GIN (attributes)
            WHERE status = \'active\' AND attributes IS NOT NULL
        ');

        // 8.2: GIN index for JSONB images column
        DB::statement('
            CREATE INDEX IF NOT EXISTS products_images_gin_idx
            ON products USING GIN (images)
            WHERE status = \'active\' AND images IS NOT NULL
        ');

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
            // JSONB indexes
            'products_images_gin_idx',
            'products_attributes_gin_idx',

            // Composite indexes
            'products_search_filters_idx',
            'products_active_price_category_idx',
            'products_category_brand_status_idx',
            'products_status_manufacturer_name_idx',
            'products_active_brand_price_idx',
            'products_active_brand_name_idx',
            'products_status_brand_name_idx',
            'products_active_category_price_idx',
            'products_active_category_name_idx',
            'products_status_category_name_idx',

            // Sorting and filtering indexes
            'products_status_manufacturer_idx',
            'products_status_brand_idx',
            'products_status_category_idx',
            'products_active_name_idx',

            // Fuzzy matching indexes
            'products_active_sku_trgm_idx',
            'products_active_name_trgm_idx',
            'products_name_trgm_gin_idx',

            // Full-text search indexes
            'products_sku_text_gin_idx',
            'products_name_text_gin_idx',
            'products_active_search_vector_idx',
            'products_search_vector_gin_idx',

            // Core performance indexes
            'products_status_created_at_idx',
            'products_active_price_idx',
            'products_status_name_idx',
            'products_status_idx',
        ];

        foreach ($indexes as $index) {
            DB::statement("DROP INDEX IF EXISTS {$index}");
        }

        // Drop trigger and function
        DB::statement('DROP TRIGGER IF EXISTS products_search_vector_trigger ON products');
        DB::statement('DROP FUNCTION IF EXISTS update_product_search_vector()');

        echo "All indexes dropped successfully!\n";
    }
};
