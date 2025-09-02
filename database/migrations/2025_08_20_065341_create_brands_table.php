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
        Schema::create('ioa_brands', function (Blueprint $table) {
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
            $table->foreignId('status_id')->default(2)->constrained('ioa_statuses')->onDelete('restrict');
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('manufacturer_id')->references('id')->on('ioa_manufacturers')->onDelete('restrict');

            $table->index(['id', 'name'], 'ioa_brands_id_name_join_idx');

        });
    }
};
