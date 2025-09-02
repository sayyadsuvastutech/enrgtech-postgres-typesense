<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Laravel\Scout\Jobs\MakeSearchable;
use Typesense\Client;

class TypesenseImportCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'typesense:import 
                            {--batch=500 : Number of products to process in each batch}
                            {--chunk=100 : Chunk size for Typesense bulk operations}
                            {--force : Force recreation of collection}
                            {--flush : Delete and recreate collection}
                            {--queue : Use queue for processing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fast import/sync products to Typesense search engine with optimized batching';

    private Client $typesenseClient;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting Typesense product import...');

        try {
            $this->initializeTypesenseClient();

            if ($this->option('flush')) {
                $this->flushCollection();
            }

            if ($this->option('queue')) {
                return $this->handleQueuedImport();
            }

            return $this->handleDirectImport();

        } catch (\Exception $e) {
            $this->error('Import failed: '.$e->getMessage());
            Log::error('Typesense import failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return 1;
        }
    }

    private function initializeTypesenseClient(): void
    {
        $config = config('scout.typesense.client-settings');
        $this->typesenseClient = new Client($config);
    }

    private function flushCollection(): void
    {
        $collectionName = (new Product)->searchableAs();

        try {
            $this->typesenseClient->collections[$collectionName]->delete();
            $this->info("Deleted existing collection: {$collectionName}");
        } catch (\Exception $e) {
            $this->comment("Collection {$collectionName} doesn't exist or couldn't be deleted");
        }

        // Recreate collection with Scout
        $product = new Product;
        $product->createIndex();
        $this->info("Created fresh collection: {$collectionName}");
    }

    private function handleQueuedImport(): int
    {
        $this->info('Using queued import for better performance...');

        $batchSize = (int) $this->option('batch');
        $totalProducts = Product::active()->count();

        $this->info("Queuing {$totalProducts} products for import in batches of {$batchSize}");

        Product::active()
            ->with(['category', 'manufacturer', 'brand', 'attributes', 'prices', 'quantities', 'images'])
            ->chunk($batchSize, function ($products) {
                dispatch(new MakeSearchable($products));
            });

        $this->info('All import jobs have been queued. Run php artisan queue:work to process them.');

        return 0;
    }

    private function handleDirectImport(): int
    {
        $batchSize = (int) $this->option('batch');
        $chunkSize = (int) $this->option('chunk');

        $totalProducts = Product::active()->count();

        if (! $this->option('force') && ! $this->confirm("Import {$totalProducts} products to Typesense?")) {
            $this->info('Import cancelled.');

            return 0;
        }

        $this->info("Starting direct import of {$totalProducts} products...");
        $this->info("Batch size: {$batchSize}, Chunk size: {$chunkSize}");

        $startTime = microtime(true);
        $processed = 0;
        $errors = 0;

        $progressBar = $this->output->createProgressBar($totalProducts);
        $progressBar->start();

        Product::active()
            ->with(['category', 'manufacturer', 'brand', 'attributes', 'prices', 'quantities', 'images'])
            ->chunk($batchSize, function ($products) use ($chunkSize, $progressBar, &$processed, &$errors) {
                try {
                    $this->processBatch($products, $chunkSize);
                    $processed += $products->count();
                    $progressBar->advance($products->count());
                } catch (\Exception $e) {
                    $errors += $products->count();
                    $this->error("\nBatch processing failed: ".$e->getMessage());
                    Log::error('Typesense batch import failed', [
                        'batch_size' => $products->count(),
                        'error' => $e->getMessage(),
                    ]);
                }
            });

        $progressBar->finish();
        $this->newLine();

        $endTime = microtime(true);
        $duration = round($endTime - $startTime, 2);
        $rate = $processed > 0 ? round($processed / $duration, 2) : 0;

        $this->info("Import completed in {$duration} seconds");
        $this->info("Processed: {$processed} products");
        $this->info("Errors: {$errors} products");
        $this->info("Rate: {$rate} products/second");

        return $errors > 0 ? 1 : 0;
    }

    private function processBatch($products, int $chunkSize): void
    {
        $collectionName = (new Product)->searchableAs();

        // Process products in smaller chunks for Typesense API
        $chunks = $products->chunk($chunkSize);

        foreach ($chunks as $chunk) {
            $documents = [];

            foreach ($chunk as $product) {
                $documents[] = $product->toSearchableArray();
            }

            if (! empty($documents)) {
                $this->typesenseClient->collections[$collectionName]->documents->import($documents, [
                    'action' => 'upsert',
                ]);
            }
        }
    }

    private function validateTypesenseConnection(): bool
    {
        try {
            $this->typesenseClient->health->retrieve();

            return true;
        } catch (\Exception $e) {
            $this->error('Cannot connect to Typesense server: '.$e->getMessage());
            $this->comment('Please check your Typesense configuration in .env file');

            return false;
        }
    }
}
