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
        $this->dropIndexIfExists('public_ioa_product_quantities_pkey');
        $this->dropIndexIfExists('public_ioa_product_quantities_product_id_idx');
        $this->dropIndexIfExists('public_ioa_product_quantities_quantity_idx');
        $this->dropIndexIfExists('public_ioa_product_quantities_source_name_idx');
        $this->dropIndexIfExists('public_ioa_product_quantities_availability_status_idx');
        $this->dropIndexIfExists('public_ioa_product_quantities_product_id_quantity_idx');
        $this->dropIndexIfExists('public_ioa_product_quantities_product_id_source_name_idx');
        $this->dropIndexIfExists('public_ioa_product_quantities_product_id_availability_status_id');
        $this->dropIndexIfExists('public_ioa_product_quantities_source_name_availability_status_i');

        // Create indexes as per the provided structure
        DB::statement('CREATE UNIQUE INDEX public_ioa_product_quantities_pkey ON public.ioa_product_quantities USING btree (id)');
        DB::statement('CREATE INDEX public_ioa_product_quantities_product_id_idx ON public.ioa_product_quantities USING btree (product_id)');
        DB::statement('CREATE INDEX public_ioa_product_quantities_quantity_idx ON public.ioa_product_quantities USING btree (quantity)');
        DB::statement('CREATE INDEX public_ioa_product_quantities_source_name_idx ON public.ioa_product_quantities USING btree (source_name)');
        DB::statement('CREATE INDEX public_ioa_product_quantities_availability_status_idx ON public.ioa_product_quantities USING btree (availability_status)');
        DB::statement('CREATE INDEX public_ioa_product_quantities_product_id_quantity_idx ON public.ioa_product_quantities USING btree (product_id, quantity)');
        DB::statement('CREATE INDEX public_ioa_product_quantities_product_id_source_name_idx ON public.ioa_product_quantities USING btree (product_id, source_name)');
        DB::statement('CREATE INDEX public_ioa_product_quantities_product_id_availability_status_id ON public.ioa_product_quantities USING btree (product_id, availability_status)');
        DB::statement('CREATE INDEX public_ioa_product_quantities_source_name_availability_status_i ON public.ioa_product_quantities USING btree (source_name, availability_status)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->dropIndexIfExists('public_ioa_product_quantities_pkey');
        $this->dropIndexIfExists('public_ioa_product_quantities_product_id_idx');
        $this->dropIndexIfExists('public_ioa_product_quantities_quantity_idx');
        $this->dropIndexIfExists('public_ioa_product_quantities_source_name_idx');
        $this->dropIndexIfExists('public_ioa_product_quantities_availability_status_idx');
        $this->dropIndexIfExists('public_ioa_product_quantities_product_id_quantity_idx');
        $this->dropIndexIfExists('public_ioa_product_quantities_product_id_source_name_idx');
        $this->dropIndexIfExists('public_ioa_product_quantities_product_id_availability_status_id');
        $this->dropIndexIfExists('public_ioa_product_quantities_source_name_availability_status_i');
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