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
        $this->dropIndexIfExists('public_ioa_product_prices_pkey');
        $this->dropIndexIfExists('public_ioa_product_prices_product_id_idx');
        $this->dropIndexIfExists('public_ioa_product_prices_currency_idx');
        $this->dropIndexIfExists('public_ioa_product_prices_pricing_ranges_idx');
        $this->dropIndexIfExists('public_ioa_product_prices_product_id_currency_idx');
        $this->dropIndexIfExists('public_ioa_product_prices_source_name_currency_idx');

        // Create indexes as per the provided structure
        DB::statement('CREATE UNIQUE INDEX public_ioa_product_prices_pkey ON public.ioa_product_prices USING btree (id)');
        DB::statement('CREATE INDEX public_ioa_product_prices_product_id_idx ON public.ioa_product_prices USING btree (product_id)');
        DB::statement('CREATE INDEX public_ioa_product_prices_currency_idx ON public.ioa_product_prices USING btree (currency)');
        DB::statement('CREATE INDEX public_ioa_product_prices_pricing_ranges_idx ON public.ioa_product_prices USING gin (pricing_ranges)');
        DB::statement('CREATE INDEX public_ioa_product_prices_product_id_currency_idx ON public.ioa_product_prices USING btree (product_id, currency)');
        DB::statement('CREATE INDEX public_ioa_product_prices_source_name_currency_idx ON public.ioa_product_prices USING btree (source_name, currency)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->dropIndexIfExists('public_ioa_product_prices_pkey');
        $this->dropIndexIfExists('public_ioa_product_prices_product_id_idx');
        $this->dropIndexIfExists('public_ioa_product_prices_currency_idx');
        $this->dropIndexIfExists('public_ioa_product_prices_pricing_ranges_idx');
        $this->dropIndexIfExists('public_ioa_product_prices_product_id_currency_idx');
        $this->dropIndexIfExists('public_ioa_product_prices_source_name_currency_idx');
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