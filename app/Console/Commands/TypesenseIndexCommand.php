<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Typesense\Client;

class TypesenseIndexCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'typesense:index 
                            {action : Action to perform (list, create, delete, rename, info)}
                            {--model=Product : Model to work with}
                            {--new-name= : New name for rename action}
                            {--force : Force action without confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Manage Typesense indexes - list, create, delete, rename, or get info';

    private Client $typesenseClient;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            $this->initializeTypesenseClient();

            $action = $this->argument('action');

            return match ($action) {
                'list' => $this->listIndexes(),
                'create' => $this->createIndex(),
                'delete' => $this->deleteIndex(),
                'rename' => $this->renameIndex(),
                'info' => $this->showIndexInfo(),
                default => $this->showHelp()
            };

        } catch (\Exception $e) {
            $this->error('Command failed: '.$e->getMessage());

            return 1;
        }
    }

    private function initializeTypesenseClient(): void
    {
        $config = config('scout.typesense.client-settings');
        $this->typesenseClient = new Client($config);
    }

    private function listIndexes(): int
    {
        $this->info('Fetching all Typesense collections...');

        try {
            $collections = $this->typesenseClient->collections->retrieve();

            if (empty($collections)) {
                $this->comment('No collections found.');

                return 0;
            }

            $this->table(
                ['Name', 'Documents', 'Fields', 'Created'],
                collect($collections)->map(function ($collection) {
                    return [
                        $collection['name'],
                        $collection['num_documents'] ?? 0,
                        count($collection['fields'] ?? []),
                        isset($collection['created_at']) ?
                            date('Y-m-d H:i:s', $collection['created_at']) : 'Unknown',
                    ];
                })->toArray()
            );

            return 0;

        } catch (\Exception $e) {
            $this->error('Failed to retrieve collections: '.$e->getMessage());

            return 1;
        }
    }

    private function createIndex(): int
    {
        $model = $this->getModelInstance();
        $indexName = $model->searchableAs();

        $this->info("Creating index: {$indexName}");

        try {
            $model->createIndex();
            $this->info("Index '{$indexName}' created successfully.");

            return 0;

        } catch (\Exception $e) {
            $this->error('Failed to create index: '.$e->getMessage());

            return 1;
        }
    }

    private function deleteIndex(): int
    {
        $model = $this->getModelInstance();
        $indexName = $model->searchableAs();

        if (! $this->option('force') && ! $this->confirm("Delete index '{$indexName}'?")) {
            $this->info('Operation cancelled.');

            return 0;
        }

        try {
            $this->typesenseClient->collections[$indexName]->delete();
            $this->info("Index '{$indexName}' deleted successfully.");

            return 0;

        } catch (\Exception $e) {
            $this->error('Failed to delete index: '.$e->getMessage());

            return 1;
        }
    }

    private function renameIndex(): int
    {
        $newName = $this->option('new-name');

        if (! $newName) {
            $this->error('New name is required for rename action. Use --new-name option.');

            return 1;
        }

        $model = $this->getModelInstance();
        $currentName = $model->searchableAs();

        $this->info("Renaming index from '{$currentName}' to '{$newName}'");

        if (! $this->option('force') && ! $this->confirm('Proceed with rename?')) {
            $this->info('Operation cancelled.');

            return 0;
        }

        try {
            // Export data from old index
            $this->info('Exporting data from current index...');
            $documents = $this->typesenseClient->collections[$currentName]->documents->export();

            // Create new index with new name
            $this->info('Creating new index...');
            $schema = $this->typesenseClient->collections[$currentName]->retrieve();
            $schema['name'] = $newName;
            unset($schema['created_at'], $schema['num_documents']);

            $this->typesenseClient->collections->create($schema);

            // Import data to new index
            if ($documents) {
                $this->info('Importing data to new index...');
                $this->typesenseClient->collections[$newName]->documents->import($documents);
            }

            // Delete old index
            $this->info('Deleting old index...');
            $this->typesenseClient->collections[$currentName]->delete();

            $this->info("Index renamed successfully from '{$currentName}' to '{$newName}'");

            return 0;

        } catch (\Exception $e) {
            $this->error('Failed to rename index: '.$e->getMessage());

            return 1;
        }
    }

    private function showIndexInfo(): int
    {
        $model = $this->getModelInstance();
        $indexName = $model->searchableAs();

        try {
            $collection = $this->typesenseClient->collections[$indexName]->retrieve();

            $this->info("Index Information for: {$indexName}");
            $this->line('');
            $this->comment('Documents: '.($collection['num_documents'] ?? 0));
            $this->comment('Fields: '.count($collection['fields'] ?? []));
            $this->comment('Created: '.(isset($collection['created_at']) ?
                date('Y-m-d H:i:s', $collection['created_at']) : 'Unknown'));

            if (! empty($collection['fields'])) {
                $this->line('');
                $this->comment('Field Configuration:');

                $this->table(
                    ['Field Name', 'Type', 'Facet', 'Optional'],
                    collect($collection['fields'])->map(function ($field) {
                        return [
                            $field['name'],
                            $field['type'],
                            isset($field['facet']) ? ($field['facet'] ? 'Yes' : 'No') : 'No',
                            isset($field['optional']) ? ($field['optional'] ? 'Yes' : 'No') : 'No',
                        ];
                    })->toArray()
                );
            }

            return 0;

        } catch (\Exception $e) {
            $this->error('Failed to get index info: '.$e->getMessage());

            return 1;
        }
    }

    private function getModelInstance()
    {
        $modelName = $this->option('model');

        $modelClass = match ($modelName) {
            'Product' => Product::class,
            default => throw new \InvalidArgumentException("Unsupported model: {$modelName}")
        };

        return new $modelClass;
    }

    private function showHelp(): int
    {
        $this->info('Available actions:');
        $this->line('  list     - List all Typesense collections');
        $this->line('  create   - Create index for specified model');
        $this->line('  delete   - Delete index for specified model');
        $this->line('  rename   - Rename index (requires --new-name)');
        $this->line('  info     - Show detailed information about index');
        $this->line('');
        $this->info('Examples:');
        $this->line('  php artisan typesense:index list');
        $this->line('  php artisan typesense:index create --model=Product');
        $this->line('  php artisan typesense:index info --model=Product');
        $this->line('  php artisan typesense:index rename --model=Product --new-name=products_v2');

        return 0;
    }
}
