<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Manufacturer;
use App\Models\Product;
use App\Services\ElasticsearchService;
use Exception;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

class ProductSeeder extends Seeder
{
    use WithoutModelEvents;

    protected ElasticsearchService $elasticsearchService;

    protected int $batchSize = 100;

    public function __construct(ElasticsearchService $elasticsearchService)
    {
        $this->elasticsearchService = $elasticsearchService;
    }

    public function run(): void
    {
        $this->command->info('Starting product synchronization from Elasticsearch...');

        // Get parameters from config (set by the command)
        $maxProducts = config('sync.limit', 2000);
        $initialOffset = config('sync.offset', 0);
        $searchTerm = config('sync.term', '');
        $category = config('sync.category', '');
        $brand = config('sync.brand', '');
        $perPage = config('sync.per_page', 25);
        $startPage = config('sync.page', 1);

        if ($searchTerm || $category || $brand) {
            $this->command->info("Using search filters - Term: '{$searchTerm}', Category: '{$category}', Brand: '{$brand}'");
            $totalProducts = $this->getFilteredProductCount($searchTerm ?? '', $category ?? '', $brand ?? '');
        } else {
            $totalProducts = $this->getTotalProductCount();
        }

        $this->command->info("Found {$totalProducts} products in Elasticsearch");
        $this->command->info("Syncing up to {$maxProducts} products");

        $processedCount = 0;
        $failedCount = 0;
        $currentPage = $startPage;

        try {
            while ($processedCount < $maxProducts) {
                $from = ($currentPage - 1) * $perPage + $initialOffset;
                $size = min($perPage, $maxProducts - $processedCount);

                $this->command->info("Processing page {$currentPage} (from: {$from}, size: {$size})");

                if ($searchTerm || $category || $brand) {
                    $response = $this->elasticsearchService->searchWithFilters($searchTerm ?? '', $category ?? '', $brand ?? '', $size, $from);
                } else {
                    $response = $this->elasticsearchService->search([], $size, $from);
                }

                if (empty($response['hits']['hits'])) {
                    $this->command->warn('No more products found');
                    break;
                }

                foreach ($response['hits']['hits'] as $hit) {
                    if ($processedCount >= $maxProducts) {
                        break;
                    }

                    try {
                        $productData = $hit['_source'] ?? [];

                        if ($this->processProduct($productData)) {
                            $processedCount++;
                        } else {
                            $failedCount++;
                        }
                    } catch (Exception $e) {
                        $failedCount++;
                        Log::error('Failed to process product', [
                            'error' => $e->getMessage(),
                            'product_data' => $productData ?? null,
                        ]);
                    }
                }

                $this->command->info("Processed: {$processedCount}, Failed: {$failedCount}");
                $currentPage++;

                // Break if we've reached the maximum or no more results
                if (count($response['hits']['hits']) < $size) {
                    $this->command->info('Reached end of results');
                    break;
                }
            }
        } catch (Exception $e) {
            $this->command->error("Synchronization failed: {$e->getMessage()}");

            return;
        }

        $this->command->info('Product synchronization completed!');
        $this->command->info("Total processed: {$processedCount}");
        $this->command->info("Total failed: {$failedCount}");
    }

    protected function getTotalProductCount(): int
    {
        try {
            $response = $this->elasticsearchService->search([], 1);

            return $response['hits']['total']['value'] ?? 0;
        } catch (Exception $e) {
            $this->command->error("Failed to get total product count: {$e->getMessage()}");

            return 0;
        }
    }

    protected function getFilteredProductCount(string $term = '', string $category = '', string $brand = ''): int
    {
        try {
            $response = $this->elasticsearchService->searchWithFilters($term, $category, $brand, 1);

            return $response['hits']['total']['value'] ?? 0;
        } catch (Exception $e) {
            $this->command->error("Failed to get filtered product count: {$e->getMessage()}");

            return 0;
        }
    }

