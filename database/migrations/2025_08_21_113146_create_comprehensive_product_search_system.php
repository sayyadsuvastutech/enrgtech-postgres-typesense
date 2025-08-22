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
     */
    public function up(): void
    {
        // Enable essential PostgreSQL extensions for advanced search features
        $this->enablePostgreSQLExtensions();

        // Create database functions for semantic search and text processing
        $this->createSemanticSearchFunctions();

        // Create the search vector trigger for automatic index maintenance
        $this->createSearchVectorTrigger();

        // Create performance-optimized indexes for various search scenarios
        $this->createFullTextSearchIndexes();
        $this->createFuzzyMatchingIndexes();
        $this->createPerformanceIndexes();
        $this->createCompositeIndexes();
        $this->createJsonbIndexes();

        // Update existing records to populate search vectors
        $this->updateExistingRecords();
    }

    /**
     * Enable PostgreSQL extensions required for advanced search functionality
     */
    private function enablePostgreSQLExtensions(): void
    {
        // pg_trgm: Provides trigram matching for similarity searches and fuzzy text matching
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm;');

        // unaccent: Removes accents from text for better international search support
        DB::statement('CREATE EXTENSION IF NOT EXISTS unaccent;');

        // fuzzystrmatch: Provides Levenshtein distance and other fuzzy string matching algorithms
        DB::statement('CREATE EXTENSION IF NOT EXISTS fuzzystrmatch;');
    }

    /**
     * Create semantic search and text processing functions
     */
    private function createSemanticSearchFunctions(): void
    {
        // Function to normalize electrical specifications and units for better search matching
        DB::statement("
            CREATE OR REPLACE FUNCTION normalize_electrical_specs(input_text text)
            RETURNS text AS $$
            DECLARE
                normalized_text text;
            BEGIN
                normalized_text := input_text;

                -- Normalize amperage units (A, amp, ampere, amps) -> searchable variants
                -- Example: '10A' becomes '10 amp ampere A' for better matching
                normalized_text := regexp_replace(normalized_text, '(\d+(\.\d+)?)\s*A(?!\w)', '\1 amp ampere A', 'gi');
                normalized_text := regexp_replace(normalized_text, '(\d+(\.\d+)?)\s*amps?', '\1 amp ampere A', 'gi');
                normalized_text := regexp_replace(normalized_text, '(\d+(\.\d+)?)\s*amperes?', '\1 amp ampere A', 'gi');

                -- Normalize voltage units (V, volt, volts) -> searchable variants
                normalized_text := regexp_replace(normalized_text, '(\d+(\.\d+)?)\s*V(?!\w)', '\1 volt volts V', 'gi');
                normalized_text := regexp_replace(normalized_text, '(\d+(\.\d+)?)\s*volts?', '\1 volt volts V', 'gi');

                -- Normalize wattage units (W, watt, watts) -> searchable variants
                normalized_text := regexp_replace(normalized_text, '(\d+(\.\d+)?)\s*W(?!\w)', '\1 watt watts W', 'gi');
                normalized_text := regexp_replace(normalized_text, '(\d+(\.\d+)?)\s*watts?', '\1 watt watts W', 'gi');

                -- Normalize resistance units (Ω, ohm, ohms) -> searchable variants
                normalized_text := regexp_replace(normalized_text, '(\d+(\.\d+)?)\s*[Ωω]', '\1 ohm ohms omega', 'gi');
                normalized_text := regexp_replace(normalized_text, '(\d+(\.\d+)?)\s*ohms?', '\1 ohm ohms omega', 'gi');

                -- Normalize frequency units (Hz, hertz) -> searchable variants
                normalized_text := regexp_replace(normalized_text, '(\d+(\.\d+)?)\s*Hz', '\1 hertz hz frequency', 'gi');
                normalized_text := regexp_replace(normalized_text, '(\d+(\.\d+)?)\s*hertz', '\1 hertz hz frequency', 'gi');

                -- Add electrical component synonyms for better semantic search
                normalized_text := regexp_replace(normalized_text, '\bfuse\b', 'fuse fuze circuit_breaker protection', 'gi');
                normalized_text := regexp_replace(normalized_text, '\bbreaker\b', 'breaker fuse circuit_breaker protection', 'gi');
                normalized_text := regexp_replace(normalized_text, '\btime.?delay\b', 'time_delay slow_blow delayed', 'gi');
                normalized_text := regexp_replace(normalized_text, '\bfast.?blow\b', 'fast_blow quick_acting immediate', 'gi');
                normalized_text := regexp_replace(normalized_text, '\belectrical\b', 'electrical electric electronic', 'gi');

                -- Normalize spacing around numbers and units for consistent tokenization
                normalized_text := regexp_replace(normalized_text, '(\d)([A-Za-z])', '\1 \2', 'g');
                normalized_text := regexp_replace(normalized_text, '([A-Za-z])(\d)', '\1 \2', 'g');

                -- Clean up multiple spaces and trim
                normalized_text := regexp_replace(normalized_text, '\s+', ' ', 'g');
                normalized_text := trim(normalized_text);

                RETURN normalized_text;
            END;
            $$ LANGUAGE plpgsql IMMUTABLE;
        ");

        // Function to determine if a search term matches a product using multiple strategies
        DB::statement("
            CREATE OR REPLACE FUNCTION semantic_search_match(
                search_term text,
                product_name text,
                product_sku text,
                product_description text DEFAULT NULL,
                search_vector tsvector DEFAULT NULL
            ) RETURNS boolean AS $$
            DECLARE
                term_clean text;
                exact_match boolean DEFAULT false;
                fuzzy_match boolean DEFAULT false;
                fulltext_match boolean DEFAULT false;
            BEGIN
                term_clean := trim(lower(search_term));

                -- Return false for empty search terms
                IF term_clean = '' THEN
                    RETURN false;
                END IF;

                -- Exact substring matches (highest confidence)
                -- Checks for exact occurrences within product fields
                exact_match := (
                    lower(product_name) LIKE '%' || term_clean || '%' OR
                    lower(product_sku) LIKE '%' || term_clean || '%' OR
                    (product_description IS NOT NULL AND lower(product_description) LIKE '%' || term_clean || '%')
                );

                -- Full-text search using PostgreSQL's built-in text search
                -- Uses websearch_to_tsquery for Google-like query syntax support
                IF search_vector IS NOT NULL THEN
                    fulltext_match := search_vector @@ websearch_to_tsquery('english', search_term);
                END IF;

                -- Fuzzy matching using trigram similarity for typo tolerance
                -- Similarity thresholds: name=0.3, sku=0.3, description=0.2
                fuzzy_match := (
                    similarity(product_name, search_term) > 0.3 OR
                    similarity(product_sku, search_term) > 0.3 OR
                    (product_description IS NOT NULL AND similarity(product_description, search_term) > 0.2)
                );

                -- Product matches if ANY strategy succeeds
                RETURN exact_match OR fulltext_match OR fuzzy_match;
            END;
            $$ LANGUAGE plpgsql IMMUTABLE;
        ");

        // Function to calculate semantic search relevance score using multiple factors
        DB::statement("
            CREATE OR REPLACE FUNCTION semantic_search_score(
                search_term text,
                product_name text,
                product_sku text,
                product_description text DEFAULT NULL,
                category_name text DEFAULT NULL,
                brand_name text DEFAULT NULL,
                search_vector tsvector DEFAULT NULL
            ) RETURNS float AS $$
            DECLARE
                total_score float DEFAULT 0.0;
                term_clean text;
                exact_score float DEFAULT 0.0;      -- 15-20 points for exact matches
                fuzzy_score float DEFAULT 0.0;      -- 5-10 points for similarity
                fulltext_score float DEFAULT 0.0;   -- 10-15 points for full-text relevance
                category_score float DEFAULT 0.0;   -- 3-6 points for category match
                brand_score float DEFAULT 0.0;      -- 3-6 points for brand match
                description_score float DEFAULT 0.0; -- 1-3 points for description match
                name_bonus float DEFAULT 0.0;       -- Bonus for multi-word searches
                sku_bonus float DEFAULT 0.0;        -- Bonus for electrical spec patterns
            BEGIN
                term_clean := trim(lower(search_term));

                -- Return 0 score for empty search terms
                IF term_clean = '' THEN
                    RETURN 0.0;
                END IF;

                -- Exact match scoring (highest priority: 15-20 points)
                IF lower(product_name) = term_clean THEN
                    exact_score := 20.0;  -- Perfect product name match
                ELSIF lower(product_sku) = term_clean THEN
                    exact_score := 18.0;  -- Perfect SKU match
                ELSIF lower(product_name) LIKE '%' || term_clean || '%' THEN
                    exact_score := 15.0;  -- Partial product name match
                ELSIF lower(product_sku) LIKE '%' || term_clean || '%' THEN
                    exact_score := 15.0;  -- Partial SKU match
                END IF;

                -- Full-text relevance scoring (10-15 points)
                -- Uses PostgreSQL's ts_rank_cd with normalization flags
                IF search_vector IS NOT NULL THEN
                    fulltext_score := ts_rank_cd(search_vector, websearch_to_tsquery('english', search_term), 32) * 15.0;
                END IF;

                -- Fuzzy similarity scoring (5-10 points)
                -- Higher weight for name and SKU matches
                fuzzy_score := GREATEST(
                    similarity(product_name, search_term) * 10.0,   -- Product name similarity
                    similarity(product_sku, search_term) * 8.0,     -- SKU similarity
                    COALESCE(similarity(product_description, search_term), 0.0) * 6.0  -- Description similarity
                );

                -- Category match scoring (3-6 points)
                IF category_name IS NOT NULL THEN
                    IF lower(category_name) LIKE '%' || term_clean || '%' THEN
                        category_score := 6.0;  -- Exact category match
                    ELSIF similarity(category_name, search_term) > 0.4 THEN
                        category_score := similarity(category_name, search_term) * 4.0;  -- Fuzzy category match
                    END IF;
                END IF;

                -- Brand match scoring (3-6 points)
                IF brand_name IS NOT NULL THEN
                    IF lower(brand_name) LIKE '%' || term_clean || '%' THEN
                        brand_score := 6.0;  -- Exact brand match
                    ELSIF similarity(brand_name, search_term) > 0.4 THEN
                        brand_score := similarity(brand_name, search_term) * 4.0;  -- Fuzzy brand match
                    END IF;
                END IF;

                -- Description match scoring (1-3 points)
                IF product_description IS NOT NULL THEN
                    IF lower(product_description) LIKE '%' || term_clean || '%' THEN
                        description_score := 3.0;  -- Exact description match
                    ELSIF similarity(product_description, search_term) > 0.3 THEN
                        description_score := similarity(product_description, search_term) * 2.0;  -- Fuzzy description match
                    END IF;
                END IF;

                -- Multi-word search bonus (0.5 points per additional word)
                -- Rewards comprehensive searches
                IF strpos(search_term, ' ') > 0 THEN
                    name_bonus := (length(search_term) - length(replace(search_term, ' ', ''))) * 0.5;
                END IF;

                -- Electrical specification pattern bonus (2 points)
                -- Rewards searches for electrical specs like '10A', '125V'
                IF product_sku ~ '\d+[A-Z]+' AND search_term ~ '\d+\s*[A-Z]+' THEN
                    sku_bonus := 2.0;
                END IF;

                -- Calculate total relevance score
                total_score := exact_score + fulltext_score + fuzzy_score + category_score +
                              brand_score + description_score + name_bonus + sku_bonus;

                RETURN GREATEST(total_score, 0.0);
            END;
            $$ LANGUAGE plpgsql IMMUTABLE;
        ");

        // Function to parse electrical specifications from text
        DB::statement("
            CREATE OR REPLACE FUNCTION parse_electrical_specs(input_text text)
            RETURNS table(value numeric, unit text) AS $$
            BEGIN
                -- Extract numeric values with electrical units using regex
                -- Matches patterns like: 10A, 125V, 15W, 2.5Ω, 60Hz
                RETURN QUERY
                SELECT
                    (regexp_matches(input_text, '(\d+(?:\.\d+)?)\s*([AaVvWwΩω]|[Aa]mp|[Vv]olt|[Ww]att|[Oo]hm)', 'gi'))[1]::numeric as value,
                    lower((regexp_matches(input_text, '(\d+(?:\.\d+)?)\s*([AaVvWwΩω]|[Aa]mp|[Vv]olt|[Ww]att|[Oo]hm)', 'gi'))[2]) as unit;
            END;
            $$ LANGUAGE plpgsql IMMUTABLE;
        ");

        // Function to generate search suggestions with fuzzy matching
        DB::statement("
            CREATE OR REPLACE FUNCTION get_search_suggestions(
                search_term text,
                suggestion_limit integer DEFAULT 5
            ) RETURNS table(suggestion text, score float, type text) AS $$
            BEGIN
                -- Return suggested search terms based on product data
                -- Combines product names, SKUs, and category names with similarity scoring
                RETURN QUERY
                WITH name_suggestions AS (
                    SELECT DISTINCT
                        name as suggestion,
                        similarity(name, search_term) as score,
                        'product' as type
                    FROM products
                    WHERE status = 'active'
                    AND (similarity(name, search_term) > 0.2 OR name ILIKE '%' || search_term || '%')
                    ORDER BY similarity(name, search_term) DESC
                    LIMIT suggestion_limit
                ),
                sku_suggestions AS (
                    SELECT DISTINCT
                        sku as suggestion,
                        similarity(sku, search_term) as score,
                        'sku' as type
                    FROM products
                    WHERE status = 'active'
                    AND (similarity(sku, search_term) > 0.2 OR sku ILIKE '%' || search_term || '%')
                    ORDER BY similarity(sku, search_term) DESC
                    LIMIT 2
                ),
                category_suggestions AS (
                    SELECT DISTINCT
                        category_name as suggestion,
                        similarity(category_name, search_term) as score,
                        'category' as type
                    FROM products
                    WHERE status = 'active'
                    AND (similarity(category_name, search_term) > 0.3 OR category_name ILIKE '%' || search_term || '%')
                    ORDER BY similarity(category_name, search_term) DESC
                    LIMIT 2
                )
                SELECT * FROM name_suggestions
                UNION ALL
                SELECT * FROM sku_suggestions
                UNION ALL
                SELECT * FROM category_suggestions
                ORDER BY score DESC
                LIMIT suggestion_limit;
            END;
            $$ LANGUAGE plpgsql;
        ");
    }

    /**
     * Create trigger function and trigger to automatically maintain search vectors
     */
    private function createSearchVectorTrigger(): void
    {
        // Drop existing trigger and function if they exist
        DB::statement("DROP TRIGGER IF EXISTS products_search_vector_trigger ON products;");
        DB::statement("DROP FUNCTION IF EXISTS update_product_search_vector();");

        // Create enhanced trigger function with semantic processing
        DB::statement("
            CREATE OR REPLACE FUNCTION update_product_search_vector()
            RETURNS TRIGGER AS $$
            DECLARE
                processed_text text;
                attributes_text text DEFAULT '';
            BEGIN
                -- Extract searchable text from JSONB attributes column
                -- Converts key-value pairs into searchable text
                IF NEW.attributes IS NOT NULL AND jsonb_typeof(NEW.attributes) = 'object' THEN
                    SELECT string_agg(value::text, ' ')
                    INTO attributes_text
                    FROM jsonb_each_text(NEW.attributes)
                    WHERE value::text IS NOT NULL AND value::text != '';

                    attributes_text := COALESCE(attributes_text, '');
                END IF;

                -- Combine all searchable text fields
                -- Includes product details and denormalized relationship names
                processed_text :=
                    COALESCE(NEW.name, '') || ' ' ||
                    COALESCE(NEW.description, '') || ' ' ||
                    COALESCE(NEW.sku, '') || ' ' ||
                    COALESCE(NEW.category_name, '') || ' ' ||
                    COALESCE(NEW.brand_name, '') || ' ' ||
                    COALESCE(NEW.manufacturer_name, '') || ' ' ||
                    attributes_text;

                -- Apply electrical specification normalization
                -- Converts '10A' to '10 amp ampere A' for better search matching
                processed_text := normalize_electrical_specs(processed_text);

                -- Generate the full-text search vector using English language stemming
                NEW.search_vector = to_tsvector('english', processed_text);

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        ");

//        -- Create trigger that fires on INSERT and UPDATE operations
        DB::statement("
            CREATE TRIGGER products_search_vector_trigger
                BEFORE INSERT OR UPDATE ON products
                FOR EACH ROW
                EXECUTE FUNCTION update_product_search_vector();
        ");
    }

    /**
     * Create full-text search indexes for comprehensive text matching
     */
    private function createFullTextSearchIndexes(): void
    {
        // Primary GIN index on search_vector for all full-text queries
        // Used by: @@ operator, ts_rank functions, semantic search
        DB::statement('
            CREATE INDEX products_search_vector_gin_idx
            ON products USING GIN (search_vector)
        ');

        // Partial GIN index for active products only (covers 90%+ of queries)
        // Smaller index size = faster queries for most common use case
        DB::statement('
            CREATE INDEX products_active_search_vector_idx
            ON products USING GIN (search_vector)
            WHERE status = \'active\'
        ');

        // Specialized text search indexes on individual fields for precise matching
        // Used when searching specific fields rather than general search
        DB::statement('
            CREATE INDEX products_name_text_gin_idx
            ON products USING GIN (to_tsvector(\'english\', name))
            WHERE status = \'active\'
        ');

        DB::statement('
            CREATE INDEX products_sku_text_gin_idx
            ON products USING GIN (to_tsvector(\'english\', sku))
            WHERE status = \'active\'
        ');
    }

    /**
     * Create fuzzy matching indexes for typo tolerance and similarity search
     */
    private function createFuzzyMatchingIndexes(): void
    {
        // Trigram GIN indexes for similarity() function support
        // Enables fuzzy matching with configurable similarity thresholds

        // Product name trigram index - primary search field
        DB::statement('
            CREATE INDEX products_name_trgm_gin_idx
            ON products USING GIN (name gin_trgm_ops)
            WHERE status = \'active\'
        ');

        // SKU trigram index - important for electrical part numbers
        DB::statement('
            CREATE INDEX products_sku_trgm_gin_idx
            ON products USING GIN (sku gin_trgm_ops)
            WHERE status = \'active\'
        ');

        // Description trigram index - for detailed product information
        DB::statement('
            CREATE INDEX products_description_trgm_gin_idx
            ON products USING GIN (description gin_trgm_ops)
            WHERE status = \'active\' AND description IS NOT NULL
        ');

        // Denormalized field trigram indexes for relationship-free searches
        // Avoids JOINs by searching pre-stored category/brand names
        DB::statement('
            CREATE INDEX products_category_name_trgm_idx
            ON products USING GIN (category_name gin_trgm_ops)
            WHERE status = \'active\'
        ');

        DB::statement('
            CREATE INDEX products_brand_name_trgm_idx
            ON products USING GIN (brand_name gin_trgm_ops)
            WHERE status = \'active\'
        ');
    }

    /**
     * Create performance indexes for filtering, sorting, and common queries
     */
    private function createPerformanceIndexes(): void
    {
        // Core status filter index - used in virtually all product queries
        // Simple B-tree index for equality checks
        DB::statement('
            CREATE INDEX products_status_idx
            ON products (status)
        ');

        // Price filtering indexes for e-commerce price range queries
        DB::statement('
            CREATE INDEX products_active_price_idx
            ON products (price)
            WHERE status = \'active\'
        ');

        // Stock availability index for in-stock filtering
        // Partial index only for products with stock > 0
        DB::statement('
            CREATE INDEX products_active_stock_idx
            ON products (stock_quantity)
            WHERE status = \'active\' AND stock_quantity > 0
        ');

        // Sorting indexes for common sort orders
        // Product name alphabetical sorting
        DB::statement('
            CREATE INDEX products_active_name_sort_idx
            ON products (name)
            WHERE status = \'active\'
        ');

        // Creation date sorting (newest first) - common for product listings
        DB::statement('
            CREATE INDEX products_active_created_sort_idx
            ON products (created_at DESC)
            WHERE status = \'active\'
        ');
    }

    /**
     * Create composite indexes for complex query patterns and multi-field filtering
     */
    private function createCompositeIndexes(): void
    {
        // Category-based filtering with sorting combinations
        // Used for: category pages with name/price sorting
        DB::statement('
            CREATE INDEX products_active_category_name_idx
            ON products (category_id, name)
            WHERE status = \'active\'
        ');

        DB::statement('
            CREATE INDEX products_active_category_price_idx
            ON products (category_id, price)
            WHERE status = \'active\'
        ');

        // Brand-based filtering with sorting combinations
        // Used for: brand pages with name/price sorting
        DB::statement('
            CREATE INDEX products_active_brand_name_idx
            ON products (brand_id, name)
            WHERE status = \'active\'
        ');

        DB::statement('
            CREATE INDEX products_active_brand_price_idx
            ON products (brand_id, price)
            WHERE status = \'active\'
        ');

        // Multi-dimensional filtering combinations
        // Used for: category + brand filtering (common e-commerce pattern)
        DB::statement('
            CREATE INDEX products_category_brand_status_idx
            ON products (category_id, brand_id, status)
            WHERE status = \'active\'
        ');

        // Price range with category filtering
        // Used for: price range sliders within categories
        DB::statement('
            CREATE INDEX products_active_price_category_idx
            ON products (price, category_id)
            WHERE status = \'active\'
        ');

        // Complex search + filter scenarios
        // Separate B-tree index for common filtering combinations after search
        DB::statement('
            CREATE INDEX products_search_filters_idx
            ON products (category_id, price, brand_id, stock_quantity)
            WHERE status = \'active\'
        ');
    }

    /**
     * Create JSONB indexes for attributes and structured data queries
     */
    private function createJsonbIndexes(): void
    {
        // GIN index for JSONB attributes column
        // Supports: @>, ?, ?&, ?| operators for JSON queries
        // Used for: filtering by product specifications and attributes
        DB::statement('
            CREATE INDEX products_attributes_gin_idx
            ON products USING GIN (attributes)
            WHERE status = \'active\' AND attributes IS NOT NULL
        ');

        // GIN index for JSONB images column (if used for image metadata searches)
        // Supports: searching image metadata, alt text, etc.
        DB::statement('
            CREATE INDEX products_images_gin_idx
            ON products USING GIN (images)
            WHERE status = \'active\' AND images IS NOT NULL
        ');
    }

    /**
     * Update existing product records to populate search vectors
     */
    private function updateExistingRecords(): void
    {
        // Trigger the search vector update for all existing products
        // This ensures that products created before this migration have proper search vectors
        DB::statement("UPDATE products SET updated_at = updated_at WHERE id IS NOT NULL;");
    }

    /**
     * Reverse the migrations by dropping all created database objects
     */
    public function down(): void
    {
        // Drop all indexes (order matters for dependencies)
        $this->dropAllIndexes();

        // Drop trigger and functions
        $this->dropTriggerAndFunctions();
    }

    /**
     * Drop all created indexes
     */
    private function dropAllIndexes(): void
    {
        $indexes = [
            // Full-text search indexes
            'products_search_vector_gin_idx',
            'products_active_search_vector_idx',
            'products_name_text_gin_idx',
            'products_sku_text_gin_idx',

            // Fuzzy matching indexes
            'products_name_trgm_gin_idx',
            'products_sku_trgm_gin_idx',
            'products_description_trgm_gin_idx',
            'products_category_name_trgm_idx',
            'products_brand_name_trgm_idx',

            // Performance indexes
            'products_status_idx',
            'products_active_price_idx',
            'products_active_stock_idx',
            'products_active_name_sort_idx',
            'products_active_created_sort_idx',

            // Composite indexes
            'products_active_category_name_idx',
            'products_active_category_price_idx',
            'products_active_brand_name_idx',
            'products_active_brand_price_idx',
            'products_category_brand_status_idx',
            'products_active_price_category_idx',
            'products_search_filters_idx',

            // JSONB indexes
            'products_attributes_gin_idx',
            'products_images_gin_idx'
        ];

        foreach ($indexes as $index) {
            DB::statement("DROP INDEX CONCURRENTLY IF EXISTS {$index}");
        }
    }

    /**
     * Drop trigger and all custom functions
     */
    private function dropTriggerAndFunctions(): void
    {
        // Drop trigger first
        DB::statement("DROP TRIGGER IF EXISTS products_search_vector_trigger ON products");

        // Drop all custom functions
        DB::statement("DROP FUNCTION IF EXISTS update_product_search_vector()");
        DB::statement("DROP FUNCTION IF EXISTS normalize_electrical_specs(text)");
        DB::statement("DROP FUNCTION IF EXISTS semantic_search_match(text, text, text, text, tsvector)");
        DB::statement("DROP FUNCTION IF EXISTS semantic_search_score(text, text, text, text, text, text, tsvector)");
        DB::statement("DROP FUNCTION IF EXISTS parse_electrical_specs(text)");
        DB::statement("DROP FUNCTION IF EXISTS get_search_suggestions(text, integer)");
    }
};
