<?php

namespace App\Livewire;

use App\Services\ProductSearchService;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class ProductSearch extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $query = '';

    #[Url]
    public array $filters = [];

    #[Url(as: 'sort')]
    public string $sortBy = 'relevance';

    #[Url(as: 'dir')]
    public string $sortDirection = 'desc';

    #[Url(as: 'view')]
    public string $viewMode = 'grid';

    public array $suggestions = [];

    public bool $showSuggestions = true;

    public array $facets = [];

    public array $filterOptions = [];

    protected ProductSearchService $searchService;

    public function boot(ProductSearchService $searchService): void
    {
        $this->searchService = $searchService;
    }

    public function mount(): void
    {
        $this->filterOptions = $this->searchService->getFilterOptions();
    }

    public function render()
    {
        $searchResults = $this->searchService->searchWithFacets([
            'query' => $this->query,
            'filters' => $this->filters,
            'sort' => $this->sortBy,
            'direction' => $this->sortDirection,
            'per_page' => 20,
        ]);

        $this->facets = $searchResults['facets'];

        return view('livewire.product-search', [
            'results' => $searchResults['results'],
            'total' => $searchResults['total'],
        ]);
    }

    public function updatedQuery(): void
    {
        $this->resetPage();
        $this->updateSuggestions();
    }

    public function updateSuggestions(): void
    {
        if (strlen($this->query) >= 2) {
            $this->suggestions = $this->searchService
                ->getQuickSuggestions($this->query, 8)
                ->toArray();
            $this->showSuggestions = ! empty($this->suggestions);
        } else {
            $this->suggestions = [];
            $this->showSuggestions = false;
        }
    }

    public function hideSuggestions(): void
    {
        $this->showSuggestions = false;
    }

    public function selectSuggestion(array $suggestion): void
    {
        $this->query = $suggestion['title'];
        $this->showSuggestions = false;
        $this->resetPage();
    }

    public function updateFilter(string $filterName, $value): void
    {
        if (empty($value)) {
            unset($this->filters[$filterName]);
        } else {
            $this->filters[$filterName] = $value;
        }

        $this->resetPage();
    }

    public function toggleFilter(string $filterName, $value): void
    {
        if (! isset($this->filters[$filterName])) {
            $this->filters[$filterName] = [];
        }

        if (in_array($value, $this->filters[$filterName])) {
            $this->filters[$filterName] = array_diff($this->filters[$filterName], [$value]);
            if (empty($this->filters[$filterName])) {
                unset($this->filters[$filterName]);
            }
        } else {
            $this->filters[$filterName][] = $value;
        }

        $this->resetPage();
    }

    public function clearFilter(string $filterName): void
    {
        unset($this->filters[$filterName]);
        $this->resetPage();
    }

    public function clearAllFilters(): void
    {
        $this->filters = [];
        $this->resetPage();
    }

    public function updateSort(string $sort, string $direction = 'desc'): void
    {
        $this->sortBy = $sort;
        $this->sortDirection = $direction;
        $this->resetPage();
    }

    public function updateViewMode(string $mode): void
    {
        $this->viewMode = $mode;
    }

    public function getActiveFiltersProperty(): array
    {
        $activeFilters = [];

        foreach ($this->filters as $filterName => $filterValue) {
            if (empty($filterValue)) {
                continue;
            }

            switch ($filterName) {
                case 'categories':
                    if (is_array($filterValue)) {
                        foreach ($filterValue as $categoryId) {
                            $category = collect($this->filterOptions['categories'])
                                ->firstWhere('id', $categoryId);
                            if ($category) {
                                $activeFilters[] = [
                                    'name' => $filterName,
                                    'value' => $categoryId,
                                    'label' => 'Category: '.$category['name'],
                                ];
                            }
                        }
                    }
                    break;

                case 'manufacturers':
                    if (is_array($filterValue)) {
                        foreach ($filterValue as $manufacturerId) {
                            $manufacturer = collect($this->filterOptions['manufacturers'])
                                ->firstWhere('id', $manufacturerId);
                            if ($manufacturer) {
                                $activeFilters[] = [
                                    'name' => $filterName,
                                    'value' => $manufacturerId,
                                    'label' => 'Brand: '.$manufacturer['name'],
                                ];
                            }
                        }
                    }
                    break;

                case 'price_min':
                    $activeFilters[] = [
                        'name' => $filterName,
                        'value' => $filterValue,
                        'label' => 'Min Price: £'.number_format($filterValue, 2),
                    ];
                    break;

                case 'price_max':
                    $activeFilters[] = [
                        'name' => $filterName,
                        'value' => $filterValue,
                        'label' => 'Max Price: £'.number_format($filterValue, 2),
                    ];
                    break;

                case 'in_stock':
                    if ($filterValue) {
                        $activeFilters[] = [
                            'name' => $filterName,
                            'value' => $filterValue,
                            'label' => 'In Stock',
                        ];
                    }
                    break;

                case 'rohs_compliant':
                    if ($filterValue) {
                        $activeFilters[] = [
                            'name' => $filterName,
                            'value' => $filterValue,
                            'label' => 'RoHS Compliant',
                        ];
                    }
                    break;
            }
        }

        return $activeFilters;
    }

    public function formatPriceRange(string $range): string
    {
        return match ($range) {
            'under_1' => 'Under £1',
            '1_to_10' => '£1 - £10',
            '10_to_50' => '£10 - £50',
            '50_to_100' => '£50 - £100',
            'over_100' => 'Over £100',
            default => $range
        };
    }
}
