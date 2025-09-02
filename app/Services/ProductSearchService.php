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

class ProductSearchService
{
    private const CACHE_TTL = 300; // 5 minutes

    private const FILTER_CACHE_TTL = 1800; // 30 minutes

    public function search(array $params)
    {
        if (!empty($params['search'])) {
            $start = microtime(true);
            try {
                $scoutBuilder = Product::search($params['search']);

                // Apply filters to Typesense search
                if (!empty($params['categories']) && is_array($params['categories'])) {
                    $scoutBuilder->whereIn('category_id', array_filter($params['categories']));
                }

                if (!empty($params['brands']) && is_array($params['brands'])) {
                    $scoutBuilder->whereIn('brand_id', array_filter($params['brands']));
                }

                if (!empty($params['manufacturers']) && is_array($params['manufacturers'])) {
                    $scoutBuilder->whereIn('manufacturer_id', array_filter($params['manufacturers']));
                }

                if (isset($params['min_price']) && $params['min_price'] !== null) {
                    $scoutBuilder->where('price', '>= ' . $params['min_price']);
                }

                if (isset($params['max_price']) && $params['max_price'] !== null) {
                    $scoutBuilder->where('price', '<= ' . $params['max_price']);
                }

                if (!empty($params['in_stock'])) {
                    $scoutBuilder->where('in_stock', true);
                }

                $search = $scoutBuilder->take(1000)->get();

                $end = microtime(true);
                $time = $end - $start;

                if ($time > 2 || $search->isEmpty()) {
                    throw new \Exception('Fallback to PGSQL search');
                }

                $ids = $search->pluck('id')->toArray();
                $orderIds = implode(',', $ids);

                $query = Product::whereIn('id', $ids)
                    ->with(['images', 'quantities', 'category', 'prices', 'brand', 'manufacturer', 'attributes'])
                    ->orderByRaw("array_position(ARRAY[{$orderIds}]::bigint[], id)");

                return $query->paginate($params['per_page'] ?? 20);
            } catch (\Exception $e) {
                // Fallback to PGSQL search
                $query = $this->buildOptimizedQuery($params);
                return $query->paginate($params['per_page'] ?? 20);
            }
        }

        $query = $this->buildOptimizedQuery($params);

        return $query->simplePaginate(
            perPage: $params['per_page'] ?? 20,
            page: $params['page'] ?? 1
        );
    }

    private function buildOptimizedQuery(array $params): Builder
    {
        $query = Product::query()
            ->with(['category', 'brand', 'manufacturer', 'prices', 'quantities', 'attributes', 'images'])
            ->where('status_id', 2); // Assuming status_id 2 is active

        // Apply category filter
        if (!empty($params['categories']) && is_array($params['categories'])) {
            $query->whereIn('category_id', array_filter($params['categories']));
        }

        // Apply brand filter
        if (!empty($params['brands']) && is_array($params['brands'])) {
            $query->whereIn('brand_id', array_filter($params['brands']));
        }

        // Apply manufacturer filter
        if (!empty($params['manufacturers']) && is_array($params['manufacturers'])) {
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
        if (!empty($params['in_stock'])) {
            $query->whereHas('quantities', function ($q) {
                $q->where('quantity', '>', 0)
                    ->orWhere('availability_status', '!=', 'out_of_stock');
            });
        }

        // Apply advanced semantic search with multi-factor scoring
        if (!empty($params['search'])) {
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

    /**
     * Detect the type of search query to optimize search strategy
     */
    private function detectSearchType(string $searchTerm): string
    {
        $term = trim($searchTerm);

        // Check for exact phrase search (quoted)
        if (preg_match('/^["\'].*["\']$/', $term)) {
            return 'exact_phrase';
        }

        // Check for Google-like operators
        if (preg_match('/\b(OR|AND)\b|-\w+/', $term)) {
            return 'google_operators';
        }

        return 'semantic';
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
