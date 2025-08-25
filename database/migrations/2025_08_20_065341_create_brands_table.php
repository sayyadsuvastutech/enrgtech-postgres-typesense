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
        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->integer('manufacturer_id');
            $table->string('name');
            $table->string('slug');
            $table->string('logo')->nullable();
            $table->string('banner')->nullable();
            $table->text('description')->nullable();
            $table->string('website')->nullable();
            $table->string('type')->nullable();
            $table->string('size')->nullable();
            $table->string('location')->nullable();
            $table->string('founded')->nullable();
            $table->string('specialties')->nullable();
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->text('popular_items')->nullable();
            $table->text('new_items')->nullable();
            $table->bigInteger('domain_id')->nullable();
            $table->bigInteger('created_by')->nullable();
            $table->bigInteger('updated_by')->nullable();
            $table->timestamps();

            // PostgreSQL specific indexes for performance
            $table->index('name');
            $table->index('manufacturer_id');
            $table->index('domain_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('brands');
    }
};
