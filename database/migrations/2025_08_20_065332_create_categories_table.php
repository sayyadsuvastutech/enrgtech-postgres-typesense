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
        Schema::create('ioa_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->integer('parent_category')->nullable();
            $table->boolean('is_main')->default(false);
            $table->boolean('pushed')->default(false);
            $table->string('meta_title')->nullable();
            $table->string('meta_description')->nullable();
            $table->foreignId('status_id')->constrained('ioa_statuses')->onDelete('restrict');
            $table->integer('products_count')->default(0);
            $table->timestamps();

            $table->index(['id', 'name'], 'ioa_categories_id_name_join_idx');

        });
    }

};
