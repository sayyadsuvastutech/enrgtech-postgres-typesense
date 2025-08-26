<?php

use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Services\ProductSearchService;
use Illuminate\Pagination\LengthAwarePaginator;

new #[Layout('components.layouts.public')]
class extends Component {
    use WithPagination;

    // Search properties

    #[\Livewire\Attributes\Url]
    public string $search = '';

    #[\Livewire\Attributes\Url]
    public array $selectedCategories = [];

    #[\Livewire\Attributes\Url]
    public array $selectedBrands = [];

    #[\Livewire\Attributes\Url]
    public array $selectedManufacturers = [];

    #[\Livewire\Attributes\Url]
    public ?float $minPrice = null;

    #[\Livewire\Attributes\Url]
    public ?float $maxPrice = null;

    #[\Livewire\Attributes\Url]
    public string $sortBy = 'relevance';

    #[\Livewire\Attributes\Url]
    public string $sortOrder = 'desc';

    #[\Livewire\Attributes\Url]
    public bool $inStock = false;

    // Cached data
    public array $filterOptions = [];

    // Price presets
    public array $pricePresets = [
        ['min' => 0, 'max' => 50, 'label' => '$0 - $50'],
        ['min' => 50, 'max' => 200, 'label' => '$50 - $200'],
        ['min' => 200, 'max' => 500, 'label' => '$200 - $500'],
        ['min' => 500, 'max' => 1000, 'label' => '$500 - $1,000'],
        ['min' => 1000, 'max' => null, 'label' => '$1,000+'],
    ];

    public function mount(): void
    {
        $searchService = app(ProductSearchService::class);
//        $searchService->clearCache();
        $this->filterOptions = $searchService->getFilterOptions();
    }

    private function getSearchParams(): array
    {
        return [
            'search' => $this->search,
            'categories' => $this->selectedCategories,
            'brands' => $this->selectedBrands,
            'manufacturers' => $this->selectedManufacturers,
            'min_price' => $this->minPrice,
            'max_price' => $this->maxPrice,
            'sort_by' => $this->sortBy,
            'sort_order' => $this->sortOrder,
            'in_stock' => $this->inStock,
            'per_page' => 10,
            'page' => $this->getPage(),
        ];
    }

    public function performSearch(): void
    {
        $this->resetPage();
    }

    public function updatedSelectedCategories(): void
    {
        $this->resetPage();
    }

    public function updatedSelectedBrands(): void
    {
        $this->resetPage();
    }

    public function updatedSelectedManufacturers(): void
    {
        $this->resetPage();
    }

    public function updatedMinPrice(): void
    {
        $this->resetPage();
    }

    public function updatedMaxPrice(): void
    {
        $this->resetPage();
    }

    public function updatedSortBy(): void
    {
        // No need to reset page for sorting
    }

    public function setPriceRange($min, $max): void
    {
        $this->minPrice = $min;
        $this->maxPrice = $max;
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset([
            'selectedCategories',
            'selectedBrands',
            'selectedManufacturers',
            'minPrice',
            'maxPrice',
            'inStock'
        ]);
        $this->resetPage();
    }

    #[\Livewire\Attributes\Computed]
    public function getProducts()
    {
        $searchService = app(ProductSearchService::class);
        return $searchService->search($this->getSearchParams());
    }
}; ?>

