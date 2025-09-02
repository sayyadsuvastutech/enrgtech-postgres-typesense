<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Manufacturer;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Scout\Builder as ScoutBuilder;

class ProductSearchService
{
    private const CACHE_TTL = 300; // 5 minutes

    private const FILTER_CACHE_TTL = 1800; // 30 minutes

    private const TYPESENSE_TIMEOUT = 3; // seconds

    public function search(array $params): LengthAwarePaginator
    {
        $startTime = microtime(true);

        try {
            // Attempt Typesense search first
            if (! empty($params['search'])) {
                $result = $this->performTypesenseSearch($params);

                if ($result) {
                    $this->logSearchPerformance('typesense', $startTime, $result->total());

                    return $result;
                }
            }

            // Fallback to PostgreSQL search
            $result = $this->performPostgresSearch($params);
            $this->logSearchPerformance('postgres', $startTime, $result->total());

            return $result;

        } catch (\Exception $e) {
            Log::warning('Search service error', [
                'params' => $params,
                'error' => $e->getMessage(),
            ]);

            // Final fallback
            return $this->performBasicSearch($params);
        }
    }

    private function performTypesenseSearch(array $params): ?LengthAwarePaginator
    {
        try {
            $scoutBuilder = $this->buildTypesenseQuery($params);

            // Get raw search results with metadata
            $typesenseResults = $scoutBuilder->raw();

            if (! $typesenseResults || ! isset($typesenseResults['hits'])) {
                return null;
            }

            // Extract product IDs and their scores
            $productIds = [];
            $scores = [];

            foreach ($typesenseResults['hits'] as $hit) {
                $productId = $hit['document']['id'];
                $productIds[] = $productId;
                $scores[$productId] = $hit['text_match_info']['best_field_score'] ?? 0;
            }

            if (empty($productIds)) {
                return null;
            }

            // Fetch full products with relationships, maintaining search order
            $products = Product::whereIn('id', $productIds)
                ->with(['category', 'brand', 'manufacturer', 'prices', 'quantities', 'attributes', 'images'])
                ->get()
                ->sortBy(function ($product) use ($productIds) {
                    return array_search($product->id, $productIds);
                });

            // Create paginated result
            $perPage = $params['per_page'] ?? 20;
            $page = $params['page'] ?? 1;
            $total = $typesenseResults['found'] ?? $products->count();

            $paginatedProducts = new LengthAwarePaginator(
                $products->forPage($page, $perPage),
                $total,
                $perPage,
                $page,
                ['path' => request()->url(), 'query' => request()->query()]
            );

            // Add search metadata
            $paginatedProducts->appends([
                'facet_counts' => $typesenseResults['facet_counts'] ?? [],
                'search_time_ms' => $typesenseResults['search_time_ms'] ?? 0,
                'search_cutoff' => $typesenseResults['search_cutoff'] ?? false,
            ]);

            return $paginatedProducts;

        } catch (\Exception $e) {
            Log::info('Typesense search failed, falling back to PostgreSQL', [
                'error' => $e->getMessage(),
                'params' => $params,
            ]);

            return null;
        }
    }

    private function buildTypesenseQuery(array $params): ScoutBuilder
    {
        $searchTerm = $params['search'] ?? '*';

        $scoutBuilder = Product::search($searchTerm);

        // Configure search parameters
        $searchParams = $this->buildSearchParameters($params);

        foreach ($searchParams as $param => $value) {
            $scoutBuilder->options([$param => $value]);
        }

        // Apply filters
        $this->applyTypesenseFilters($scoutBuilder, $params);

        // Set pagination
        $perPage = $params['per_page'] ?? 20;
        $page = $params['page'] ?? 1;
        $scoutBuilder->take($perPage);

        return $scoutBuilder;
    }

    private function buildSearchParameters(array $params): array
    {
        $searchParams = [
            'query_by' => 'title,name,pnum,mf_pnum,description,category_name,manufacturer_name,brand_name,breadcrumb,searchable_attributes',
            'query_by_weights' => '4,4,3,3,2,1,1,1,1,1',
            'prefix' => 'true,true,true,true,false,false,false,false,false,false',
            'infix' => 'off,off,fallback,fallback,fallback,fallback,fallback,fallback,fallback,fallback',
            'typo_tokens_threshold' => 1,
            'drop_tokens_threshold' => 1,
            'num_typos' => 2,
            'sort_by' => $this->getTypesenseSortBy($params),
            'facet_by' => 'category_name,brand_name,manufacturer_name,attributes,sources,in_stock,is_rohs_compliant',
            'max_facet_values' => 100,
            'facet_query' => '',
            'page' => $params['page'] ?? 1,
            'per_page' => $params['per_page'] ?? 20,
        ];

        // Advanced search configurations
        if (! empty($params['search'])) {
            $searchType = $this->detectSearchType($params['search']);

            switch ($searchType) {
                case 'exact_phrase':
                    $searchParams['query_by_weights'] = '1,1,1,1,1,1,1,1,1,1';
                    $searchParams['prefix'] = 'false,false,false,false,false,false,false,false,false,false';
                    break;

                case 'product_number':
                    $searchParams['query_by'] = 'pnum,mf_pnum,title,name';
                    $searchParams['query_by_weights'] = '10,10,1,1';
                    $searchParams['prefix'] = 'true,true,true,true';
                    break;
            }
        }

        return $searchParams;
    }

