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
        $this->dropIndexIfExists('public_ioa_manufacturers_pkey');
        $this->dropIndexIfExists('public_ioa_manufacturers_name_unique');
        $this->dropIndexIfExists('public_ioa_manufacturers_name_idx');
        $this->dropIndexIfExists('public_ioa_manufacturers_slug_idx');
        $this->dropIndexIfExists('public_ioa_manufacturers_status_id_idx');
        $this->dropIndexIfExists('public_ioa_manufacturers_id_name_idx');

        // Create indexes as per the provided structure
        DB::statement('CREATE UNIQUE INDEX public_ioa_manufacturers_pkey ON public.ioa_manufacturers USING btree (id)');
        DB::statement('CREATE UNIQUE INDEX public_ioa_manufacturers_name_unique ON public.ioa_manufacturers USING btree (name)');
        DB::statement('CREATE INDEX public_ioa_manufacturers_name_idx ON public.ioa_manufacturers USING btree (name)');
        DB::statement('CREATE INDEX public_ioa_manufacturers_slug_idx ON public.ioa_manufacturers USING btree (slug)');
        DB::statement('CREATE INDEX public_ioa_manufacturers_status_id_idx ON public.ioa_manufacturers USING btree (status_id)');
        DB::statement('CREATE INDEX public_ioa_manufacturers_id_name_idx ON public.ioa_manufacturers USING btree (id, name)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->dropIndexIfExists('public_ioa_manufacturers_pkey');
        $this->dropIndexIfExists('public_ioa_manufacturers_name_unique');
        $this->dropIndexIfExists('public_ioa_manufacturers_name_idx');
        $this->dropIndexIfExists('public_ioa_manufacturers_slug_idx');
        $this->dropIndexIfExists('public_ioa_manufacturers_status_id_idx');
        $this->dropIndexIfExists('public_ioa_manufacturers_id_name_idx');
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