<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Manufacturer;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ProductSearchService
{
    protected array $filters = [];

    protected string $query = '';

    protected string $sortBy = 'relevance';

    protected string $sortDirection = 'desc';

    protected int $perPage = 20;

    public function search(array $params = []): LengthAwarePaginator
    {
        $this->setSearchParameters($params);

        $query = $this->buildQuery();

        return $query->paginate($this->perPage);
    }

    public function searchWithFacets(array $params = []): array
    {
        $this->setSearchParameters($params);

        $baseQuery = $this->buildBaseQuery();

        // Get paginated results
        $results = $this->applySorting($baseQuery)->paginate($this->perPage);

        // Get facets (aggregations)
        $facets = $this->getFacets($baseQuery);

        return [
            'results' => $results,
            'facets' => $facets,
            'query' => $this->query,
            'filters' => $this->filters,
            'total' => $results->total(),
        ];
    }

    public function getQuickSuggestions(string $query, int $limit = 5): Collection
    {
        if (empty($query) || strlen($query) < 2) {
            return collect();
        }

        return Product::active()
            ->select('name', 'pnum', 'mf_pnum')
            ->where(function ($q) use ($query) {
                $q->where('name', 'LIKE', "{$query}%")
                    ->orWhere('mf_pnum', 'LIKE', "{$query}%")
                    ->orWhere('pnum', 'LIKE', "{$query}%");
            })
            ->limit($limit)
            ->get()
            ->map(function ($product) {
                return [
                    'title' => $product->name,
                    'subtitle' => $product->mf_pnum,
                    'url' => route('products.show', $product->pnum),
                ];
            });
    }

    protected function setSearchParameters(array $params): void
    {
        $this->query = $params['q'] ?? $params['query'] ?? '';
        $this->filters = $params['filters'] ?? [];
        $this->sortBy = $params['sort'] ?? 'relevance';
        $this->sortDirection = $params['direction'] ?? 'desc';
        $this->perPage = min((int) ($params['per_page'] ?? 20), 100);
    }

    protected function buildQuery(): Builder
    {
        return $this->applySorting($this->buildBaseQuery());
    }

    protected function buildBaseQuery(): Builder
    {
        $query = Product::query()
            ->with(['manufacturer', 'category'])
            ->active();

        // Apply text search
        if (! empty($this->query)) {
            $query = $this->applyTextSearch($query, $this->query);
        }

        // Apply filters
        $query = $this->applyFilters($query);

        return $query;
    }

    protected function applyTextSearch(Builder $query, string $searchTerm): Builder
    {
        $terms = explode(' ', trim($searchTerm));

        return $query->where(function ($q) use ($terms) {
            foreach ($terms as $term) {
                $term = trim($term);
                if (empty($term)) {
                    continue;
                }

                $q->where(function ($subQuery) use ($term) {
                    $subQuery->where('name', 'LIKE', "%{$term}%")
                        ->orWhere('description', 'LIKE', "%{$term}%")
                        ->orWhere('mf_pnum', 'LIKE', "%{$term}%")
                        ->orWhere('mf_pnum_norm', 'LIKE', "%{$term}%")
                        ->orWhere('pnum', 'LIKE', "%{$term}%")
                        ->orWhereHas('manufacturer', function ($mfQuery) use ($term) {
                            $mfQuery->where('name', 'LIKE', "%{$term}%");
                        })
                        ->orWhereHas('category', function ($catQuery) use ($term) {
                            $catQuery->where('name', 'LIKE', "%{$term}%");
                        });
                });
            }
        });
    }

    protected function applyFilters(Builder $query): Builder
    {
        foreach ($this->filters as $filterName => $filterValue) {
            if (empty($filterValue)) {
                continue;
            }

            switch ($filterName) {
                case 'category':
                case 'categories':
                    $query = $this->applyCategoryFilter($query, $filterValue);
                    break;

                case 'manufacturer':
                case 'manufacturers':
                    $query = $this->applyManufacturerFilter($query, $filterValue);
                    break;

                case 'price_min':
                    $query = $this->applyPriceMinFilter($query, $filterValue);
                    break;

                case 'price_max':
                    $query = $this->applyPriceMaxFilter($query, $filterValue);
                    break;

                case 'in_stock':
                    if ($filterValue) {
                        $query = $query->inStock();
                    }
                    break;

                case 'rohs_compliant':
                    if ($filterValue) {
                        $query = $query->rohsCompliant();
                    }
                    break;

                case 'source':
                    $query = $query->bySource($filterValue);
                    break;
            }
        }

        return $query;
    }

    protected function applyCategoryFilter(Builder $query, $categories): Builder
    {
        if (! is_array($categories)) {
            $categories = [$categories];
        }

        return $query->whereIn('category_id', $categories);
    }

    protected function applyManufacturerFilter(Builder $query, $manufacturers): Builder
    {
        if (! is_array($manufacturers)) {
            $manufacturers = [$manufacturers];
        }

        return $query->whereIn('manufacturer_id', $manufacturers);
    }

    protected function applyPriceMinFilter(Builder $query, float $minPrice): Builder
    {
        return $query->whereRaw('(pricing_all->\'dk\'->\'ranges\'->0->>\'price\')::numeric >= ?', [$minPrice]);
    }

    protected function applyPriceMaxFilter(Builder $query, float $maxPrice): Builder
    {
        return $query->whereRaw('(pricing_all->\'dk\'->\'ranges\'->0->>\'price\')::numeric <= ?', [$maxPrice]);
    }

    protected function applySorting(Builder $query): Builder
    {
        switch ($this->sortBy) {
            case 'name':
                return $query->orderBy('name', $this->sortDirection);

            case 'price':
                return $query->orderByRaw(
                    '(pricing_all->\'dk\'->\'ranges\'->0->>\'price\')::numeric '.$this->sortDirection
                );

            case 'manufacturer':
                return $query->join('manufacturers', 'products.manufacturer_id', '=', 'manufacturers.id')
                    ->where('manufacturers.is_active', true)
                    ->orderBy('manufacturers.name', $this->sortDirection)
                    ->select('products.*');

            case 'category':
                return $query->join('categories', 'products.category_id', '=', 'categories.id')
                    ->where('categories.is_active', true)
                    ->orderBy('categories.name', $this->sortDirection)
                    ->select('products.*');

            case 'newest':
                return $query->orderBy('created_at', 'desc');

            case 'relevance':
            default:
                if (! empty($this->query)) {
                    return $this->applyRelevanceSort($query);
                }

                return $query->orderBy('created_at', 'desc');
        }
    }

    protected function applyRelevanceSort(Builder $query): Builder
    {
        $searchTerm = $this->query;

        return $query->orderByRaw('
            CASE 
                WHEN name LIKE ? THEN 1
                WHEN mf_pnum LIKE ? THEN 2
                WHEN description LIKE ? THEN 3
                ELSE 4
            END, name ASC
        ', ["%{$searchTerm}%", "%{$searchTerm}%", "%{$searchTerm}%"]);
    }

    protected function getFacets(Builder $baseQuery): array
    {
        // Clone query to avoid affecting main results
        $facetQuery = clone $baseQuery;

        return [
            'categories' => $this->getCategoryFacets($facetQuery),
            'manufacturers' => $this->getManufacturerFacets($facetQuery),
            'price_ranges' => $this->getPriceRangeFacets($facetQuery),
            'availability' => $this->getAvailabilityFacets($facetQuery),
        ];
    }

    protected function getCategoryFacets(Builder $query): Collection
    {
        return $query->select('category_id')
            ->selectRaw('COUNT(*) as product_count')
            ->groupBy('category_id')
            ->reorder('category_id')
            ->orderByRaw('COUNT(*) DESC')
            ->with('category:id,name')
            ->get()
            ->pluck('product_count', 'category.name')
            ->sort()
            ->reverse();
    }

    protected function getManufacturerFacets(Builder $query): Collection
    {
        return $query->select('manufacturer_id')
            ->selectRaw('COUNT(*) as product_count')
            ->groupBy('manufacturer_id')
            ->reorder('manufacturer_id')
            ->orderByRaw('COUNT(*) DESC')
            ->with('manufacturer:id,name')
            ->get()
            ->pluck('product_count', 'manufacturer.name')
            ->sort()
            ->reverse();
    }

    protected function getPriceRangeFacets(Builder $query): array
    {
        $ranges = [
            'under_1' => [0, 1],
            '1_to_10' => [1, 10],
            '10_to_50' => [10, 50],
            '50_to_100' => [50, 100],
            'over_100' => [100, 999999],
        ];

        $facets = [];
        foreach ($ranges as $key => $range) {
            $count = (clone $query)
                ->whereRaw('(pricing_all->\'dk\'->\'ranges\'->0->>\'price\')::numeric >= ?', [$range[0]])
                ->whereRaw('(pricing_all->\'dk\'->\'ranges\'->0->>\'price\')::numeric < ?', [$range[1]])
                ->count();

            if ($count > 0) {
                $facets[$key] = $count;
            }
        }

        return $facets;
    }

    protected function getAvailabilityFacets(Builder $query): array
    {
        $inStock = (clone $query)->inStock()->count();
        $rohsCompliant = (clone $query)->rohsCompliant()->count();

        return [
            'in_stock' => $inStock,
            'rohs_compliant' => $rohsCompliant,
        ];
    }

    public function getFilterOptions(): array
    {
        return [
            'categories' => Category::active()->orderBy('name')->get(['id', 'name']),
            'manufacturers' => Manufacturer::active()->orderBy('name')->get(['id', 'name']),
            'sort_options' => [
                'relevance' => 'Relevance',
                'name' => 'Name',
                'price' => 'Price',
                'manufacturer' => 'Manufacturer',
                'category' => 'Category',
                'newest' => 'Newest First',
            ],
        ];
    }
}
