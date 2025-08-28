<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Drop existing indexes if they exist
        $this->dropIndexIfExists('public_ioa_products_pkey');
        $this->dropIndexIfExists('public_ioa_products_pnum_unique');
        $this->dropIndexIfExists('public_ioa_products_name_idx');
        $this->dropIndexIfExists('public_ioa_products_pnum_idx');
        $this->dropIndexIfExists('public_ioa_products_category_id_idx');
        $this->dropIndexIfExists('public_ioa_products_manufacturer_id_idx');
        $this->dropIndexIfExists('public_ioa_products_brand_id_idx');
        $this->dropIndexIfExists('public_ioa_products_status_id_idx');
        $this->dropIndexIfExists('public_ioa_products_pushed_idx');
        $this->dropIndexIfExists('public_ioa_products_search_vector_idx');
        $this->dropIndexIfExists('public_ioa_products_category_id_manufacturer_id_status_id_idx');
        $this->dropIndexIfExists('public_ioa_products_category_id_status_id_idx');
        $this->dropIndexIfExists('public_ioa_products_manufacturer_id_status_id_idx');
        $this->dropIndexIfExists('public_ioa_products_brand_id_status_id_idx');
        $this->dropIndexIfExists('public_ioa_products_status_id_category_id_idx');
        $this->dropIndexIfExists('public_ioa_products_status_id_manufacturer_id_idx');
        $this->dropIndexIfExists('public_ioa_products_status_id_created_at_desc_idx');
        $this->dropIndexIfExists('public_ioa_products_status_id_category_id_name_idx');
        $this->dropIndexIfExists('public_ioa_products_status_id_manufacturer_id_name_idx');
        $this->dropIndexIfExists('public_ioa_products_category_id_name_idx');

        // Create indexes as per the provided structure
        DB::statement('CREATE UNIQUE INDEX public_ioa_products_pkey ON public.ioa_products USING btree (id)');
        DB::statement('CREATE UNIQUE INDEX public_ioa_products_pnum_unique ON public.ioa_products USING btree (pnum)');
        DB::statement('CREATE INDEX public_ioa_products_name_idx ON public.ioa_products USING btree (name) WHERE (status_id = 1)');
        DB::statement('CREATE INDEX public_ioa_products_pnum_idx ON public.ioa_products USING btree (pnum) WHERE (status_id = 1)');
        DB::statement('CREATE INDEX public_ioa_products_category_id_idx ON public.ioa_products USING btree (category_id)');
        DB::statement('CREATE INDEX public_ioa_products_manufacturer_id_idx ON public.ioa_products USING btree (manufacturer_id)');
        DB::statement('CREATE INDEX public_ioa_products_brand_id_idx ON public.ioa_products USING btree (brand_id)');
        DB::statement('CREATE INDEX public_ioa_products_status_id_idx ON public.ioa_products USING btree (status_id)');
        DB::statement('CREATE INDEX public_ioa_products_pushed_idx ON public.ioa_products USING btree (pushed)');
        DB::statement('CREATE INDEX public_ioa_products_search_vector_idx ON public.ioa_products USING gin (search_vector) WHERE (status_id = 1)');
        DB::statement('CREATE INDEX public_ioa_products_category_id_manufacturer_id_status_id_idx ON public.ioa_products USING btree (category_id, manufacturer_id, status_id) WHERE (status_id = 1)');
        DB::statement('CREATE INDEX public_ioa_products_category_id_status_id_idx ON public.ioa_products USING btree (category_id, status_id)');
        DB::statement('CREATE INDEX public_ioa_products_manufacturer_id_status_id_idx ON public.ioa_products USING btree (manufacturer_id, status_id)');
        DB::statement('CREATE INDEX public_ioa_products_brand_id_status_id_idx ON public.ioa_products USING btree (brand_id, status_id)');
        DB::statement('CREATE INDEX public_ioa_products_status_id_category_id_idx ON public.ioa_products USING btree (status_id, category_id) WHERE (status_id = 1)');
        DB::statement('CREATE INDEX public_ioa_products_status_id_manufacturer_id_idx ON public.ioa_products USING btree (status_id, manufacturer_id) WHERE (status_id = 1)');
        DB::statement('CREATE INDEX public_ioa_products_status_id_created_at_desc_idx ON public.ioa_products USING btree (status_id, created_at DESC) WHERE (status_id = 1)');
        DB::statement('CREATE INDEX public_ioa_products_status_id_category_id_name_idx ON public.ioa_products USING btree (status_id, category_id, name) WHERE (status_id = 1)');
        DB::statement('CREATE INDEX public_ioa_products_status_id_manufacturer_id_name_idx ON public.ioa_products USING btree (status_id, manufacturer_id, name) WHERE (status_id = 1)');
        DB::statement('CREATE INDEX public_ioa_products_category_id_name_idx ON public.ioa_products USING btree (category_id, name) WHERE (status_id = 1)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->dropIndexIfExists('public_ioa_products_pkey');
        $this->dropIndexIfExists('public_ioa_products_pnum_unique');
        $this->dropIndexIfExists('public_ioa_products_name_idx');
        $this->dropIndexIfExists('public_ioa_products_pnum_idx');
        $this->dropIndexIfExists('public_ioa_products_category_id_idx');
        $this->dropIndexIfExists('public_ioa_products_manufacturer_id_idx');
        $this->dropIndexIfExists('public_ioa_products_brand_id_idx');
        $this->dropIndexIfExists('public_ioa_products_status_id_idx');
        $this->dropIndexIfExists('public_ioa_products_pushed_idx');
        $this->dropIndexIfExists('public_ioa_products_search_vector_idx');
        $this->dropIndexIfExists('public_ioa_products_category_id_manufacturer_id_status_id_idx');
        $this->dropIndexIfExists('public_ioa_products_category_id_status_id_idx');
        $this->dropIndexIfExists('public_ioa_products_manufacturer_id_status_id_idx');
        $this->dropIndexIfExists('public_ioa_products_brand_id_status_id_idx');
        $this->dropIndexIfExists('public_ioa_products_status_id_category_id_idx');
        $this->dropIndexIfExists('public_ioa_products_status_id_manufacturer_id_idx');
        $this->dropIndexIfExists('public_ioa_products_status_id_created_at_desc_idx');
        $this->dropIndexIfExists('public_ioa_products_status_id_category_id_name_idx');
        $this->dropIndexIfExists('public_ioa_products_status_id_manufacturer_id_name_idx');
        $this->dropIndexIfExists('public_ioa_products_category_id_name_idx');
    }

    /**
     * Drop index if it exists
     */
    private function dropIndexIfExists(string $indexName): void
    {
        try {
            DB::statement("DROP INDEX IF EXISTS {$indexName}");
        } catch (\Exception $e) {
            // Index doesn't exist or couldn't be dropped
        }
    }
};