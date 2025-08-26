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
            ->where('status_id', 1); // Assuming status_id 1 is active



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
                $q->where(function ($subQ) use ($params) {
                    $subQ->whereRaw("(pricing_data->>'unit_price')::numeric >= ?", [$params['min_price']])
                        ->orWhereRaw("(pricing_data->'ranges'->0->>'price')::numeric >= ?", [$params['min_price']])
                        ->orWhereRaw("(pricing_data->>'price')::numeric >= ?", [$params['min_price']]);
                });
            });
        }
        if (isset($params['max_price']) && $params['max_price'] !== null) {
            $query->whereHas('prices', function ($q) use ($params) {
                $q->where(function ($subQ) use ($params) {
                    $subQ->whereRaw("(pricing_data->>'unit_price')::numeric <= ?", [$params['max_price']])
                        ->orWhereRaw("(pricing_data->'ranges'->0->>'price')::numeric <= ?", [$params['max_price']])
                        ->orWhereRaw("(pricing_data->>'price')::numeric <= ?", [$params['max_price']]);
                });
            });
        }

        // Apply stock filter
        if (!empty($params['in_stock'])) {
            $query->whereHas('quantities', function ($q) {
                $q->where(function ($subQ) {
                    $subQ->whereRaw("(quantity_data->>'quantity')::int > 0")
                        ->orWhereRaw("(quantity_data->>'available')::int > 0");
                });
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
                return $query->search($searchTerm);
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
                // Order by minimum price from all sources - handle multiple price structures
                $query->leftJoin('product_prices', 'products.id', '=', 'product_prices.product_id')
                    ->orderByRaw("MIN(
                        COALESCE(
                            (product_prices.pricing_data->>'unit_price')::numeric,
                            (product_prices.pricing_data->'ranges'->0->>'price')::numeric,
                            (product_prices.pricing_data->>'price')::numeric
                        )
                    ) $sortOrder")
                    ->groupBy('products.id');
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
                $query->where('status_id', 1);
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
                $query->where('status_id', 1);
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
                $query->where('status_id', 1);
            }])
            ->orderByDesc('products_count')
            ->limit(50)
            ->get()
            ->toArray();
    }

    private function getPriceRange(): array
    {
        $result = DB::table('product_prices')
            ->join('products', 'products.id', '=', 'product_prices.product_id')
            ->where('products.status_id', 1)
            ->selectRaw("
                MIN(
                    COALESCE(
                        (product_prices.pricing_data->>'unit_price')::numeric,
                        (product_prices.pricing_data->'ranges'->0->>'price')::numeric,
                        (product_prices.pricing_data->>'price')::numeric
                    )
                ) as min_price, 
                MAX(
                    COALESCE(
                        (product_prices.pricing_data->>'unit_price')::numeric,
                        (product_prices.pricing_data->'ranges'->0->>'price')::numeric,
                        (product_prices.pricing_data->>'price')::numeric
                    )
                ) as max_price
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
