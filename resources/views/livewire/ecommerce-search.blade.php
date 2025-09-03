<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use App\Services\TypesenseEcommerceSearchService;

new #[Layout('components.layouts.public')]
class extends Component {
    
    #[Url(as: 'q')]
    public string $query = '';
    
    #[Url(as: 'category')]
    public array $selectedCategories = [];
    
    #[Url(as: 'brand')]
    public array $selectedBrands = [];
    
    #[Url(as: 'manufacturer')]
    public array $selectedManufacturers = [];
    
    #[Url(as: 'price_range')]
    public array $selectedPriceRanges = [];
    
    #[Url(as: 'min_price')]
    public ?float $customMinPrice = null;
    
    #[Url(as: 'max_price')]
    public ?float $customMaxPrice = null;
    
    #[Url(as: 'in_stock')]
    public ?bool $inStockOnly = true;
    
    #[Url(as: 'sort')]
    public string $sortBy = 'relevance';
    
    #[Url(as: 'page')]
    public int $currentPage = 1;
    
    public int $perPage = 24;
    public array $searchResults = [];
    public array $facets = [];
    public array $priceRanges = [];
    public bool $isLoading = false;
    public string $error = '';
    
    public function mount(): void
    {
        try {
            $this->priceRanges = $this->getSearchService()->getPriceRanges();
            $this->search();
        } catch (\Exception $e) {
            \Log::error('Mount error in ecommerce search', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->error = 'Unable to load search interface. Please try again later.';
        }
    }
    
    public function search(): void
    {
        $this->isLoading = true;
        $this->error = '';
        
        try {
            $searchService = $this->getSearchService();
            
            $params = [
                'q' => $this->query,
                'category' => $this->selectedCategories,
                'brand' => $this->selectedBrands,
                'manufacturer' => $this->selectedManufacturers,
                'price_range' => $this->selectedPriceRanges,
                'min_price' => $this->customMinPrice,
                'max_price' => $this->customMaxPrice,
                'in_stock' => $this->inStockOnly,
                'sort_by' => $this->sortBy,
                'page' => $this->currentPage,
                'per_page' => $this->perPage,
            ];
            
            $results = $searchService->search($params);
            
            $this->searchResults = $results;
            $this->facets = $results['facets'] ?? [];
            
        } catch (\Exception $e) {
            $this->error = 'Search is temporarily unavailable. Please try again.';
            $this->searchResults = [
                'products' => [],
                'facets' => [],
                'pagination' => ['total' => 0],
                'meta' => ['search_time_ms' => 0]
            ];
            $this->facets = [];
        }
        
        $this->isLoading = false;
    }
    
    public function updatedQuery(): void
    {
        $this->currentPage = 1;
        $this->search();
    }
    
    public function updatedSelectedCategories(): void
    {
        $this->currentPage = 1;
        $this->search();
    }
    
    public function updatedSelectedBrands(): void
    {
        $this->currentPage = 1;
        $this->search();
    }
    
    public function updatedSelectedManufacturers(): void
    {
        $this->currentPage = 1;
        $this->search();
    }
    
    public function updatedSelectedPriceRanges(): void
    {
        $this->currentPage = 1;
        $this->search();
    }
    
    public function updatedCustomMinPrice(): void
    {
        $this->currentPage = 1;
        $this->search();
    }
    
    public function updatedCustomMaxPrice(): void
    {
        $this->currentPage = 1;
        $this->search();
    }
    
    public function updatedInStockOnly(): void
    {
        $this->currentPage = 1;
        $this->search();
    }
    
    public function updatedSortBy(): void
    {
        $this->search();
    }
    
    public function updatedCurrentPage(): void
    {
        $this->search();
    }
    
    public function clearAllFilters(): void
    {
        $this->selectedCategories = [];
        $this->selectedBrands = [];
        $this->selectedManufacturers = [];
        $this->selectedPriceRanges = [];
        $this->customMinPrice = null;
        $this->customMaxPrice = null;
        $this->inStockOnly = null;
        $this->currentPage = 1;
        $this->search();
    }
    
    public function goToPage(int $page): void
    {
        $this->currentPage = $page;
        $this->search();
    }
    
    private function getSearchService(): TypesenseEcommerceSearchService
    {
        return app(TypesenseEcommerceSearchService::class);
    }
    
}; ?>

<div class="bg-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        
        <!-- Search Header -->
        <div class="mb-8">
            <div class="text-center mb-6">
                <h1 class="text-3xl font-bold text-gray-900 mb-2">Ecommerce Product Search</h1>
                <p class="text-gray-600">Advanced faceted search with Typesense</p>
            </div>
            
            <!-- Search Bar -->
            <div class="max-w-3xl mx-auto">
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                    </div>
                    <input type="text"
                           wire:model.live.debounce.300ms="query"
                           placeholder="Search products, SKUs, brands, categories..."
                           class="block w-full pl-10 pr-12 py-3 border border-gray-300 rounded-lg text-lg text-gray-900 placeholder-gray-500 bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                           value="{{ $query }}">
                    
