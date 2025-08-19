<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();

            // Core product identifiers
            $table->string('pnum', 20)->unique();
            $table->bigInteger('oth_id')->unique();
            $table->string('mf_pnum', 50);
            $table->string('mf_pnum_norm', 50);
            $table->json('mf_pnum_list')->nullable();

            // Basic product info
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->text('description_ext')->nullable();
            $table->json('mf_keys')->nullable();

            // Relationships
            $table->foreignId('manufacturer_id')->constrained('manufacturers')->onDelete('cascade');
            $table->foreignId('category_id')->constrained('categories')->onDelete('cascade');

            // Pricing and inventory
            $table->json('pricing_all')->nullable();
            $table->json('quantity_all')->nullable();
            $table->boolean('rohs_all')->nullable();

            // Product attributes and specifications
            $table->json('attributes_list_all')->nullable();
            $table->json('attributes_list_all_filter')->nullable();
            $table->json('images_all')->nullable();
            $table->json('document_list_all')->nullable();
            $table->json('sources_all')->nullable();

            // SEO and categorization
            $table->string('sitemap', 20)->nullable();
            $table->json('categories_all')->nullable();
            $table->json('categories_all_filters')->nullable();
            $table->text('related_links')->nullable();
            $table->json('prod_redirect_to')->nullable();

            // Status and metadata
            $table->integer('status')->default(1);
            $table->string('oth_source', 10)->nullable();
            $table->string('uom_message')->nullable();
            $table->string('country_of_origin', 50)->nullable();
            $table->boolean('is_active')->default(true);

            // Sync tracking
            $table->bigInteger('sess_ins_id')->nullable();
            $table->bigInteger('sess_upd_id')->nullable();
            $table->string('last_updated_by', 20)->nullable();
            $table->string('Last_updated', 20)->nullable();
            $table->bigInteger('api_last_update')->nullable();
            $table->boolean('api_last_response_status')->nullable();

            $table->timestamps();

            // Performance indexes
            $table->index(['is_active', 'status']);
            $table->index(['manufacturer_id', 'is_active']);
            $table->index(['category_id', 'is_active']);
            $table->index(['pnum']);
            $table->index(['mf_pnum']);
            $table->index(['oth_id']);
            $table->index(['oth_source']);
            $table->index(['api_last_update']);

            // Search indexes
            $table->index(['name']);
            $table->index(['description']);
            $table->index(['mf_pnum_norm']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
