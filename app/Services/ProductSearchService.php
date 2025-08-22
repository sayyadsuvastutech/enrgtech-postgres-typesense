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
            ->select([
                'id', 'name', 'slug', 'sku', 'price', 'stock_quantity',
                'status', 'category_id', 'brand_id', 'manufacturer_id',
                'category_name', 'brand_name', 'manufacturer_name', 'attributes',
            ])
            ->where('status', 'active');

        // Apply advanced semantic search with multi-factor scoring
        if (! empty($params['search'])) {
            $searchTerm = trim($params['search']);

            if (strlen($searchTerm) >= 2) {
                $query = $this->applySemanticSearch($query, $searchTerm);
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
        $this->applySorting($query, $params['sort_by'] ?? 'relevance', $params['sort_order'] ?? 'desc', $params['search'] ?? null);

        return $query;
    }

    /**
     * Apply semantic search with intelligent query detection
     */
    private function applySemanticSearch(Builder $query, string $searchTerm): Builder
    {

        $phrase = trim($searchTerm, '"\'');
        return $query->semanticSearch($phrase);

        $searchType = $this->detectSearchType($searchTerm);

        switch ($searchType) {
            case 'exact_phrase':
                // Remove quotes and use phrase search
                $phrase = trim($searchTerm, '"\'');
                return $query->phraseSearch($phrase);

            case 'google_operators':
                // Contains OR, AND, -, or other operators
                return $query->webSearch($searchTerm);

            case 'electrical_spec':
                // Contains electrical specifications (numbers + units)
                return $query->semanticSearch($searchTerm);

            case 'fuzzy_needed':
                // Short terms or potential typos
                return $this->combinedFuzzySearch($query, $searchTerm);

            default:
                // Standard semantic search
                return $query->semanticSearch($searchTerm);
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

        // Check for electrical specifications
        if (preg_match('/\d+\s*[AVWΩavwω]|\d+\s*(amp|volt|watt|ohm)/i', $term)) {
            return 'electrical_spec';
        }

        // Check if fuzzy search might be needed (short terms, potential typos)
        if (strlen($term) <= 4 || !preg_match('/[aeiou]/i', $term)) {
            return 'fuzzy_needed';
        }

        return 'semantic';
    }

    /**
     * Combined fuzzy search with fallback strategies
     */
    private function combinedFuzzySearch(Builder $query, string $searchTerm): Builder
    {
        return $query->selectRaw("
            products.*,
            GREATEST(
                semantic_search_score(?, name, sku, description, category_name, brand_name, search_vector),
                similarity(name, ?) * 8.0,
                similarity(sku, ?) * 10.0,
                CASE WHEN levenshtein(name, ?) <= 3 THEN 6.0 ELSE 0.0 END,
                CASE WHEN levenshtein(sku, ?) <= 2 THEN 8.0 ELSE 0.0 END
            ) as combined_score
        ", [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm])
        ->whereRaw("
            semantic_search_match(?, name, sku, description, search_vector) OR
            similarity(name, ?) > 0.3 OR
            similarity(sku, ?) > 0.3 OR
            levenshtein(name, ?) <= 3 OR
            levenshtein(sku, ?) <= 2
        ", [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm])
        ->orderBy('combined_score', 'desc');
    }

    private function applySorting(Builder $query, string $sortBy, string $sortOrder, ?string $searchTerm = null): void
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
            // Use the PostgreSQL function for intelligent suggestions
            $suggestions = DB::select(
                "SELECT suggestion, score, type FROM get_search_suggestions(?, ?)",
                [$term, $limit]
            );

            return array_map(function ($suggestion) {
                return [
                    'text' => $suggestion->suggestion,
                    'type' => $suggestion->type,
                    'score' => $suggestion->score,
                ];
            }, $suggestions);
        });
    }

    /**
     * Get advanced search analytics
     */
    public function getSearchAnalytics(string $searchTerm): array
    {
        $analytics = [
            'search_type' => $this->detectSearchType($searchTerm),
            'electrical_specs' => $this->extractElectricalSpecs($searchTerm),
            'term_length' => strlen(trim($searchTerm)),
            'word_count' => str_word_count($searchTerm),
            'has_operators' => preg_match('/\b(OR|AND)\b|-\w+/', $searchTerm) ? true : false,
            'has_quotes' => preg_match('/["\']/', $searchTerm) ? true : false,
        ];

        return $analytics;
    }

    /**
     * Extract electrical specifications from search term
     */
    private function extractElectricalSpecs(string $searchTerm): array
    {
        $specs = [];

        // Extract amperage specifications
        if (preg_match_all('/(\d+(?:\.\d+)?)\s*(?:A|amp|ampere)s?/i', $searchTerm, $matches)) {
            $specs['amperage'] = array_map('floatval', $matches[1]);
        }

        // Extract voltage specifications
        if (preg_match_all('/(\d+(?:\.\d+)?)\s*(?:V|volt)s?/i', $searchTerm, $matches)) {
            $specs['voltage'] = array_map('floatval', $matches[1]);
        }

        // Extract wattage specifications
        if (preg_match_all('/(\d+(?:\.\d+)?)\s*(?:W|watt)s?/i', $searchTerm, $matches)) {
            $specs['wattage'] = array_map('floatval', $matches[1]);
        }

        // Extract resistance specifications
        if (preg_match_all('/(\d+(?:\.\d+)?)\s*(?:Ω|ω|ohm)s?/i', $searchTerm, $matches)) {
            $specs['resistance'] = array_map('floatval', $matches[1]);
        }

        return $specs;
    }
}