    private function applyTypesenseFilters(ScoutBuilder $scoutBuilder, array $params): void
    {
        $filters = [];

        // Category filter
        if (! empty($params['categories']) && is_array($params['categories'])) {
            $categoryIds = array_filter($params['categories']);
            if (! empty($categoryIds)) {
                $filters[] = 'category_id:=['.implode(',', $categoryIds).']';
            }
        }

        // Brand filter
        if (! empty($params['brands']) && is_array($params['brands'])) {
            $brandIds = array_filter($params['brands']);
            if (! empty($brandIds)) {
                $filters[] = 'brand_id:=['.implode(',', $brandIds).']';
            }
        }

        // Manufacturer filter
        if (! empty($params['manufacturers']) && is_array($params['manufacturers'])) {
            $manufacturerIds = array_filter($params['manufacturers']);
            if (! empty($manufacturerIds)) {
                $filters[] = 'manufacturer_id:=['.implode(',', $manufacturerIds).']';
            }
        }

        // Price range filter
        if (isset($params['min_price']) || isset($params['max_price'])) {
            $minPrice = $params['min_price'] ?? 0;
            $maxPrice = $params['max_price'] ?? 999999;
            $filters[] = "price:[{$minPrice}..{$maxPrice}]";
        }

        // Stock filter
        if (! empty($params['in_stock'])) {
            $filters[] = 'in_stock:true';
        }

        // RoHS compliance filter
        if (isset($params['rohs_compliant'])) {
            $rohs = $params['rohs_compliant'] ? 'true' : 'false';
            $filters[] = "is_rohs_compliant:{$rohs}";
        }

        // Minimum rating filter
        if (isset($params['min_rating'])) {
            $filters[] = "average_rating:>={$params['min_rating']}";
        }

        // Attribute filters
        if (! empty($params['attributes']) && is_array($params['attributes'])) {
            foreach ($params['attributes'] as $attr) {
                if (! empty($attr)) {
                    $filters[] = "attributes:={$attr}";
                }
            }
        }

        if (! empty($filters)) {
            $scoutBuilder->options(['filter_by' => implode(' && ', $filters)]);
        }
    }

    private function getTypesenseSortBy(array $params): string
    {
        $sortBy = $params['sort_by'] ?? 'relevance';
        $sortOrder = $params['sort_order'] ?? 'desc';

        switch ($sortBy) {
            case 'price':
                return "price:{$sortOrder}";
            case 'name':
                return "name:{$sortOrder}";
            case 'rating':
                return "average_rating:{$sortOrder}";
            case 'newest':
                return 'created_at:desc';
            case 'stock':
                return "stock_quantity:{$sortOrder}";
            case 'relevance':
            default:
                return '_text_match:desc,created_at:desc';
        }
    }

    private function detectSearchType(string $searchTerm): string
    {
        $term = trim($searchTerm);

        // Check for exact phrase search (quoted)
        if (preg_match('/^["\'].*["\']$/', $term)) {
            return 'exact_phrase';
        }

        // Check for product number patterns
        if (preg_match('/^[A-Z0-9\-_]+$/i', $term) && strlen($term) >= 3) {
            return 'product_number';
        }

        // Check for Google-like operators
        if (preg_match('/\b(OR|AND)\b|-\w+/', $term)) {
            return 'google_operators';
        }

        return 'semantic';
    }

    private function performPostgresSearch(array $params): LengthAwarePaginator
    {
        $query = $this->buildOptimizedQuery($params);

        return $query->paginate($params['per_page'] ?? 20);
    }

    private function performBasicSearch(array $params): LengthAwarePaginator
    {
        $query = Product::query()
            ->with(['category', 'brand', 'manufacturer', 'prices', 'quantities', 'attributes', 'images'])
            ->where('status_id', 2);

        if (! empty($params['search'])) {
            $query->where('name', 'ILIKE', '%'.$params['search'].'%');
        }

        return $query->simplePaginate($params['per_page'] ?? 20);
    }

