<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ioa_products', function (Blueprint $table) {
            // Optimize COUNT queries for category filter options
            $table->index(['category_id', 'status_id'], 'ioa_products_category_status_count_idx');

            // Optimize COUNT queries for brand filter options
            $table->index(['brand_id', 'status_id'], 'ioa_products_brand_status_count_idx');

            // Optimize COUNT queries for manufacturer filter options
            $table->index(['manufacturer_id', 'status_id'], 'ioa_products_manufacturer_status_count_idx');

        });

        // Add indexes to categories table for better JOIN performance
        Schema::table('ioa_categories', function (Blueprint $table) {
            $table->index(['id', 'name'], 'ioa_categories_id_name_join_idx');
        });

        // Add indexes to brands table for better JOIN performance
        Schema::table('ioa_brands', function (Blueprint $table) {
            $table->index(['id', 'name'], 'ioa_brands_id_name_join_idx');
        });

        // Add indexes to manufacturers table for better JOIN performance
        Schema::table('ioa_manufacturers', function (Blueprint $table) {
            $table->index(['id', 'name'], 'ioa_manufacturers_id_name_join_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ioa_products', function (Blueprint $table) {
            $table->dropIndex('ioa_products_category_status_count_idx');
            $table->dropIndex('ioa_products_brand_status_count_idx');
            $table->dropIndex('ioa_products_manufacturer_status_count_idx');
        });

        Schema::table('ioa_categories', function (Blueprint $table) {
            $table->dropIndex('ioa_categories_id_name_join_idx');
        });

        Schema::table('ioa_brands', function (Blueprint $table) {
            $table->dropIndex('ioa_brands_id_name_join_idx');
        });

        Schema::table('ioa_manufacturers', function (Blueprint $table) {
            $table->dropIndex('ioa_manufacturers_id_name_join_idx');
        });
    }
};
