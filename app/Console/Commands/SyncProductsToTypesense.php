<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncProductsToTypesense extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'typesense:sync-products
                            {--batch=500 : Number of products to process in each batch}
                            {--limit= : Limit number of products to sync}
                            {--update-existing : Update existing products in index}
                            {--recreate-index : Drop and recreate the index}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync products to Typesense search index using existing embeddings';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting Typesense product sync...');

        $batchSize = (int) $this->option('batch');
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;
        $updateExisting = $this->option('update-existing');
        $recreateIndex = $this->option('recreate-index');

        try {
            // Get active products with relationships
            $baseQuery = Product::with([
                'category', 'manufacturer', 'brand', 'attributes',
                'prices', 'quantities', 'images', 'embedding',
            ])->where('status_id', 2)
            ->whereHas('embedding',function ($query)  {
                return $query->whereNotNull('embedding');
            });
            
            // Apply limit if specified
            if ($limit) {
                $query = $baseQuery->take($limit);
                $totalProducts = min($limit, $baseQuery->count());
                $this->info("Limiting sync to {$limit} products");
            } else {
                $query = $baseQuery->take(10000);
                $totalProducts = min(10000, $baseQuery->count());
            }
            $this->info("Found {$totalProducts} active products to sync");

            if ($totalProducts === 0) {
                $this->warn('No active products found to sync');

                return self::SUCCESS;
            }

            // Check how many products already have embeddings
            $productsWithEmbeddings = Product::whereHas('embedding')->where('status_id', 2)->count();
            $this->info("Products with existing embeddings: {$productsWithEmbeddings}");

            // Process in batches
            $processedCount = 0;
            $errorCount = 0;

            $progressBar = $this->output->createProgressBar($totalProducts);
            $progressBar->start();

            $query->chunk($batchSize, function ($products) use (
                &$processedCount,
                &$errorCount,
                $progressBar
            ) {
                foreach ($products as $product) {
                    try {
                        // Sync to Typesense (embedding will be included if it exists)
                        $product->searchable();
                        $processedCount++;

                    } catch (\Exception $e) {
                        $errorCount++;
                        Log::error('Failed to sync product to Typesense', [
                            'product_id' => $product->id,
                            'error' => $e->getMessage(),
                        ]);
                    }

                    $progressBar->advance();
                }
            });

            $progressBar->finish();
            $this->newLine(2);

            $this->info('Sync completed!');
            $this->table(['Metric', 'Count'], [
                ['Total Products', $totalProducts],
                ['Products with Embeddings', $productsWithEmbeddings],
                ['Successfully Synced', $processedCount],
                ['Errors', $errorCount],
                ['Success Rate', round(($processedCount / $totalProducts) * 100, 2).'%'],
            ]);

            if ($errorCount > 0) {
                $this->warn('Check the logs for error details');
            }

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Sync failed: '.$e->getMessage());
            Log::error('Typesense sync command failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return self::FAILURE;
        }
    }

}