                    @if($query)
                        <button wire:click="$set('query', '')" 
                                class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    @endif
                </div>
            </div>
        </div>

        <div class="flex flex-col lg:flex-row gap-6">
            
            <!-- Filters Sidebar -->
            <div class="w-full lg:w-64 lg:flex-shrink-0">
                <div class="bg-white rounded-lg border border-gray-200 shadow-sm sticky top-4">
                    
                    <!-- Filter Header -->
                    <div class="flex items-center justify-between p-4 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900">Filters</h3>
                        <button wire:click="clearAllFilters" 
                                class="text-sm text-blue-600 hover:text-blue-800 font-medium">
                            Clear All
                        </button>
                    </div>
                    
                    <div class="p-4 space-y-6 max-h-[600px] overflow-y-auto">
                        
                        <!-- Stock Filter - Moved to top -->
                        <div>
                            <h4 class="font-medium text-gray-900 mb-3">Availability</h4>
                            <label class="flex items-center">
                                <input type="checkbox" 
                                       wire:model.live="inStockOnly"
                                       class="rounded border-gray-300 text-blue-600">
                                <span class="ml-2 text-sm text-gray-700">In Stock Only</span>
                            </label>
                        </div>
                        
                        <!-- Categories Filter -->
                        @if(!empty($facets['category_name']))
                            <div>
                                <h4 class="font-medium text-gray-900 mb-3">Categories</h4>
                                <div class="space-y-2 max-h-48 overflow-y-auto">
                                    @foreach($facets['category_name'] as $category)
                                        <label class="flex items-center">
                                            <input type="checkbox" 
                                                   wire:model.live="selectedCategories"
                                                   value="{{ $category['value'] }}"
                                                   class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                            <span class="ml-2 text-sm {{ in_array($category['value'], $selectedCategories) ? 'text-blue-600 font-medium' : 'text-gray-700' }}">
                                                {{ $category['value'] }}
                                                <span class="text-gray-500 font-normal">({{ $category['count'] }})</span>
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                        
                        <!-- Brands Filter -->
                        @if(!empty($facets['brand_name']))
                            <div>
                                <h4 class="font-medium text-gray-900 mb-3">Brands</h4>
                                <div class="space-y-2 max-h-48 overflow-y-auto">
                                    @foreach($facets['brand_name'] as $brand)
                                        <label class="flex items-center">
                                            <input type="checkbox" 
                                                   wire:model.live="selectedBrands"
                                                   value="{{ $brand['value'] }}"
                                                   class="rounded border-gray-300 text-blue-600">
                                            <span class="ml-2 text-sm text-gray-700">
                                                {{ $brand['value'] }}
                                                <span class="text-gray-500">({{ $brand['count'] }})</span>
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                        
                        <!-- Manufacturers Filter -->
                        @if(!empty($facets['manufacturer_name']))
                            <div>
                                <h4 class="font-medium text-gray-900 mb-3">Manufacturers</h4>
                                <div class="space-y-2 max-h-48 overflow-y-auto">
                                    @foreach($facets['manufacturer_name'] as $manufacturer)
                                        <label class="flex items-center">
                                            <input type="checkbox" 
                                                   wire:model.live="selectedManufacturers"
                                                   value="{{ $manufacturer['value'] }}"
                                                   class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                            <span class="ml-2 text-sm {{ in_array($manufacturer['value'], $selectedManufacturers) ? 'text-blue-600 font-medium' : 'text-gray-700' }}">
                                                {{ $manufacturer['value'] }}
                                                <span class="text-gray-500 font-normal">({{ $manufacturer['count'] }})</span>
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                        
                    </div>
                </div>
            </div>
            
            <!-- Results Section -->
            <div class="flex-1">
                
                <!-- Results Header -->
                <div class="flex items-center justify-between mb-6">
                    <div class="flex items-center space-x-4">
                        <div class="text-sm text-gray-600">
                            @if($isLoading)
                                <div class="flex items-center text-blue-600">
                                    <svg class="animate-spin -ml-1 mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    Searching...
                                </div>
                            @else
                                <span>
                                    {{ number_format($searchResults['pagination']['total'] ?? 0) }} results
                                    @if(isset($searchResults['meta']['search_time_ms']))
                                        <span class="text-gray-400">
                                            ({{ $searchResults['meta']['search_time_ms'] }}ms)
                                        </span>
                                    @endif
                                </span>
                            @endif
                        </div>
                    </div>
                    
                    <!-- Sort Options -->
                    <div>
                        <select wire:model.live="sortBy" 
                                class="border border-gray-300 rounded-md shadow-sm py-2 px-3 text-sm text-gray-900 bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="relevance">Most Relevant</option>
                            <option value="price_asc">Price: Low to High</option>
                            <option value="price_desc">Price: High to Low</option>
                            <option value="name_asc">Name: A to Z</option>
                            <option value="name_desc">Name: Z to A</option>
                            <option value="newest">Newest First</option>
                            <option value="rating">Highest Rated</option>
                        </select>
                    </div>
                </div>
                