    protected function processProduct(array $productData): bool
    {

        if (empty($productData['pnum']) || empty($productData['oth_id'])) {
            $this->command->warn('Missing required fields (pnum or oth_id) for product: '.json_encode($productData, JSON_UNESCAPED_SLASHES));

            return false;
        }

        try {
            $manufacturer = $this->findOrCreateManufacturer($productData);
            $category = $this->findOrCreateCategory($productData);

            if (! $manufacturer) {
                $this->command->error("Failed to create manufacturer for product: {$productData['pnum']}");

                return false;
            }

            if (! $category) {
                $this->command->error("Failed to create category for product: {$productData['pnum']}");

                return false;
            }

            $product = Product::updateOrCreate(
                ['oth_id' => $productData['oth_id']],
                [
                    'pnum' => $productData['pnum'] ?? '',
                    'mf_pnum' => $productData['mf_pnum'] ?? '',
                    'mf_pnum_norm' => $productData['mf_pnum_norm'] ?? '',
                    'mf_pnum_list' => $productData['mf_pnum_list'] ?? null,
                    'name' => $productData['name'] ?? '',
                    'description' => $productData['description'] ?? null,
                    'description_ext' => $productData['description_ext'] ?? null,
                    'mf_keys' => $productData['mf_keys'] ?? null,
                    'manufacturer_id' => $manufacturer->id,
                    'category_id' => $category->id,
                    'pricing_all' => $productData['pricing_all'] ?? null,
                    'quantity_all' => $productData['quantity_all'] ?? null,
                    'rohs_all' => isset($productData['rohs_all']) ? $this->extractRohsValue($productData['rohs_all']) : null,
                    'attributes_list_all' => $productData['attributes_list_all'] ?? null,
                    'attributes_list_all_filter' => $productData['attributes_list_all_filter'] ?? null,
                    'images_all' => $productData['images_all'] ?? null,
                    'document_list_all' => $productData['document_list_all'] ?? null,
                    'sources_all' => $productData['sources_all'] ?? null,
                    'sitemap' => $productData['sitemap'] ?? null,
                    'categories_all' => $productData['categories_all'] ?? null,
                    'categories_all_filters' => $productData['categories_all_filters'] ?? null,
                    'related_links' => $this->parseRelatedLinks($productData['related_links'] ?? null),
                    'prod_redirect_to' => $productData['prod_redirect_to'] ?? null,
                    'status' => $productData['status'] ?? 1,
                    'oth_source' => $productData['oth_source'] ?? null,
                    'uom_message' => $productData['uom_message'] ?? null,
                    'country_of_origin' => $productData['country_of_origin'] ?? null,
                    'is_active' => ($productData['status'] ?? 1) === 1,
                    'sess_ins_id' => $productData['sess_ins_id'] ?? null,
                    'sess_upd_id' => $productData['sess_upd_id'] ?? null,
                    'last_updated_by' => $productData['last_updated_by'] ?? null,
                    'Last_updated' => $productData['Last_updated'] ?? null,
                    'api_last_update' => $productData['api_last_update'] ?? null,
                    'api_last_response_status' => $productData['api_last_response_status'] ?? null,
                ]
            );

            return true;
        } catch (Exception $e) {
            Log::error('Failed to process product', [
                'pnum' => $productData['pnum'] ?? 'unknown',
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    protected function findOrCreateManufacturer(array $productData): ?Manufacturer
    {
        if (empty($productData['mf_name'])) {
            $this->command->warn('Missing manufacturer name for product: '.($productData['pnum'] ?? 'unknown'));

            return null;
        }

        $mfId = $productData['mf_id'] ?? null;
        $name = $productData['mf_name'];

        try {
            $manufacturer = null;

            if ($mfId) {
                $manufacturer = Manufacturer::where('mf_id', $mfId)->first();
            }

            if (! $manufacturer) {
                $manufacturer = Manufacturer::where('name', $name)->first();
            }

            if (! $manufacturer) {
                $manufacturer = Manufacturer::create([
                    'mf_id' => $mfId,
                    'name' => $name,
                    'slug' => $this->createSlug($name),
                    'is_active' => true,
                ]);
            }

            return $manufacturer;
        } catch (Exception $e) {
            $this->command->error("Failed to create manufacturer '{$name}': {$e->getMessage()}");
            Log::error('Failed to create manufacturer', [
                'name' => $name,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    protected function findOrCreateCategory(array $productData): ?Category
    {
        if (empty($productData['ct_name'])) {
            $this->command->warn('Missing category name for product: '.($productData['pnum'] ?? 'unknown'));

            return null;
        }

        $ctId = $productData['ct_id'] ?? null;
        $name = $productData['ct_name'];

        try {
            $category = null;

            if ($ctId) {
                $category = Category::where('ct_id', $ctId)->first();
            }

            if (! $category) {
                $category = Category::where('name', $name)->first();
            }

            if (! $category) {
                $category = Category::create([
                    'ct_id' => $ctId,
                    'name' => $name,
                    'slug' => $this->createSlug($name),
                    'filters' => is_array($productData['categories_all_filters'] ?? null) ? $productData['categories_all_filters'] : null,
                    'is_active' => true,
                ]);
            }

            return $category;
        } catch (Exception $e) {
            $this->command->error("Failed to create category '{$name}': {$e->getMessage()}");
            Log::error('Failed to create category', [
                'name' => $name,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    protected function createSlug(string $text): string
    {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $text), '-'));
        $baseSlug = substr($slug, 0, 90);

        // Add a unique suffix to avoid duplicates
        $uniqueSlug = $baseSlug.'-'.substr(md5($text), 0, 8);

        return substr($uniqueSlug, 0, 100);
    }

    protected function extractRohsValue($rohsData)
    {
        if (is_bool($rohsData)) {
            return $rohsData;
        }
        
        if (is_array($rohsData)) {
            foreach ($rohsData as $source => $value) {
                if (is_bool($value)) {
                    return $value;
                }
            }
        }
        
        return null;
    }

    protected function parseRelatedLinks($relatedLinksData)
    {
        if (is_null($relatedLinksData)) {
            return null;
        }
        
        if (is_array($relatedLinksData)) {
            return $relatedLinksData;
        }
        
        if (is_string($relatedLinksData)) {
            $decoded = json_decode($relatedLinksData, true);
            return is_array($decoded) ? $decoded : null;
        }
        
        return null;
    }
}
