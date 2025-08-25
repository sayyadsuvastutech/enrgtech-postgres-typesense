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
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->integer('category_id')->nullable();
            $table->string('name');
            $table->string('slug');
            $table->string('image')->nullable();
            $table->string('image_url')->nullable();
            $table->text('description')->nullable();
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->text('popular_items')->nullable();
            $table->text('new_items')->nullable();
            $table->bigInteger('status_id')->nullable();
            $table->bigInteger('domain_id')->nullable();
            $table->bigInteger('created_by')->nullable();
            $table->bigInteger('updated_by')->nullable();
            $table->timestamps();

            // PostgreSQL specific indexes for performance
            $table->index('category_id');
            $table->index('status_id');
            $table->index('domain_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