                <!-- Error Message -->
                @if($error)
                    <div class="bg-red-50 border border-red-200 rounded-md p-4 mb-6">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                                </svg>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-red-800">{{ $error }}</p>
                            </div>
                        </div>
                    </div>
                @endif
                
                <!-- Products Grid -->
                @if(!empty($searchResults['products']))
                    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
                        @foreach($searchResults['products'] as $product)
                            <a href="{{ route('products.show', $product['id']) }}" 
                               class="bg-white rounded-lg border border-gray-200 shadow-sm hover:shadow-md hover:border-blue-300 transition-all duration-200 overflow-hidden block group">
                                
                                <!-- Product Image -->
                                <div class="aspect-square bg-gray-50 relative">
                                    <img src="{{ $product['image_url'] }}" 
                                         alt="{{ $product['name'] }}"
                                         class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-200"
                                         loading="lazy">
                                    
                                    @if(!$product['in_stock'])
                                        <div class="absolute top-2 right-2">
                                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                Out of Stock
                                            </span>
                                        </div>
                                    @endif
                                </div>
                                
                                <!-- Product Info -->
                                <div class="p-4 space-y-3">
                                    
                                    <!-- Category & Brand -->
                                    <div class="text-xs text-gray-500">
                                        @if($product['category_name'])
                                            <span>{{ $product['category_name'] }}</span>
                                        @endif
                                        @if($product['brand_name'])
                                            <span> • {{ $product['brand_name'] }}</span>
                                        @endif
                                    </div>
                                    
                                    <!-- Product Name -->
                                    <h3 class="font-semibold text-gray-900 line-clamp-2 text-sm group-hover:text-blue-600 transition-colors">
                                        {!! $product['highlights']['name'][0] ?? $product['name'] !!}
                                    </h3>
                                    
                                    <!-- Product Numbers -->
                                    <div class="space-y-1">
                                        @if($product['pnum'])
                                            <div class="text-xs text-gray-600 font-mono">
                                                SKU: {{ $product['pnum'] }}
                                            </div>
                                        @endif
                                        <div class="text-xs text-gray-500 font-mono">
                                            ID: {{ $product['id'] }}
                                        </div>
                                    </div>
                                    
                                    <!-- Price & Stock -->
                                    <div class="flex items-center justify-between pt-2 border-t border-gray-100">
                                        <div class="text-lg font-bold text-gray-900">
                                            ${{ number_format($product['price'], 2) }}
                                        </div>
                                        
                                        @if($product['in_stock'])
                                            <div class="text-xs text-green-600 font-medium">
                                                {{ $product['stock_quantity'] }} in stock
                                            </div>
                                        @else
                                            <div class="text-xs text-red-600">
                                                Out of stock
                                            </div>
                                        @endif
                                    </div>
                                    
                                </div>
                            </a>
                        @endforeach
                    </div>
                    
                    <!-- Pagination -->
                    @if($searchResults['pagination']['total_pages'] > 1)
                        <div class="mt-8 flex items-center justify-center">
                            <nav class="inline-flex -space-x-px rounded-md shadow-sm" aria-label="Pagination">
                                
                                @if($currentPage > 1)
                                    <button wire:click="goToPage({{ $currentPage - 1 }})"
                                            class="relative inline-flex items-center rounded-l-md px-2 py-2 text-gray-400 ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                                        <span class="sr-only">Previous</span>
                                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M12.79 5.23a.75.75 0 01-.02 1.06L8.832 10l3.938 3.71a.75.75 0 11-1.04 1.08l-4.5-4.25a.75.75 0 010-1.08l4.5-4.25a.75.75 0 011.06.02z" clip-rule="evenodd"/>
                                        </svg>
                                    </button>
                                @endif
                                
                                @for($page = max(1, $currentPage - 2); $page <= min($searchResults['pagination']['total_pages'], $currentPage + 2); $page++)
                                    @if($page == $currentPage)
                                        <span class="relative z-10 inline-flex items-center bg-blue-600 px-4 py-2 text-sm font-semibold text-white">
                                            {{ $page }}
                                        </span>
                                    @else
                                        <button wire:click="goToPage({{ $page }})"
                                                class="relative inline-flex items-center px-4 py-2 text-sm font-semibold text-gray-900 ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                                            {{ $page }}
                                        </button>
                                    @endif
                                @endfor
                                
                                @if($currentPage < $searchResults['pagination']['total_pages'])
                                    <button wire:click="goToPage({{ $currentPage + 1 }})"
                                            class="relative inline-flex items-center rounded-r-md px-2 py-2 text-gray-400 ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
                                        <span class="sr-only">Next</span>
                                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd"/>
                                        </svg>
                                    </button>
                                @endif
                            </nav>
                        </div>
                    @endif
                    
                @else
                    
                    <!-- No Results -->
                    <div class="text-center py-12">
                        <svg class="h-12 w-12 text-gray-400 mx-auto mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">No products found</h3>
                        <p class="text-gray-500 mb-4">
                            Try adjusting your search criteria or clearing some filters.
                        </p>
                        <button wire:click="clearAllFilters" 
                                class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                            Clear All Filters
                        </button>
                    </div>
                @endif
                
            </div>
        </div>
    </div>
</div>
