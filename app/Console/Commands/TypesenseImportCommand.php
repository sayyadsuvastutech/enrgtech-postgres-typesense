<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Laravel\Scout\Jobs\MakeSearchable;
use Typesense\Client;
use Illuminate\Support\Collection;

class TypesenseImportCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'typesense:import
                            {--batch=2000 : Number of products to process in each batch}
                            {--chunk=1000 : Chunk size for Typesense bulk operations}
                            {--parallel=4 : Number of parallel workers for queued processing}
                            {--memory-limit=2G : Memory limit for the process}
                            {--force : Force recreation of collection}
                            {--flush : Delete and recreate collection}
                            {--queue : Use queue for processing}
                            {--skip-relations : Skip loading relations for faster processing}
                            {--optimize : Use optimized batch processing}
                            {--checkpoint=10000 : Create checkpoint every N records}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Ultra-fast import/sync products to Typesense search engine optimized for millions of records';

    private Client $typesenseClient;
    private int $checkpointCounter = 0;
    private int $lastProcessedId = 0;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // Set memory limit
        $memoryLimit = $this->option('memory-limit');
        ini_set('memory_limit', $memoryLimit);

        $this->info("Starting optimized Typesense product import with {$memoryLimit} memory limit...");

        try {
            $this->initializeTypesenseClient();

            // Validate Typesense connection
            if (!$this->validateTypesenseConnection()) {
                return 1;
            }

            // Optimize Typesense client settings
            $this->optimizeTypesenseSettings();

            // Ensure collection exists
            $this->ensureCollectionExists();

            if ($this->option('flush')) {
                $this->flushCollection();
            }

            if ($this->option('queue')) {
                return $this->handleOptimizedQueuedImport();
            }

            if ($this->option('optimize')) {
                return $this->handleUltraFastImport();
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

        // Optimize client settings for bulk operations
        $config['connection_timeout_seconds'] = 300;
        $config['timeout_seconds'] = 300;

        $this->typesenseClient = new Client($config);
    }

    private function optimizeTypesenseSettings(): void
    {
        // Increase batch size limits and timeouts for bulk operations
        $this->info('Optimizing Typesense client for bulk operations...');

        // These settings help with large bulk imports
        // Note: These may need to be configured on the Typesense server side as well
    }

    private function handleUltraFastImport(): int
    {
        $batchSize = (int) $this->option('batch');
        $chunkSize = (int) $this->option('chunk');
        $checkpointInterval = (int) $this->option('checkpoint');
        $skipRelations = $this->option('skip-relations');

        // Get total count more efficiently
        $totalProducts = $this->getOptimizedCount();

        if (!$this->option('force') && !$this->confirm("Import {$totalProducts} products to Typesense using ultra-fast mode?")) {
            $this->info('Import cancelled.');
            return 0;
        }

        $this->info("Starting ultra-fast import of {$totalProducts} products...");
        $this->info("Batch size: {$batchSize}, Chunk size: {$chunkSize}, Checkpoint: {$checkpointInterval}");

        $startTime = microtime(true);
        $processed = 0;
        $errors = 0;

        $progressBar = $this->output->createProgressBar($totalProducts);
        $progressBar->start();

        // Use cursor-based pagination for memory efficiency
        $query = $this->buildOptimizedQuery($skipRelations);

        $query->chunkById($batchSize, function ($products) use ($chunkSize, $progressBar, &$processed, &$errors, $checkpointInterval) {
            try {
                $this->processOptimizedBatch($products, $chunkSize);
                $processed += $products->count();
                $this->checkpointCounter += $products->count();
                $progressBar->advance($products->count());

                // Create checkpoint
                if ($this->checkpointCounter >= $checkpointInterval) {
                    $this->createCheckpoint($processed);
                    $this->checkpointCounter = 0;

                    // Garbage collection to free memory
                    gc_collect_cycles();
                }

            } catch (\Exception $e) {
                $errors += $products->count();
                $this->error("\nBatch processing failed: ".$e->getMessage());
                Log::error('Typesense batch import failed', [
                    'batch_size' => $products->count(),
                    'error' => $e->getMessage(),
                ]);
            }
        }, 'id'); // Use ID-based chunking for better performance

        $progressBar->finish();
        $this->newLine();

        return $this->displayResults($startTime, $processed, $errors);
    }

    private function handleOptimizedQueuedImport(): int
    {
        $this->info('Using optimized queued import for maximum performance...');

        $batchSize = (int) $this->option('batch');
        $parallelWorkers = (int) $this->option('parallel');
        $totalProducts = $this->getOptimizedCount();

        $this->info("Queuing {$totalProducts} products for import in batches of {$batchSize} with {$parallelWorkers} parallel workers");

        // Create multiple queue batches for parallel processing
        $batchNumber = 0;
        $query = $this->buildOptimizedQuery($this->option('skip-relations'));

        $query->chunkById($batchSize, function ($products) use (&$batchNumber, $parallelWorkers) {
            // Distribute across multiple queues for parallel processing
            $queueName = 'typesense_import_' . ($batchNumber % $parallelWorkers);

            dispatch(new MakeSearchable($products))
                ->onQueue($queueName)
                ->onConnection('redis'); // Use Redis for better performance

            $batchNumber++;
        }, 'id');

        $this->info("All import jobs have been queued across {$parallelWorkers} parallel queues.");
        $this->comment("Run multiple workers: php artisan queue:work --queue=typesense_import_0,typesense_import_1,typesense_import_2,typesense_import_3");

        return 0;
    }

    private function buildOptimizedQuery(bool $skipRelations = false)
    {
        $query = Product::active()
            ->select([
                'id', 'title', 'name', 'pnum', 'mf_pnum', 'description',
                'category_id', 'manufacturer_id', 'brand_id',
                'price', 'in_stock', 'stock_quantity', 'is_rohs_compliant',
                'average_rating', 'total_reviews', 'created_at', 'updated_at'
            ]);

        if (!$skipRelations) {
            $query->with([
                'category:id,name',
                'manufacturer:id,name',
                'brand:id,name',
                'attributes:id,name,value',
                'prices:product_id,price,currency',
                'quantities:product_id,quantity,location',
                'images:product_id,url,is_primary'
            ]);
        }

        return $query;
    }

    private function processOptimizedBatch($products, int $chunkSize): void
    {
        $collectionName = (new Product)->searchableAs();

        // Pre-allocate array with known size for better memory usage
        $allDocuments = [];
        $allDocuments = array_pad($allDocuments, $products->count(), null);

        // Transform products to searchable arrays
        foreach ($products as $index => $product) {
            $allDocuments[$index] = $product->toSearchableArray();
        }

        // Process in optimized chunks
        $chunks = array_chunk($allDocuments, $chunkSize);

        foreach ($chunks as $documents) {
            if (!empty($documents)) {
                $this->typesenseClient->collections[$collectionName]->documents->import(
                    $documents,
                    [
                        'action' => 'upsert',
                        'batch_size' => $chunkSize
                    ]
                );
            }
        }

        // Clear memory
        unset($allDocuments, $chunks);
    }

    private function getOptimizedCount(): int
    {
        // Use raw query for faster counting
        return DB::table('products')
            ->where('status', 'active') // Adjust this condition based on your active() scope
            ->count();
    }

    private function createCheckpoint(int $processed): void
    {
        $this->info("\n✓ Checkpoint: {$processed} products processed");

        // Log checkpoint
        Log::info('Typesense import checkpoint', [
            'processed' => $processed,
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true)
        ]);
    }

    private function displayResults($startTime, int $processed, int $errors): int
    {
        $endTime = microtime(true);
        $duration = round($endTime - $startTime, 2);
        $rate = $processed > 0 ? round($processed / $duration, 2) : 0;
        $memoryPeak = round(memory_get_peak_usage(true) / 1024 / 1024, 2);

        $this->info("Import completed in {$duration} seconds");
        $this->info("Processed: {$processed} products");
        $this->info("Errors: {$errors} products");
        $this->info("Rate: {$rate} products/second");
        $this->info("Peak memory usage: {$memoryPeak} MB");

        return $errors > 0 ? 1 : 0;
    }

    private function handleDirectImport(): int
    {
        $batchSize = (int) $this->option('batch');
        $chunkSize = (int) $this->option('chunk');

        $totalProducts = Product::active()->count();

        if (!$this->option('force') && !$this->confirm("Import {$totalProducts} products to Typesense?")) {
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

        return $this->displayResults($startTime, $processed, $errors);
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

            if (!empty($documents)) {
                $this->typesenseClient->collections[$collectionName]->documents->import($documents, [
                    'action' => 'upsert',
                ]);
            }
        }
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

        // Recreate collection
        $this->createCollection($collectionName);
        $this->info("Created fresh collection: {$collectionName}");
    }

    private function validateTypesenseConnection(): bool
    {
        try {
            $this->typesenseClient->health->retrieve();
            $this->info('✓ Typesense server connection successful');
            return true;
        } catch (\Exception $e) {
            $this->error('Cannot connect to Typesense server: '.$e->getMessage());
            $this->comment('Please check your Typesense configuration in .env file');
            return false;
        }
    }

    private function ensureCollectionExists(): void
    {
        $collectionName = (new Product)->searchableAs();

        try {
            $this->typesenseClient->collections[$collectionName]->retrieve();
            $this->info("✓ Collection '{$collectionName}' already exists");
        } catch (\Exception $e) {
            $this->info("Creating collection: {$collectionName}");
            $this->createCollection($collectionName);
            $this->info("✓ Collection '{$collectionName}' created successfully");
        }
    }

    private function createCollection(string $collectionName): void
    {
        $schema = $this->getCollectionSchema($collectionName);
        $this->typesenseClient->collections->create($schema);
    }

    private function getCollectionSchema(string $collectionName): array
    {
        // Get schema from scout config
        $modelSettings = config('scout.typesense.model-settings.' . Product::class);
        $schema = $modelSettings['collection-schema'] ?? [];

        // Set collection name
        $schema['name'] = $collectionName;

        // Set default fields if not configured
        if (empty($schema['fields'])) {
            $schema['fields'] = $this->getDefaultFields();
        }

        // Set default sorting field if not configured
        if (!isset($schema['default_sorting_field'])) {
            $schema['default_sorting_field'] = 'created_at';
        }

        return $schema;
    }

    private function getDefaultFields(): array
    {
        return [
            ['name' => 'id', 'type' => 'string'],
            ['name' => 'title', 'type' => 'string'],
            ['name' => 'name', 'type' => 'string'],
            ['name' => 'pnum', 'type' => 'string', 'facet' => true],
            ['name' => 'mf_pnum', 'type' => 'string', 'facet' => true],
            ['name' => 'description', 'type' => 'string'],
            ['name' => 'category_id', 'type' => 'int32', 'facet' => true],
            ['name' => 'category_name', 'type' => 'string', 'facet' => true],
            ['name' => 'manufacturer_id', 'type' => 'int32', 'facet' => true],
            ['name' => 'manufacturer_name', 'type' => 'string', 'facet' => true],
            ['name' => 'brand_id', 'type' => 'int32', 'facet' => true],
            ['name' => 'brand_name', 'type' => 'string', 'facet' => true],
            ['name' => 'price', 'type' => 'float', 'facet' => true],
            ['name' => 'in_stock', 'type' => 'bool', 'facet' => true],
            ['name' => 'stock_quantity', 'type' => 'int32', 'facet' => true],
            ['name' => 'is_rohs_compliant', 'type' => 'bool', 'facet' => true],
            ['name' => 'average_rating', 'type' => 'float', 'facet' => true],
            ['name' => 'total_reviews', 'type' => 'int32'],
            ['name' => 'breadcrumb', 'type' => 'string'],
            ['name' => 'attributes', 'type' => 'string[]', 'facet' => true],
            ['name' => 'searchable_attributes', 'type' => 'string'],
            ['name' => 'sources', 'type' => 'string[]', 'facet' => true],
            ['name' => 'image_url', 'type' => 'string'],
            ['name' => 'created_at', 'type' => 'int64'],
            ['name' => 'updated_at', 'type' => 'int64']
        ];
    }
}
