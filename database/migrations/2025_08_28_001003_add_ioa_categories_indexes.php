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
        $this->dropIndexIfExists('public_ioa_categories_pkey');
        $this->dropIndexIfExists('public_ioa_categories_name_unique');
        $this->dropIndexIfExists('public_ioa_categories_name_idx');
        $this->dropIndexIfExists('public_ioa_categories_slug_idx');
        $this->dropIndexIfExists('public_ioa_categories_parent_category_idx');
        $this->dropIndexIfExists('public_ioa_categories_is_main_idx');
        $this->dropIndexIfExists('public_ioa_categories_pushed_idx');
        $this->dropIndexIfExists('public_ioa_categories_status_id_idx');
        $this->dropIndexIfExists('public_ioa_categories_products_count_idx');
        $this->dropIndexIfExists('public_ioa_categories_parent_category_is_main_idx');
        $this->dropIndexIfExists('public_ioa_categories_parent_category_name_idx');
        $this->dropIndexIfExists('public_ioa_categories_parent_category_products_count_idx');
        $this->dropIndexIfExists('public_ioa_categories_is_main_pushed_idx');
        $this->dropIndexIfExists('public_ioa_categories_status_id_pushed_idx');
        $this->dropIndexIfExists('public_ioa_categories_id_name_idx');

        // Create indexes as per the provided structure
        DB::statement('CREATE UNIQUE INDEX public_ioa_categories_pkey ON public.ioa_categories USING btree (id)');
        DB::statement('CREATE UNIQUE INDEX public_ioa_categories_name_unique ON public.ioa_categories USING btree (name)');
        DB::statement('CREATE INDEX public_ioa_categories_name_idx ON public.ioa_categories USING btree (name)');
        DB::statement('CREATE INDEX public_ioa_categories_slug_idx ON public.ioa_categories USING btree (slug)');
        DB::statement('CREATE INDEX public_ioa_categories_parent_category_idx ON public.ioa_categories USING btree (parent_category)');
        DB::statement('CREATE INDEX public_ioa_categories_is_main_idx ON public.ioa_categories USING btree (is_main)');
        DB::statement('CREATE INDEX public_ioa_categories_pushed_idx ON public.ioa_categories USING btree (pushed)');
        DB::statement('CREATE INDEX public_ioa_categories_status_id_idx ON public.ioa_categories USING btree (status_id)');
        DB::statement('CREATE INDEX public_ioa_categories_products_count_idx ON public.ioa_categories USING btree (products_count)');
        DB::statement('CREATE INDEX public_ioa_categories_parent_category_is_main_idx ON public.ioa_categories USING btree (parent_category, is_main)');
        DB::statement('CREATE INDEX public_ioa_categories_parent_category_name_idx ON public.ioa_categories USING btree (parent_category, name)');
        DB::statement('CREATE INDEX public_ioa_categories_parent_category_products_count_idx ON public.ioa_categories USING btree (parent_category, products_count)');
        DB::statement('CREATE INDEX public_ioa_categories_is_main_pushed_idx ON public.ioa_categories USING btree (is_main, pushed)');
        DB::statement('CREATE INDEX public_ioa_categories_status_id_pushed_idx ON public.ioa_categories USING btree (status_id, pushed)');
        DB::statement('CREATE INDEX public_ioa_categories_id_name_idx ON public.ioa_categories USING btree (id, name)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->dropIndexIfExists('public_ioa_categories_pkey');
        $this->dropIndexIfExists('public_ioa_categories_name_unique');
        $this->dropIndexIfExists('public_ioa_categories_name_idx');
        $this->dropIndexIfExists('public_ioa_categories_slug_idx');
        $this->dropIndexIfExists('public_ioa_categories_parent_category_idx');
        $this->dropIndexIfExists('public_ioa_categories_is_main_idx');
        $this->dropIndexIfExists('public_ioa_categories_pushed_idx');
        $this->dropIndexIfExists('public_ioa_categories_status_id_idx');
        $this->dropIndexIfExists('public_ioa_categories_products_count_idx');
        $this->dropIndexIfExists('public_ioa_categories_parent_category_is_main_idx');
        $this->dropIndexIfExists('public_ioa_categories_parent_category_name_idx');
        $this->dropIndexIfExists('public_ioa_categories_parent_category_products_count_idx');
        $this->dropIndexIfExists('public_ioa_categories_is_main_pushed_idx');
        $this->dropIndexIfExists('public_ioa_categories_status_id_pushed_idx');
        $this->dropIndexIfExists('public_ioa_categories_id_name_idx');
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