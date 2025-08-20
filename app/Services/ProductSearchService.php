<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Manufacturer;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;

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
            ->select([
                'id', 'name', 'slug', 'sku', 'price', 'stock_quantity',
                'status', 'category_id', 'brand_id', 'manufacturer_id',
                'category_name', 'brand_name', 'manufacturer_name', 'attributes',
            ])
            ->where('status', 'active');

        // Apply search filter - simplified for performance
        if (! empty($params['search'])) {
            $searchTerm = trim($params['search']);

            if (strlen($searchTerm) >= 2) {
                $query->searchWithRank($searchTerm);
            }
        }

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
            $query->where('price', '>=', $params['min_price']);
        }
        if (isset($params['max_price']) && $params['max_price'] !== null) {
            $query->where('price', '<=', $params['max_price']);
        }

        // Apply stock filter
        if (! empty($params['in_stock'])) {
            $query->where('stock_quantity', '>', 0);
        }

        // Apply sorting
        $this->applySorting($query, $params['sort_by'] ?? 'relevance', $params['sort_order'] ?? 'desc');

        return $query;
    }

    private function applySorting(Builder $query, string $sortBy, string $sortOrder): void
    {
        switch ($sortBy) {
            case 'name':
                $query->orderBy('name', $sortOrder);
                break;
            case 'price':
                $query->orderBy('price', $sortOrder);
                break;
            case 'newest':
                $query->orderBy('created_at', 'desc');
                break;
            case 'relevance':
            default:
                $query->orderBy('name', 'asc');
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
        return Cache::remember('filter_categories', self::FILTER_CACHE_TTL, function () {
            return Category::query()
                ->select('id', 'name')
                ->whereHas('products', function ($query) {
                    $query->where('status', 'active');
                })
                ->orderBy('name')
                ->limit(50)
                ->get()
                ->toArray();
        });
    }

    private function getCachedBrands(): array
    {
        return Cache::remember('filter_brands', self::FILTER_CACHE_TTL, function () {
            return Brand::query()
                ->select('id', 'name')
                ->whereHas('products', function ($query) {
                    $query->where('status', 'active');
                })
                ->orderBy('name')
                ->limit(50)
                ->get()
                ->toArray();
        });
    }

    private function getCachedManufacturers(): array
    {
        return Cache::remember('filter_manufacturers', self::FILTER_CACHE_TTL, function () {
            return Manufacturer::query()
                ->select('id', 'name')
                ->whereHas('products', function ($query) {
                    $query->where('status', 'active');
                })
                ->orderBy('name')
                ->limit(50)
                ->get()
                ->toArray();
        });
    }

    private function getPriceRange(): array
    {
        return Cache::remember('product_price_range', self::FILTER_CACHE_TTL, function () {
            $result = Product::query()
                ->where('status', 'active')
                ->selectRaw('MIN(price) as min_price, MAX(price) as max_price')
                ->first();

            return [
                'min' => $result->min_price ?? 0,
                'max' => $result->max_price ?? 1000,
            ];
        });
    }

    public function clearCache(): void
    {
        Cache::forget('product_filter_options');
        Cache::forget('filter_categories');
        Cache::forget('filter_brands');
        Cache::forget('filter_manufacturers');
        Cache::forget('product_price_range');
    }

    public function getSearchSuggestions(string $term, int $limit = 5): array
    {
        if (strlen($term) < 2) {
            return [];
        }

        $cacheKey = 'search_suggestions_'.md5($term);

        return Cache::remember($cacheKey, 300, function () use ($term, $limit) {
            $suggestions = [];

            // Get product name suggestions (limited query)
            $productSuggestions = Product::query()
                ->select('name')
                ->where('status', 'active')
                ->where('name', 'ILIKE', '%'.$term.'%')
                ->distinct()
                ->limit($limit)
                ->pluck('name')
                ->map(fn ($name) => ['type' => 'product', 'text' => $name])
                ->toArray();

            // Get SKU suggestions (limited query)
            $skuSuggestions = Product::query()
                ->select('sku')
                ->where('status', 'active')
                ->where('sku', 'ILIKE', '%'.$term.'%')
                ->distinct()
                ->limit(2)
                ->pluck('sku')
                ->map(fn ($sku) => ['type' => 'sku', 'text' => $sku])
                ->toArray();

            $suggestions = array_merge($productSuggestions, $skuSuggestions);

            return array_slice($suggestions, 0, $limit);
        });
    }
}
