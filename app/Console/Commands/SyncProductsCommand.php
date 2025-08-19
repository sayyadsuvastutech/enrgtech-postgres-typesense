<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class SyncProductsCommand extends Command
{
    protected $signature = 'products:sync {--limit=2000 : Maximum products to sync} {--offset=0 : Offset to start from} {--term= : Search term} {--category= : Category filter} {--brand= : Brand/manufacturer filter} {--per_page=25 : Products per page} {--page=1 : Page number}';

    protected $description = 'Synchronize products from Elasticsearch to local database with search filtering';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $offset = (int) $this->option('offset');
        $term = $this->option('term');
        $category = $this->option('category');
        $brand = $this->option('brand');
        $perPage = (int) $this->option('per_page');
        $page = (int) $this->option('page');

        $this->info('Starting product synchronization...');
        $this->info("Limit: {$limit}, Offset: {$offset}");
        
        if ($term) {
            $this->info("Search term: {$term}");
        }
        if ($category) {
            $this->info("Category filter: {$category}");
        }
        if ($brand) {
            $this->info("Brand filter: {$brand}");
        }

        try {
            // Set environment variables for the seeder to access
            config([
                'sync.limit' => $limit,
                'sync.offset' => $offset,
                'sync.term' => $term,
                'sync.category' => $category,
                'sync.brand' => $brand,
                'sync.per_page' => $perPage,
                'sync.page' => $page,
            ]);

            Artisan::call('db:seed', [
                '--class' => 'ProductSeeder',
                '--force' => true,
            ]);

            $output = Artisan::output();
            $this->info($output);

            $this->info('✅ Product synchronization completed successfully!');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("❌ Synchronization failed: {$e->getMessage()}");

            return Command::FAILURE;
        }
    }
}
