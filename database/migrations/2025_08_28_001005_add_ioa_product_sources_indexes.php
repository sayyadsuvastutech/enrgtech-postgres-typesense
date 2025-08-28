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
        $this->dropIndexIfExists('public_ioa_product_sources_pkey');
        $this->dropIndexIfExists('public_ioa_product_sources_product_id_idx');
        $this->dropIndexIfExists('public_ioa_product_sources_source_name_idx');
        $this->dropIndexIfExists('public_ioa_product_sources_source_product_id_idx');
        $this->dropIndexIfExists('public_ioa_product_sources_product_id_source_name_idx');

        // Create indexes as per the provided structure
        DB::statement('CREATE UNIQUE INDEX public_ioa_product_sources_pkey ON public.ioa_product_sources USING btree (id)');
        DB::statement('CREATE INDEX public_ioa_product_sources_product_id_idx ON public.ioa_product_sources USING btree (product_id)');
        DB::statement('CREATE INDEX public_ioa_product_sources_source_name_idx ON public.ioa_product_sources USING btree (source_name)');
        DB::statement('CREATE INDEX public_ioa_product_sources_source_product_id_idx ON public.ioa_product_sources USING btree (source_product_id)');
        DB::statement('CREATE INDEX public_ioa_product_sources_product_id_source_name_idx ON public.ioa_product_sources USING btree (product_id, source_name)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->dropIndexIfExists('public_ioa_product_sources_pkey');
        $this->dropIndexIfExists('public_ioa_product_sources_product_id_idx');
        $this->dropIndexIfExists('public_ioa_product_sources_source_name_idx');
        $this->dropIndexIfExists('public_ioa_product_sources_source_product_id_idx');
        $this->dropIndexIfExists('public_ioa_product_sources_product_id_source_name_idx');
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