    private function logSearchPerformance(string $engine, float $startTime, int $resultCount): void
    {
        $duration = round((microtime(true) - $startTime) * 1000, 2);

        Log::info('Search performance', [
            'engine' => $engine,
            'duration_ms' => $duration,
            'result_count' => $resultCount,
        ]);
    }

    public function getAdvancedFilterOptions(): array
    {
        return Cache::remember('advanced_product_filter_options', self::FILTER_CACHE_TTL, function () {
            return [
                'categories' => $this->getCachedCategories(),
                'brands' => $this->getCachedBrands(),
                'manufacturers' => $this->getCachedManufacturers(),
                'price_range' => $this->getPriceRange(),
                'attributes' => $this->getPopularAttributes(),
                'sources' => $this->getAvailableSources(),
                'rating_ranges' => [
                    ['min' => 4.0, 'max' => 5.0, 'label' => '4+ Stars'],
                    ['min' => 3.0, 'max' => 4.0, 'label' => '3+ Stars'],
                    ['min' => 2.0, 'max' => 3.0, 'label' => '2+ Stars'],
                    ['min' => 1.0, 'max' => 2.0, 'label' => '1+ Stars'],
                ],
            ];
        });
    }

    private function getPopularAttributes(): array
    {
        return DB::table('ioa_product_attributes')
            ->join('ioa_products', 'ioa_products.id', '=', 'ioa_product_attributes.product_id')
            ->where('ioa_products.status_id', 2)
            ->selectRaw('
                jsonb_object_keys(attributes) as attr_key,
                COUNT(*) as usage_count
            ')
            ->groupBy('attr_key')
            ->orderByDesc('usage_count')
            ->limit(50)
            ->get()
            ->map(function ($item) {
                return [
                    'key' => $item->attr_key,
                    'count' => $item->usage_count,
                ];
            })
            ->toArray();
    }

    private function getAvailableSources(): array
    {
        return DB::table('ioa_product_prices')
            ->join('ioa_products', 'ioa_products.id', '=', 'ioa_product_prices.product_id')
            ->where('ioa_products.status_id', 2)
            ->select('source_name')
            ->selectRaw('COUNT(*) as product_count')
            ->groupBy('source_name')
            ->orderByDesc('product_count')
            ->get()
            ->map(function ($item) {
                return [
                    'name' => $item->source_name,
                    'count' => $item->product_count,
                ];
            })
            ->toArray();
    }

    public function getSearchSuggestions(string $query, int $limit = 10): array
    {
        $cacheKey = 'search_suggestions:'.md5($query);

        return Cache::remember($cacheKey, 300, function () use ($query, $limit) {
            // Use Typesense for auto-complete suggestions
            try {
                $suggestions = Product::search($query)
                    ->options([
                        'query_by' => 'name,title,pnum,mf_pnum',
                        'prefix' => 'true,true,true,true',
                        'limit' => $limit,
                        'facet_by' => 'category_name,brand_name',
                    ])
                    ->take($limit)
                    ->get(['id', 'name', 'title', 'category_name', 'brand_name'])
                    ->map(function ($product) {
                        return [
                            'id' => $product->id,
                            'text' => $product->name ?? $product->title,
                            'category' => $product->category_name,
                            'brand' => $product->brand_name,
                        ];
                    })
                    ->toArray();

                return $suggestions;

            } catch (\Exception $e) {
                // Fallback to PostgreSQL suggestions
                return Product::query()
                    ->where('status_id', 2)
                    ->where(function ($q) use ($query) {
                        $q->where('name', 'ILIKE', $query.'%')
                            ->orWhere('title', 'ILIKE', $query.'%')
                            ->orWhere('pnum', 'ILIKE', $query.'%');
                    })
                    ->select('id', 'name', 'title', 'category_name', 'brand_name')
                    ->limit($limit)
                    ->get()
                    ->map(function ($product) {
                        return [
                            'id' => $product->id,
                            'text' => $product->name ?? $product->title,
                            'category' => $product->category_name,
                            'brand' => $product->brand_name,
                        ];
                    })
                    ->toArray();
            }
        });
    }

    private function buildOptimizedQuery(array $params): Builder
    {
        $query = Product::query()
            ->with(['category', 'brand', 'manufacturer', 'prices', 'quantities', 'attributes', 'images'])
            ->where('status_id', 2); // Assuming status_id 2 is active

        // Apply category filter
        if (! empty($params['categories']) && is_array($params['categories'])) {
            $query->whereIn('category_id', array_filter($params['categories']));
        }

        // Apply brand filter
        if (! empty($params['brands']) && is_array($params['brands'])) {
            $query->whereIn('brand_id', array_filter($params['brands']));
        }

        // Apply manufacturer filter
        if (! empty($params['manufacturers']) && is_array($params['manufacturers'])) {
            $query->whereIn('manufacturer_id', array_filter($params['manufacturers']));
        }

        // Apply price range filter
        if (isset($params['min_price']) && $params['min_price'] !== null) {
            $query->whereHas('prices', function ($q) use ($params) {
                $q->whereRaw("(pricing_ranges->0->>'price')::numeric >= ?", [$params['min_price']]);
            });
        }
        if (isset($params['max_price']) && $params['max_price'] !== null) {
            $query->whereHas('prices', function ($q) use ($params) {
                $q->whereRaw("(pricing_ranges->0->>'price')::numeric <= ?", [$params['max_price']]);
            });
        }

        // Apply stock filter
        if (! empty($params['in_stock'])) {
            $query->whereHas('quantities', function ($q) {
                $q->where('quantity', '>', 0)
                    ->orWhere('availability_status', '!=', 'out_of_stock');
            });
        }

        // Apply advanced semantic search with multi-factor scoring
        if (! empty($params['search'])) {
            $searchTerm = trim($params['search']);

            if (strlen($searchTerm) >= 2) {
                $query = $this->applySemanticSearch($query, $searchTerm);
            }
        }

        // Apply sorting
        $this->applySorting($query, $params['sort_by'] ?? 'relevance', $params['sort_order'] ?? 'desc', $params['search'] ?? null);

        return $query;
    }

    /**
     * Apply semantic search with intelligent query detection
     */
    private function applySemanticSearch(Builder $query, string $searchTerm): Builder
    {

        $searchType = $this->detectSearchType($searchTerm);

        switch ($searchType) {
            case 'exact_phrase':
                // Remove quotes and use phrase search
                $phrase = trim($searchTerm, '"\'');

                return $query->phraseSearch($phrase);

            case 'google_operators':
                // Contains OR, AND, -, or other operators
                return $query->webSearch($searchTerm);

            default:
                // Standard semantic search
                return $query->search2($searchTerm);
        }
    }

    private function applySorting(Builder $query, string $sortBy, string $sortOrder, ?string $searchTerm = null): void
    {
        switch ($sortBy) {
            case 'name':
                $query->orderBy('name', $sortOrder);
                break;
            case 'price':
                // Order by minimum price from all sources
                $query->leftJoin('ioa_product_prices', 'ioa_products.id', '=', 'ioa_product_prices.product_id')
                    ->orderByRaw("MIN((ioa_product_prices.pricing_ranges->0->>'price')::numeric) $sortOrder")
                    ->groupBy('ioa_products.id');
                break;
            case 'newest':
                $query->orderBy('created_at', 'desc');
                break;
            case 'relevance':
            default:
                // If there's a search term, rely on the search scoring
                // Otherwise, default to name sorting
                if (empty($searchTerm)) {
                    $query->orderBy('name', 'asc');
                }
                // Search methods already apply their own ordering by score
                break;
        }
    }

    public function getFilterOptions(): array
    {
        return Cache::remember('product_filter_options', self::FILTER_CACHE_TTL, function () {
            return [
                'categories' => $this->getCachedCategories(),
                'brands' => $this->getCachedBrands(),
                'manufacturers' => $this->getCachedManufacturers(),
                'price_range' => $this->getPriceRange(),
            ];
        });
    }

    private function getCachedCategories(): array
    {
        return Category::query()
            ->select('id', 'name')
            ->withCount(['products as products_count' => function ($query) {
                $query->where('status_id', 2);
            }])
            ->orderByDesc('products_count')
            ->limit(50)
            ->get()
            ->toArray();
    }

    private function getCachedBrands(): array
    {
        return Brand::query()
            ->select('id', 'name')
            ->withCount(['products as products_count' => function ($query) {
                $query->where('status_id', 2);
            }])
            ->orderByDesc('products_count')
            ->limit(50)
            ->get()
            ->toArray();
    }

    private function getCachedManufacturers(): array
    {
        return Manufacturer::query()
            ->select('id', 'name')
            ->withCount(['products as products_count' => function ($query) {
                $query->where('status_id', 2);
            }])
            ->orderByDesc('products_count')
            ->limit(50)
            ->get()
            ->toArray();
    }

    private function getPriceRange(): array
    {
        $result = DB::table('ioa_product_prices')
            ->join('ioa_products', 'ioa_products.id', '=', 'ioa_product_prices.product_id')
            ->where('ioa_products.status_id', 2)
            ->selectRaw("
                MIN((ioa_product_prices.pricing_ranges->0->>'price')::numeric) as min_price,
                MAX((ioa_product_prices.pricing_ranges->0->>'price')::numeric) as max_price
            ")
            ->first();

        return [
            'min' => $result->min_price ?? 0,
            'max' => $result->max_price ?? 1000,
        ];
    }

    public function clearCache(): void
    {
        Cache::forget('product_filter_options');
    }
}