<div class="bg-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

        <!-- Search Header -->
        <div class="mb-8">
            <div class="text-center mb-8">
                <h1 class="text-3xl font-bold text-gray-900 mb-2">Find Your Products</h1>
                <p class="text-gray-600">Search through our extensive catalog of products</p>
            </div>

            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                <!-- Search Input -->
                <div class="flex-1 max-w-2xl mx-auto lg:mx-0 relative">
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                        </div>
                        <div class="flex">
                            <input type="text"
                                   wire:model="search"
                                   wire:keydown.enter="performSearch"
                                   placeholder="Search products, SKUs, models..."
                                   class="flex-1 pl-10 pr-3 py-3 border border-gray-300 rounded-l-lg text-lg text-gray-900 placeholder-gray-500 bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent focus:z-10">
                            <button wire:click="performSearch"
                                    type="button"
                                    class="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-r-lg border border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-0 transition-colors duration-200">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                </svg>
                            </button>
                        </div>

                        @if($search)
                            <button wire:click="$set('search', '')"
                                    class="absolute right-20 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600 transition-colors">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        @endif
                    </div>
                </div>

                <!-- Sort Controls -->
                <div class="flex items-center justify-center lg:justify-end">
                    <select wire:model.live="sortBy"
                            class="block border border-gray-300 rounded-md shadow-sm py-2 px-3 bg-white text-gray-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm">
                        <option value="relevance">Best Match</option>
                        <option value="name">Name A-Z</option>
                        <option value="price">Price</option>
                        <option value="newest">Newest</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="flex flex-col lg:flex-row gap-8">
            <!-- Filters Sidebar -->
            <div class="w-full lg:w-64">
                <div
                    class="bg-white rounded-lg border border-gray-200 shadow-sm sticky top-24 max-h-[calc(100vh-8rem)] overflow-hidden flex flex-col">
                    <!-- Fixed Header -->
                    <div class="flex items-center justify-between p-6 border-b border-gray-200 flex-shrink-0">
                        <h3 class="text-lg font-semibold text-gray-900">Filters</h3>
                        <button wire:click="clearFilters"
                                class="text-sm text-blue-600 hover:text-blue-800 font-medium">
                            Clear All
                        </button>
                    </div>

                    <!-- Scrollable Content -->
                    <div class="flex-1 overflow-y-auto p-6 space-y-6">
                        <!-- Price Range -->
                        <div>
                            <h4 class="text-sm font-medium text-gray-900 mb-3">Price Range</h4>

                            <!-- Price Presets -->
                            <div class="space-y-2 mb-4">
                                @foreach($pricePresets as $preset)
                                    <button wire:click="setPriceRange({{ $preset['min'] }}, {{ $preset['max'] }})"
                                            class="block w-full text-left px-3 py-2 text-sm rounded-lg border transition-colors {{ ($minPrice == $preset['min'] && $maxPrice == $preset['max']) ? 'bg-blue-50 border-blue-200 text-blue-700' : 'border-gray-200 text-gray-700 hover:bg-gray-50' }}">
                                        {{ $preset['label'] }}
                                    </button>
                                @endforeach
                            </div>

                            <!-- Custom Price Range -->
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 mb-1">Min Price</label>
                                    <input type="number"
                                           wire:model.live.debounce.500ms="minPrice"
                                           min="0"
                                           step="0.01"
                                           placeholder="0"
                                           class="block w-full border border-gray-300 rounded-md shadow-sm py-1 px-2 text-sm text-gray-900 bg-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 mb-1">Max Price</label>
                                    <input type="number"
                                           wire:model.live.debounce.500ms="maxPrice"
                                           min="0"
                                           step="0.01"
                                           placeholder="∞"
                                           class="block w-full border border-gray-300 rounded-md shadow-sm py-1 px-2 text-sm text-gray-900 bg-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                </div>
                            </div>
                        </div>

                        <!-- Categories Filter -->
                        @if(!empty($filterOptions['categories']))
                            <div>
                                <h4 class="text-sm font-medium text-gray-900 mb-3">Categories</h4>
                                <div class="border border-gray-200 rounded-lg max-h-48 overflow-y-auto bg-gray-50">
                                    <div class="p-3 space-y-2">
                                        @foreach($filterOptions['categories'] as $category)
                                            <label
                                                class="flex items-center cursor-pointer hover:bg-white rounded px-2 py-1 transition-colors">
                                                <input type="checkbox"
                                                       wire:model.live="selectedCategories"
                                                       value="{{ $category['id'] }}"
                                                       class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                                                <span class="ml-2 text-sm text-gray-700">{{ $category['name'] }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        @endif

                        <!-- Brands Filter -->
                        @if(!empty($filterOptions['brands']))
                            <div>
                                <h4 class="text-sm font-medium text-gray-900 mb-3">Brands</h4>
                                <div class="border border-gray-200 rounded-lg max-h-48 overflow-y-auto bg-gray-50">
                                    <div class="p-3 space-y-2">
                                        @foreach($filterOptions['brands'] as $brand)
                                            <label
                                                class="flex items-center cursor-pointer hover:bg-white rounded px-2 py-1 transition-colors">
                                                <input type="checkbox"
                                                       wire:model.live="selectedBrands"
                                                       value="{{ $brand['id'] }}"
                                                       class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                                                <span class="ml-2 text-sm text-gray-700">{{ $brand['name'] }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        @endif

                        <!-- Manufacturers Filter -->
                        @if(!empty($filterOptions['manufacturers']))
                            <div>
                                <h4 class="text-sm font-medium text-gray-900 mb-3">Manufacturers</h4>
                                <div class="border border-gray-200 rounded-lg max-h-48 overflow-y-auto bg-gray-50">
                                    <div class="p-3 space-y-2">
                                        @foreach($filterOptions['manufacturers'] as $manufacturer)
                                            <label
                                                class="flex items-center cursor-pointer hover:bg-white rounded px-2 py-1 transition-colors">
                                                <input type="checkbox"
                                                       wire:model.live="selectedManufacturers"
                                                       value="{{ $manufacturer['id'] }}"
                                                       class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                                                <span
                                                    class="ml-2 text-sm text-gray-700">{{ $manufacturer['name'] }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Results -->
            <div class="flex-1">
                <!-- Results Header -->
                <div class="flex items-center justify-between mb-6">
                    <div class="text-sm text-gray-600">
                        <span wire:loading
                              wire:target="search,selectedCategories,selectedBrands,selectedManufacturers,minPrice,maxPrice,inStock"
                              class="text-blue-600 flex items-center">
                            <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-blue-600"
                                 xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                        stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor"
                                      d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Searching...
                        </span>
                    </div>

                </div>

                <!-- Products Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4"
                     wire:loading.class="opacity-50">
                    @forelse($this->getProducts as $product)
                        <a href="{{ route('products.show', ['product' => $product, 'search' => $search]) }}"
                            class="bg-white rounded-lg border border-gray-200 shadow-sm hover:shadow-md transition-all duration-200 overflow-hidden group cursor-pointer">
                            <!-- Product Image -->
                            <div class="aspect-square bg-gray-50 relative">
                                <img src="/images/place_holder.svg"
                                     alt="{{ $product->name }}"
                                     class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-200"
                                     loading="lazy">

                                <!-- Stock Badge -->
                                @if($product->stock_quantity <= 0)
                                    <div class="absolute top-2 right-2">
                                        <span
                                            class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                            Out of Stock
                                        </span>
                                    </div>
                                @endif
                            </div>

                            <!-- Product Info -->
                            <div class="p-4 space-y-2">
                                <!-- Category & Brand -->
                                <div class="text-xs text-gray-500">
                                    <span>{{ $product->category_name }}</span>
                                    @if($product->brand_name)
                                        <span> • {{ $product->brand_name }}</span>
                                    @endif
                                </div>

                                <!-- Product Name -->
                                <h3 class="font-semibold text-gray-900 line-clamp-2 text-sm leading-5 group-hover:text-blue-600 transition-colors">
                                    {{ $product->name }}
                                </h3>

                                <!-- SKU -->
                                <div class="text-xs text-gray-600 font-mono">
                                    SKU: {{ $product->sku }}
                                </div>

                                <!-- Price & Stock -->
                                <div class="flex items-center justify-between pt-2 border-t border-gray-100">
                                    <div class="text-lg font-bold text-gray-900">
                                        ${{ number_format($product->price, 2) }}
                                    </div>
                                    <div class="text-xs text-gray-600">
                                        {{ $product->stock_quantity > 0 ? $product->stock_quantity . ' in stock' : 'Out of stock' }}
                                    </div>
                                </div>
                            </div>
                        </a>
                    @empty
                        <div class="col-span-full text-center py-12">
                            <svg class="h-12 w-12 text-gray-400 mx-auto mb-4" fill="none" viewBox="0 0 24 24"
                                 stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                            <h3 class="text-lg font-medium text-gray-900 mb-2">No products found</h3>
                            <p class="text-gray-500 mb-4">Try adjusting your search criteria or clearing some
                                filters.</p>
                            <button wire:click="clearFilters"
                                    class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                Clear Filters
                            </button>
                        </div>
                    @endforelse
                </div>

                <!-- Pagination -->
                @if($this->getProducts->hasPages())
                    <div class="mt-8">
                        {{ $this->getProducts->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
