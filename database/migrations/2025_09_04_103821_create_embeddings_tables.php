<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Ensure the pgvector extension exists
        DB::statement('CREATE EXTENSION IF NOT EXISTS vector');

        // Create table without timestamps first
        Schema::create('ioa_product_embeddings', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('product_id');
            $table->text('product_text')->nullable();

            $table->foreign('product_id')
                ->references('id')
                ->on('ioa_products')
                ->onDelete('cascade')
                ->onUpdate('no action');
        });

        // Add the vector column after product_text
        DB::statement('ALTER TABLE ioa_product_embeddings ADD COLUMN embedding vector(256)');
        
        // Add timestamps after embedding
        DB::statement('ALTER TABLE ioa_product_embeddings ADD COLUMN created_at timestamp(0) without time zone');
        DB::statement('ALTER TABLE ioa_product_embeddings ADD COLUMN updated_at timestamp(0) without time zone');

        // Create the ivfflat index for embeddings
        DB::statement('
            CREATE INDEX IF NOT EXISTS ioa_product_embeddings_embedding_idx
            ON ioa_product_embeddings
            USING ivfflat (embedding vector_cosine_ops)
            WITH (lists = 100)
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ioa_product_embeddings');
    }
};
