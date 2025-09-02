<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
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


        // Step 3: Core performance indexes (most critical)
        $this->createCorePerformanceIndexes();

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

        // 4.2: Trigram index on "name" column for fuzzy search (ILIKE, similarity, etc.)
        DB::statement('
            CREATE INDEX IF NOT EXISTS ioa_products_name_trgm_idx
            ON ioa_products USING GIN (name gin_trgm_ops)
        ');

        // 4.2: Trigram index on "name" column for fuzzy search (ILIKE, similarity, etc.)
        DB::statement('
            CREATE INDEX IF NOT EXISTS ioa_products_title_trgm_idx
            ON ioa_products USING GIN (title gin_trgm_ops)
        ');

        // 3.2: Combined status + name for most common sorting pattern
        DB::statement('
            CREATE INDEX IF NOT EXISTS ioa_ioa_products_status_name_idx
            ON ioa_products (status_id, name)
        ');

        // 3.2: Combined status + name for most common sorting pattern
        DB::statement('
            CREATE INDEX IF NOT EXISTS ioa_ioa_products_status_title_idx
            ON ioa_products (status_id, title)
        ');

        // 3.4: Created date sorting for "newest products"
        DB::statement('
            CREATE INDEX IF NOT EXISTS ioa_ioa_products_status_created_at_idx
            ON ioa_products (status_id, created_at DESC)
        ');




        // 4.1: Full-text search using GIN on precomputed tsvector column
        DB::statement('
            CREATE INDEX IF NOT EXISTS ioa_products_search_vector_gin_idx
            ON ioa_products USING GIN (search_vector)
        ');



        // 7.1: Category-based filtering with name sorting
        DB::statement('
            CREATE INDEX IF NOT EXISTS ioa_products_status_category_name_idx
            ON ioa_products (status_id, category_id, name)
        ');

        // 7.5: Manufacturer-based filtering with name sorting
        DB::statement('
            CREATE INDEX IF NOT EXISTS ioa_products_status_manufacturer_name_idx
            ON ioa_products (status_id, manufacturer_id, name)
        ');

        // 7.6: Multi-dimensional filtering combinations (using manufacturer instead of brand)
        DB::statement('
            CREATE INDEX IF NOT EXISTS ioa_products_category_manufacturer_status_idx
            ON ioa_products (category_id, manufacturer_id, status_id)
        ');

    }

};
