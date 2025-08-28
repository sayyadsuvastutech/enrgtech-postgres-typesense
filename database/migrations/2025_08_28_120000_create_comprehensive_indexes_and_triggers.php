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
        // Enable required extensions for fuzzy search and full-text search
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
        DB::statement('CREATE EXTENSION IF NOT EXISTS unaccent');

        // ========================================
        // PRODUCTS TABLE COMPREHENSIVE INDEXES
        // ========================================

        // Drop existing indexes if they exist
        $productIndexes = [
            'idx_ioa_products_pnum',
            'idx_ioa_products_mf_pnum',
            'idx_ioa_products_mf_pnum_slug',
            'idx_ioa_products_category_id',
            'idx_ioa_products_manufacturer_id',
            'idx_ioa_products_brand_id',
            'idx_ioa_products_status_id',
            'idx_ioa_products_name',
            'idx_ioa_products_title',
            'idx_ioa_products_name_trgm',
            'idx_ioa_products_title_trgm',
            'idx_ioa_products_search_vector',
            'idx_ioa_products_search_vector_gin',
            'idx_ioa_products_created_at',
            'idx_ioa_products_updated_at',
            'idx_ioa_products_status_id_active',
            'idx_ioa_products_category_status',
            'idx_ioa_products_manufacturer_status',
            'idx_ioa_products_brand_status',
            'idx_ioa_products_category_manufacturer',
            'idx_ioa_products_category_brand',
            'idx_ioa_products_manufacturer_brand',
            'idx_ioa_products_pushed',
            'idx_ioa_products_rohs_compliant',
            'idx_ioa_products_is_updated',
            'idx_ioa_products_search_composite',
            'idx_ioa_products_filter_composite',
            'idx_ioa_products_category_name',
            'idx_ioa_products_manufacturer_name',
            'idx_ioa_products_brand_name'
        ];

        foreach ($productIndexes as $index) {
            DB::statement("DROP INDEX IF EXISTS {$index}");
        }

        // Core field indexes
        DB::statement('CREATE INDEX idx_ioa_products_pnum ON ioa_products(pnum)');
        DB::statement('CREATE INDEX idx_ioa_products_mf_pnum ON ioa_products(mf_pnum) WHERE mf_pnum IS NOT NULL');
        DB::statement('CREATE INDEX idx_ioa_products_mf_pnum_slug ON ioa_products(mf_pnum_slug) WHERE mf_pnum_slug IS NOT NULL');

        // Foreign key indexes
        DB::statement('CREATE INDEX idx_ioa_products_category_id ON ioa_products(category_id) WHERE category_id IS NOT NULL');
        DB::statement('CREATE INDEX idx_ioa_products_manufacturer_id ON ioa_products(manufacturer_id) WHERE manufacturer_id IS NOT NULL');
        DB::statement('CREATE INDEX idx_ioa_products_brand_id ON ioa_products(brand_id) WHERE brand_id IS NOT NULL');
        DB::statement('CREATE INDEX idx_ioa_products_status_id ON ioa_products(status_id) WHERE status_id IS NOT NULL');

        // Text search indexes
        DB::statement('CREATE INDEX idx_ioa_products_name ON ioa_products(name) WHERE name IS NOT NULL');
        DB::statement('CREATE INDEX idx_ioa_products_title ON ioa_products(title) WHERE title IS NOT NULL');

        // Trigram indexes for fuzzy search
        DB::statement('CREATE INDEX idx_ioa_products_name_trgm ON ioa_products USING gin(name gin_trgm_ops) WHERE name IS NOT NULL');
        DB::statement('CREATE INDEX idx_ioa_products_title_trgm ON ioa_products USING gin(title gin_trgm_ops) WHERE title IS NOT NULL');

        // Full-text search indexes
        DB::statement('CREATE INDEX idx_ioa_products_search_vector ON ioa_products USING btree(search_vector) WHERE search_vector IS NOT NULL');
        DB::statement('CREATE INDEX idx_ioa_products_search_vector_gin ON ioa_products USING gin(search_vector) WHERE search_vector IS NOT NULL');

        // Timestamp indexes
        DB::statement('CREATE INDEX idx_ioa_products_created_at ON ioa_products(created_at)');
        DB::statement('CREATE INDEX idx_ioa_products_updated_at ON ioa_products(updated_at)');

        // Status-based indexes (using existing status_id field)
        DB::statement('CREATE INDEX idx_ioa_products_status_id_active ON ioa_products(status_id) WHERE status_id IS NOT NULL');

        // Composite indexes for common queries
        DB::statement('CREATE INDEX idx_ioa_products_category_status ON ioa_products(category_id, status_id) WHERE category_id IS NOT NULL AND status_id IS NOT NULL');
        DB::statement('CREATE INDEX idx_ioa_products_manufacturer_status ON ioa_products(manufacturer_id, status_id) WHERE manufacturer_id IS NOT NULL AND status_id IS NOT NULL');
        DB::statement('CREATE INDEX idx_ioa_products_brand_status ON ioa_products(brand_id, status_id) WHERE brand_id IS NOT NULL AND status_id IS NOT NULL');

        // Multi-field composite indexes
        DB::statement('CREATE INDEX idx_ioa_products_category_manufacturer ON ioa_products(category_id, manufacturer_id) WHERE category_id IS NOT NULL AND manufacturer_id IS NOT NULL');
        DB::statement('CREATE INDEX idx_ioa_products_category_brand ON ioa_products(category_id, brand_id) WHERE category_id IS NOT NULL AND brand_id IS NOT NULL');
        DB::statement('CREATE INDEX idx_ioa_products_manufacturer_brand ON ioa_products(manufacturer_id, brand_id) WHERE manufacturer_id IS NOT NULL AND brand_id IS NOT NULL');

        // Boolean field indexes (using existing boolean fields)
        DB::statement('CREATE INDEX idx_ioa_products_pushed ON ioa_products(pushed) WHERE pushed = true');
        DB::statement('CREATE INDEX idx_ioa_products_rohs_compliant ON ioa_products(is_rohs_compliant) WHERE is_rohs_compliant = true');
        DB::statement('CREATE INDEX idx_ioa_products_is_updated ON ioa_products(is_updated) WHERE is_updated = true');

        // Complex composite indexes for advanced filtering
        DB::statement('CREATE INDEX idx_ioa_products_search_composite ON ioa_products(category_id, manufacturer_id, brand_id, status_id) WHERE category_id IS NOT NULL AND manufacturer_id IS NOT NULL AND brand_id IS NOT NULL AND status_id IS NOT NULL');
        DB::statement('CREATE INDEX idx_ioa_products_filter_composite ON ioa_products(category_id, manufacturer_id, status_id, updated_at) WHERE category_id IS NOT NULL AND manufacturer_id IS NOT NULL AND status_id IS NOT NULL');

        // Denormalized field indexes
        DB::statement('CREATE INDEX idx_ioa_products_category_name ON ioa_products(category_name) WHERE category_name IS NOT NULL');
        DB::statement('CREATE INDEX idx_ioa_products_manufacturer_name ON ioa_products(manufacturer_name) WHERE manufacturer_name IS NOT NULL');
        DB::statement('CREATE INDEX idx_ioa_products_brand_name ON ioa_products(brand_name) WHERE brand_name IS NOT NULL');

        // ========================================
        // PRODUCT ATTRIBUTES TABLE INDEXES
        // ========================================

        $attributeIndexes = [
            'idx_ioa_product_attributes_product_id',
            'idx_ioa_product_attributes_source_name',
            'idx_ioa_product_attributes_attributes_gin',
            'idx_ioa_product_attributes_product_source'
        ];

        foreach ($attributeIndexes as $index) {
            DB::statement("DROP INDEX IF EXISTS {$index}");
        }

        DB::statement('CREATE INDEX idx_ioa_product_attributes_product_id ON ioa_product_attributes(product_id)');
        DB::statement('CREATE INDEX idx_ioa_product_attributes_source_name ON ioa_product_attributes(source_name)');
        DB::statement('CREATE INDEX idx_ioa_product_attributes_attributes_gin ON ioa_product_attributes USING gin(attributes) WHERE attributes IS NOT NULL');
        DB::statement('CREATE INDEX idx_ioa_product_attributes_product_source ON ioa_product_attributes(product_id, source_name)');

        // ========================================
        // PRODUCT SOURCES TABLE INDEXES
        // ========================================

        $sourceIndexes = [
            'idx_ioa_product_sources_product_id',
            'idx_ioa_product_sources_source_name',
            'idx_ioa_product_sources_product_source',
            'idx_ioa_product_sources_source_data_gin'
        ];

        foreach ($sourceIndexes as $index) {
            DB::statement("DROP INDEX IF EXISTS {$index}");
        }

        DB::statement('CREATE INDEX idx_ioa_product_sources_product_id ON ioa_product_sources(product_id)');
        DB::statement('CREATE INDEX idx_ioa_product_sources_source_name ON ioa_product_sources(source_name)');
        DB::statement('CREATE INDEX idx_ioa_product_sources_product_source ON ioa_product_sources(product_id, source_name)');
        DB::statement('CREATE INDEX idx_ioa_product_sources_source_data_gin ON ioa_product_sources USING gin(source_data) WHERE source_data IS NOT NULL');

        // ========================================
        // PRODUCT DOCUMENTS TABLE INDEXES
        // ========================================

        $documentIndexes = [
            'idx_ioa_product_documents_product_id',
            'idx_ioa_product_documents_source_name',
            'idx_ioa_product_documents_product_source',
            'idx_ioa_product_documents_documents_data_gin'
        ];

        foreach ($documentIndexes as $index) {
            DB::statement("DROP INDEX IF EXISTS {$index}");
        }

        DB::statement('CREATE INDEX idx_ioa_product_documents_product_id ON ioa_product_documents(product_id)');
        DB::statement('CREATE INDEX idx_ioa_product_documents_source_name ON ioa_product_documents(source_name)');
        DB::statement('CREATE INDEX idx_ioa_product_documents_product_source ON ioa_product_documents(product_id, source_name)');
        DB::statement('CREATE INDEX idx_ioa_product_documents_documents_data_gin ON ioa_product_documents USING gin(documents_data) WHERE documents_data IS NOT NULL');

        // ========================================
        // CATEGORIES TABLE INDEXES
        // ========================================

        $categoryIndexes = [
            'idx_ioa_categories_name',
            'idx_ioa_categories_status_id',
            'idx_ioa_categories_parent_category',
            'idx_ioa_categories_is_main',
            'idx_ioa_categories_pushed',
            'idx_ioa_categories_hierarchy',
            'idx_ioa_categories_active_hierarchy',
            'idx_ioa_categories_search_composite'
        ];

        foreach ($categoryIndexes as $index) {
            DB::statement("DROP INDEX IF EXISTS {$index}");
        }

        DB::statement('CREATE INDEX idx_ioa_categories_name ON ioa_categories(name)');
        DB::statement('CREATE INDEX idx_ioa_categories_status_id ON ioa_categories(status_id)');
        DB::statement('CREATE INDEX idx_ioa_categories_parent_category ON ioa_categories(parent_category) WHERE parent_category IS NOT NULL');
        DB::statement('CREATE INDEX idx_ioa_categories_is_main ON ioa_categories(is_main) WHERE is_main = true');
        DB::statement('CREATE INDEX idx_ioa_categories_pushed ON ioa_categories(pushed) WHERE pushed = true');
        DB::statement('CREATE INDEX idx_ioa_categories_hierarchy ON ioa_categories(parent_category, name) WHERE parent_category IS NOT NULL');
        DB::statement('CREATE INDEX idx_ioa_categories_active_hierarchy ON ioa_categories(parent_category, status_id, name) WHERE status_id = 1');
        DB::statement('CREATE INDEX idx_ioa_categories_search_composite ON ioa_categories(status_id, name) WHERE status_id = 1');

        // ========================================
        // MANUFACTURERS TABLE INDEXES
        // ========================================

        $manufacturerIndexes = [
            'idx_ioa_manufacturers_name',
            'idx_ioa_manufacturers_status_id',
            'idx_ioa_manufacturers_pushed',
            'idx_ioa_manufacturers_active_name',
            'idx_ioa_manufacturers_pushed_active'
        ];

        foreach ($manufacturerIndexes as $index) {
            DB::statement("DROP INDEX IF EXISTS {$index}");
        }

        DB::statement('CREATE INDEX idx_ioa_manufacturers_name ON ioa_manufacturers(name)');
        DB::statement('CREATE INDEX idx_ioa_manufacturers_status_id ON ioa_manufacturers(status_id)');
        DB::statement('CREATE INDEX idx_ioa_manufacturers_pushed ON ioa_manufacturers(pushed)');
        DB::statement('CREATE INDEX idx_ioa_manufacturers_active_name ON ioa_manufacturers(status_id, name) WHERE status_id = 1');
        DB::statement('CREATE INDEX idx_ioa_manufacturers_pushed_active ON ioa_manufacturers(pushed, status_id)');

        // ========================================
        // BRANDS TABLE INDEXES
        // ========================================

        $brandIndexes = [
            'idx_ioa_brands_name',
            'idx_ioa_brands_manufacturer_id',
            'idx_ioa_brands_status_id',
            'idx_ioa_brands_manufacturer_name',
            'idx_ioa_brands_active_name',
            'idx_ioa_brands_manufacturer_active'
        ];

        foreach ($brandIndexes as $index) {
            DB::statement("DROP INDEX IF EXISTS {$index}");
        }

        DB::statement('CREATE INDEX idx_ioa_brands_name ON ioa_brands(name)');
        DB::statement('CREATE INDEX idx_ioa_brands_manufacturer_id ON ioa_brands(manufacturer_id)');
        DB::statement('CREATE INDEX idx_ioa_brands_status_id ON ioa_brands(status_id)');
        DB::statement('CREATE INDEX idx_ioa_brands_manufacturer_name ON ioa_brands(manufacturer_id, name)');
        DB::statement('CREATE INDEX idx_ioa_brands_active_name ON ioa_brands(status_id, name) WHERE status_id = 1');
        DB::statement('CREATE INDEX idx_ioa_brands_manufacturer_active ON ioa_brands(manufacturer_id, status_id) WHERE status_id = 1');

        // ========================================
        // DATABASE TRIGGERS AND FUNCTIONS
        // ========================================

        // Function to update product search vector
        DB::statement("
            CREATE OR REPLACE FUNCTION update_product_search_vector()
            RETURNS TRIGGER AS $$
            DECLARE
                attribute_text TEXT := '';
                source_text TEXT := '';
                document_text TEXT := '';
            BEGIN
                -- Collect attributes text from JSONB - extract both keys and values
                SELECT COALESCE(string_agg(
                    COALESCE(key, '') || ' ' || COALESCE(value::text, ''), ' '
                ), '') 
                INTO attribute_text
                FROM ioa_product_attributes pa, 
                     jsonb_each_text(pa.attributes) AS kv(key, value)
                WHERE pa.product_id = NEW.id;

                -- Collect source data text
                SELECT COALESCE(string_agg(COALESCE(source_data::text, ''), ' '), '')
                INTO source_text
                FROM ioa_product_sources 
                WHERE product_id = NEW.id;

                -- Collect document data text
                SELECT COALESCE(string_agg(COALESCE(documents_data::text, ''), ' '), '')
                INTO document_text
                FROM ioa_product_documents 
                WHERE product_id = NEW.id;

                -- Update search vector with all combined text
                NEW.search_vector := to_tsvector('english', 
                    COALESCE(NEW.name, '') || ' ' ||
                    COALESCE(NEW.title, '') || ' ' ||
                    COALESCE(NEW.pnum, '') || ' ' ||
                    COALESCE(NEW.mf_pnum, '') || ' ' ||
                    COALESCE(NEW.category_name, '') || ' ' ||
                    COALESCE(NEW.manufacturer_name, '') || ' ' ||
                    COALESCE(NEW.brand_name, '') || ' ' ||
                    COALESCE(attribute_text, '') || ' ' ||
                    COALESCE(source_text, '') || ' ' ||
                    COALESCE(document_text, '')
                );

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        ");

        // Trigger to update search vector on product changes
        DB::statement('DROP TRIGGER IF EXISTS products_search_vector_trigger ON ioa_products');
        DB::statement("
            CREATE TRIGGER products_search_vector_trigger
                BEFORE INSERT OR UPDATE ON ioa_products
                FOR EACH ROW
                EXECUTE FUNCTION update_product_search_vector();
        ");

        // Function to sync category names to products
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

        // Trigger for category name sync
        DB::statement('DROP TRIGGER IF EXISTS categories_sync_products_trigger ON ioa_categories');
        DB::statement("
            CREATE TRIGGER categories_sync_products_trigger
                AFTER UPDATE OF name ON ioa_categories
                FOR EACH ROW
                EXECUTE FUNCTION sync_category_name_to_products();
        ");

        // Function to sync manufacturer names to products
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

        // Trigger for manufacturer name sync
        DB::statement('DROP TRIGGER IF EXISTS manufacturers_sync_products_trigger ON ioa_manufacturers');
        DB::statement("
            CREATE TRIGGER manufacturers_sync_products_trigger
                AFTER UPDATE OF name ON ioa_manufacturers
                FOR EACH ROW
                EXECUTE FUNCTION sync_manufacturer_name_to_products();
        ");

        // Function to sync brand names to products
        DB::statement("
            CREATE OR REPLACE FUNCTION sync_brand_name_to_products()
            RETURNS TRIGGER AS $$
            BEGIN
                UPDATE ioa_products 
                SET brand_name = NEW.name
                WHERE brand_id = NEW.id;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        ");

        // Trigger for brand name sync
        DB::statement('DROP TRIGGER IF EXISTS brands_sync_products_trigger ON ioa_brands');
        DB::statement("
            CREATE TRIGGER brands_sync_products_trigger
                AFTER UPDATE OF name ON ioa_brands
                FOR EACH ROW
                EXECUTE FUNCTION sync_brand_name_to_products();
        ");

        // Function to populate denormalized fields on product insert/update
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

                -- Update brand_name
                SELECT name INTO NEW.brand_name
                FROM ioa_brands 
                WHERE id = NEW.brand_id;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        ");

        // Trigger to populate denormalized fields
        DB::statement('DROP TRIGGER IF EXISTS products_populate_denormalized_fields_trigger ON ioa_products');
        DB::statement("
            CREATE TRIGGER products_populate_denormalized_fields_trigger
                BEFORE INSERT OR UPDATE ON ioa_products
                FOR EACH ROW
                EXECUTE FUNCTION populate_product_denormalized_fields();
        ");

        // Function to update product search from attributes changes
        DB::statement("
            CREATE OR REPLACE FUNCTION update_product_search_from_attributes()
            RETURNS TRIGGER AS $$
            BEGIN
                -- Update the search_vector for the related product
                -- This will trigger the existing product search vector update function
                UPDATE ioa_products 
                SET updated_at = NOW()  -- Minimal update to trigger the search vector update
                WHERE id = COALESCE(NEW.product_id, OLD.product_id);
                
                RETURN COALESCE(NEW, OLD);
            END;
            $$ LANGUAGE plpgsql;
        ");

        // Trigger for product attributes search sync
        DB::statement('DROP TRIGGER IF EXISTS product_attributes_search_sync_trigger ON ioa_product_attributes');
        DB::statement("
            CREATE TRIGGER product_attributes_search_sync_trigger
                AFTER INSERT OR UPDATE OR DELETE ON ioa_product_attributes
                FOR EACH ROW
                EXECUTE FUNCTION update_product_search_from_attributes();
        ");

        // Function to update product search from sources changes
        DB::statement("
            CREATE OR REPLACE FUNCTION update_product_search_from_sources()
            RETURNS TRIGGER AS $$
            BEGIN
                UPDATE ioa_products 
                SET updated_at = NOW()
                WHERE id = COALESCE(NEW.product_id, OLD.product_id);
                
                RETURN COALESCE(NEW, OLD);
            END;
            $$ LANGUAGE plpgsql;
        ");

        // Trigger for product sources search sync
        DB::statement('DROP TRIGGER IF EXISTS product_sources_search_sync_trigger ON ioa_product_sources');
        DB::statement("
            CREATE TRIGGER product_sources_search_sync_trigger
                AFTER INSERT OR UPDATE OR DELETE ON ioa_product_sources
                FOR EACH ROW
                EXECUTE FUNCTION update_product_search_from_sources();
        ");

        // Function to update product search from documents changes
        DB::statement("
            CREATE OR REPLACE FUNCTION update_product_search_from_documents()
            RETURNS TRIGGER AS $$
            BEGIN
                UPDATE ioa_products 
                SET updated_at = NOW()
                WHERE id = COALESCE(NEW.product_id, OLD.product_id);
                
                RETURN COALESCE(NEW, OLD);
            END;
            $$ LANGUAGE plpgsql;
        ");

        // Trigger for product documents search sync
        DB::statement('DROP TRIGGER IF EXISTS product_documents_search_sync_trigger ON ioa_product_documents');
        DB::statement("
            CREATE TRIGGER product_documents_search_sync_trigger
                AFTER INSERT OR UPDATE OR DELETE ON ioa_product_documents
                FOR EACH ROW
                EXECUTE FUNCTION update_product_search_from_documents();
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop all triggers first
        DB::statement('DROP TRIGGER IF EXISTS product_documents_search_sync_trigger ON ioa_product_documents');
        DB::statement('DROP TRIGGER IF EXISTS product_sources_search_sync_trigger ON ioa_product_sources');
        DB::statement('DROP TRIGGER IF EXISTS product_attributes_search_sync_trigger ON ioa_product_attributes');
        DB::statement('DROP TRIGGER IF EXISTS products_populate_denormalized_fields_trigger ON ioa_products');
        DB::statement('DROP TRIGGER IF EXISTS brands_sync_products_trigger ON ioa_brands');
        DB::statement('DROP TRIGGER IF EXISTS manufacturers_sync_products_trigger ON ioa_manufacturers');
        DB::statement('DROP TRIGGER IF EXISTS categories_sync_products_trigger ON ioa_categories');
        DB::statement('DROP TRIGGER IF EXISTS products_search_vector_trigger ON ioa_products');

        // Drop all functions
        DB::statement('DROP FUNCTION IF EXISTS update_product_search_from_documents()');
        DB::statement('DROP FUNCTION IF EXISTS update_product_search_from_sources()');
        DB::statement('DROP FUNCTION IF EXISTS update_product_search_from_attributes()');
        DB::statement('DROP FUNCTION IF EXISTS populate_product_denormalized_fields()');
        DB::statement('DROP FUNCTION IF EXISTS sync_brand_name_to_products()');
        DB::statement('DROP FUNCTION IF EXISTS sync_manufacturer_name_to_products()');
        DB::statement('DROP FUNCTION IF EXISTS sync_category_name_to_products()');
        DB::statement('DROP FUNCTION IF EXISTS update_product_search_vector()');

        // Drop all indexes (PostgreSQL will automatically drop them when tables are dropped,
        // but we'll explicitly drop them for clarity)
        $allIndexes = [
            // Product indexes
            'idx_ioa_products_pnum',
            'idx_ioa_products_mf_pnum',
            'idx_ioa_products_mf_pnum_slug',
            'idx_ioa_products_category_id',
            'idx_ioa_products_manufacturer_id',
            'idx_ioa_products_brand_id',
            'idx_ioa_products_status_id',
            'idx_ioa_products_name',
            'idx_ioa_products_title',
            'idx_ioa_products_name_trgm',
            'idx_ioa_products_title_trgm',
            'idx_ioa_products_search_vector',
            'idx_ioa_products_search_vector_gin',
            'idx_ioa_products_created_at',
            'idx_ioa_products_updated_at',
            'idx_ioa_products_status_id_active',
            'idx_ioa_products_category_status',
            'idx_ioa_products_manufacturer_status',
            'idx_ioa_products_brand_status',
            'idx_ioa_products_category_manufacturer',
            'idx_ioa_products_category_brand',
            'idx_ioa_products_manufacturer_brand',
            'idx_ioa_products_pushed',
            'idx_ioa_products_rohs_compliant',
            'idx_ioa_products_is_updated',
            'idx_ioa_products_search_composite',
            'idx_ioa_products_filter_composite',
            'idx_ioa_products_category_name',
            'idx_ioa_products_manufacturer_name',
            'idx_ioa_products_brand_name',
            
            // Product attributes indexes
            'idx_ioa_product_attributes_product_id',
            'idx_ioa_product_attributes_source_name',
            'idx_ioa_product_attributes_attributes_gin',
            'idx_ioa_product_attributes_product_source',
            
            // Product sources indexes
            'idx_ioa_product_sources_product_id',
            'idx_ioa_product_sources_source_name',
            'idx_ioa_product_sources_product_source',
            'idx_ioa_product_sources_source_data_gin',
            
            // Product documents indexes
            'idx_ioa_product_documents_product_id',
            'idx_ioa_product_documents_source_name',
            'idx_ioa_product_documents_product_source',
            'idx_ioa_product_documents_documents_data_gin',
            
            // Category indexes
            'idx_ioa_categories_name',
            'idx_ioa_categories_status_id',
            'idx_ioa_categories_parent_category',
            'idx_ioa_categories_is_main',
            'idx_ioa_categories_pushed',
            'idx_ioa_categories_hierarchy',
            'idx_ioa_categories_active_hierarchy',
            'idx_ioa_categories_search_composite',
            
            // Manufacturer indexes
            'idx_ioa_manufacturers_name',
            'idx_ioa_manufacturers_status_id',
            'idx_ioa_manufacturers_pushed',
            'idx_ioa_manufacturers_active_name',
            'idx_ioa_manufacturers_pushed_active',
            
            // Brand indexes
            'idx_ioa_brands_name',
            'idx_ioa_brands_manufacturer_id',
            'idx_ioa_brands_status_id',
            'idx_ioa_brands_manufacturer_name',
            'idx_ioa_brands_active_name',
            'idx_ioa_brands_manufacturer_active'
        ];

        foreach ($allIndexes as $index) {
            DB::statement("DROP INDEX IF EXISTS {$index}");
        }
    }
};