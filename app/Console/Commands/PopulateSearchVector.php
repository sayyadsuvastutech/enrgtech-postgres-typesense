<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PopulateSearchVector extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'search:populate-vector 
                            {--batch=1000 : Number of products to process in each batch}
                            {--single : Use single query for all records (fastest but uses more memory)}
                            {--force : Force update all products without prompting}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Populate search_vector column for products with searchable content from product fields and attributes';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $batchSize = (int) $this->option('batch');
        $force = $this->option('force');

        $totalProducts = Product::count();
        
        if (!$force && !$this->confirm("This will update search vectors for {$totalProducts} products. Continue?")) {
            $this->info('Operation cancelled.');
            return;
        }

        $this->info("Starting search vector population for {$totalProducts} products...");
        
        $startTime = microtime(true);
        $processed = 0;

        if ($this->option('single')) {
            // Single query approach - fastest but uses more memory
            $this->info('Using single query approach (fastest)...');
            $processed = $this->updateAllSearchVectorsSingle();
        } else {
            // Batched approach - memory efficient
            $this->info("Using batched approach with batch size: {$batchSize}...");
            $bar = $this->output->createProgressBar($totalProducts);
            $bar->start();
            $this->updateSearchVectorsBulk($batchSize, $bar, $processed);
            $bar->finish();
            $this->newLine();
        }

        $endTime = microtime(true);
        $duration = round($endTime - $startTime, 2);
        
        $this->info("Successfully updated search vectors for {$processed} products in {$duration} seconds.");
    }

    /**
     * Update search vectors in bulk using pure SQL for maximum performance
     */
    private function updateSearchVectorsBulk(int $batchSize, $bar, int &$processed): void
    {
        $offset = 0;
        
        do {
            $sql = "
                UPDATE ioa_products 
                SET search_vector = to_tsvector('english', 
                    COALESCE(name, '') || ' ' ||
                    COALESCE(title, '') || ' ' ||
                    COALESCE(category_name, '') || ' ' ||
                    COALESCE(manufacturer_name, '') || ' ' ||
                    COALESCE(brand_name, '') || ' ' ||
                    COALESCE(breadcrumb, '') || ' ' ||
                    COALESCE(description, '') || ' ' ||
                    COALESCE(
                        (SELECT STRING_AGG(
                            COALESCE(attr_key, '') || ' ' || COALESCE(attr_value, ''), ' '
                        ) 
                        FROM (
                            SELECT 
                                jsonb_object_keys(pa.attributes) as attr_key,
                                jsonb_extract_path_text(pa.attributes, jsonb_object_keys(pa.attributes)) as attr_value
                            FROM ioa_product_attributes pa 
                            WHERE pa.product_id = ioa_products.id
                        ) attrs), 
                        ''
                    )
                )
                WHERE id IN (
                    SELECT id FROM ioa_products 
                    ORDER BY id 
                    LIMIT ? OFFSET ?
                )
            ";
            
            $affectedRows = DB::update($sql, [$batchSize, $offset]);
            
            $processed += $affectedRows;
            $bar->advance($affectedRows);
            $offset += $batchSize;
            
        } while ($affectedRows > 0);
    }

    /**
     * Update all search vectors in a single query - fastest method
     */
    private function updateAllSearchVectorsSingle(): int
    {
        $sql = "
            UPDATE ioa_products 
            SET search_vector = to_tsvector('english', 
                COALESCE(name, '') || ' ' ||
                COALESCE(title, '') || ' ' ||
                COALESCE(category_name, '') || ' ' ||
                COALESCE(manufacturer_name, '') || ' ' ||
                COALESCE(brand_name, '') || ' ' ||
                COALESCE(breadcrumb, '') || ' ' ||
                COALESCE(description, '') || ' ' ||
                COALESCE(
                    (SELECT STRING_AGG(
                        COALESCE(attr_key, '') || ' ' || COALESCE(attr_value, ''), ' '
                    ) 
                    FROM (
                        SELECT 
                            jsonb_object_keys(pa.attributes) as attr_key,
                            jsonb_extract_path_text(pa.attributes, jsonb_object_keys(pa.attributes)) as attr_value
                        FROM ioa_product_attributes pa 
                        WHERE pa.product_id = ioa_products.id
                    ) attrs), 
                    ''
                )
            )
        ";
        
        return DB::update($sql);
    }

    /**
     * Clean and normalize search content
     */
    private function cleanSearchContent(string $content): string
    {
        // Remove extra whitespace and normalize
        $content = preg_replace('/\s+/', ' ', $content);
        
        // Remove special characters that might interfere with search
        $content = preg_replace('/[^\w\s\-\.\/]/', ' ', $content);
        
        // Remove extra spaces again
        $content = trim(preg_replace('/\s+/', ' ', $content));
        
        return $content;
    }
}
