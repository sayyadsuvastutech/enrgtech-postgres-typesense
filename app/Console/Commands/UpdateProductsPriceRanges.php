<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\TypesenseEcommerceSearchService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class UpdateProductsPriceRanges extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ecommerce:update-price-ranges 
                            {--batch=1000 : Number of products to process in each batch}
                            {--force : Force update all products}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update all products in Typesense with price range facets for ecommerce search';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $batchSize = (int) $this->option('batch');
        $force = $this->option('force');

        $this->info("Starting price range update for products...");

        try {
            $searchService = app(TypesenseEcommerceSearchService::class);
            $totalProducts = Product::active()->count();

            if (!$force && !$this->confirm("Update price ranges for {$totalProducts} products?")) {
                $this->info('Operation cancelled.');
                return 0;
            }

            $progressBar = $this->output->createProgressBar($totalProducts);
            $progressBar->start();

            $processed = 0;
            $errors = 0;

            Product::active()
                ->with(['prices'])
                ->chunk($batchSize, function ($products) use ($searchService, $progressBar, &$processed, &$errors) {
                    foreach ($products as $product) {
                        try {
                            $searchService->updateProductWithPriceRange($product);
                            $processed++;
                        } catch (\Exception $e) {
                            $errors++;
                            Log::error('Failed to update product price range', [
                                'product_id' => $product->id,
                                'error' => $e->getMessage()
                            ]);
                        }
                        $progressBar->advance();
                    }
                });

            $progressBar->finish();
            $this->newLine();

            $this->info("Price range update completed!");
            $this->info("Processed: {$processed} products");
            if ($errors > 0) {
                $this->warn("Errors: {$errors} products failed to update");
            }

            return $errors > 0 ? 1 : 0;

        } catch (\Exception $e) {
            $this->error('Command failed: ' . $e->getMessage());
            Log::error('Price range update command failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return 1;
        }
    }
}
