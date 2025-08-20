<?php

use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\Product;
use App\Models\Category;
use App\Models\Brand;
use App\Models\Manufacturer;
use Illuminate\Database\Eloquent\Builder;

new #[Layout('components.layouts.public')]
class extends Component {
    use WithPagination;

    // Search properties
    public string $search = '';
    public array $selectedCategories = [];
    public array $selectedBrands = [];
    public array $selectedManufacturers = [];
    public ?float $minPrice = null;
    public ?float $maxPrice = null;
    public array $searchSuggestions = [];
    public bool $showSuggestions = false;

    // Filter properties
    public array $technicalFilters = [];
    public string $sortBy = 'relevance';
    public string $sortOrder = 'desc';
    public bool $inStock = false;
    public bool $showAdvancedFilters = false;

    // Data properties
    public $categories;
    public $brands;
    public $manufacturers;
    public $commonAttributes;

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
        $this->loadFilterOptions();
    }

    public function loadFilterOptions(): void
    {
        // Load categories with basic filtering to avoid deadlocks
        $this->categories = Category::query()
            ->take(20)
            ->get();

        // Load brands with product count
        $this->brands = Brand::query()
            ->take(20)
            ->get();

        // Load manufacturers with product count
        $this->manufacturers = Manufacturer::query()
            ->take(15)
            ->get();

        $this->commonAttributes = [];
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
        $this->generateSearchSuggestions();
    }

    public function generateSearchSuggestions(): void
    {
        if (strlen($this->search) >= 2) {
            $suggestions = collect();

            // Get product name suggestions
            $productSuggestions = Product::active()
                ->where('name', 'ILIKE', '%' . $this->search . '%')
                ->limit(3)
                ->pluck('name')
                ->map(fn($name) => ['type' => 'product', 'text' => $name]);

            // Get SKU suggestions
            $skuSuggestions = Product::active()
                ->where('sku', 'ILIKE', '%' . $this->search . '%')
                ->limit(2)
                ->pluck('sku')
                ->map(fn($sku) => ['type' => 'sku', 'text' => $sku]);

            $this->searchSuggestions = $suggestions
                ->concat($productSuggestions)
                ->concat($skuSuggestions)
                ->unique('text')
                ->take(6)
                ->toArray();

            $this->showSuggestions = !empty($this->searchSuggestions);
        } else {
            $this->searchSuggestions = [];
            $this->showSuggestions = false;
        }
    }

    public function selectSuggestion($suggestion): void
    {
        $this->search = $suggestion['text'];
        $this->showSuggestions = false;
        $this->resetPage();
    }

    public function hideSuggestions(): void
    {
        $this->showSuggestions = false;
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
            'technicalFilters',
            'inStock'
        ]);
        $this->resetPage();
    }

    public function toggleAdvancedFilters(): void
    {
        $this->showAdvancedFilters = !$this->showAdvancedFilters;
    }

    public function getProducts()
    {
        $query = Product::query()->active();

        // Apply basic search for now
        if (!empty($this->search)) {
            $query->where(function ($q) {
                $q->where('sku', 'ILIKE', '%' . $this->search . '%')
                  ->orWhere('name', 'ILIKE', '%' . $this->search . '%');
            });
        }

        // Apply filters
        if (!empty($this->selectedCategories)) {
            $query->whereIn('category_id', $this->selectedCategories);
        }

        if (!empty($this->selectedBrands)) {
            $query->whereIn('brand_id', $this->selectedBrands);
        }

        if (!empty($this->selectedManufacturers)) {
            $query->whereIn('manufacturer_id', $this->selectedManufacturers);
        }

        // Apply price range
        if ($this->minPrice !== null) {
            $query->where('price', '>=', $this->minPrice);
        }
        if ($this->maxPrice !== null) {
            $query->where('price', '<=', $this->maxPrice);
        }

        // Apply sorting
        switch ($this->sortBy) {
            case 'name':
                $query->orderBy('name', $this->sortOrder);
                break;
            case 'price':
                $query->orderBy('price', $this->sortOrder);
                break;
            case 'newest':
                $query->orderBy('created_at', 'desc');
                break;
            default:
                $query->orderBy('name', 'asc');
                break;
        }

        return $query->with(['category', 'brand', 'manufacturer'])->paginate(20);
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
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </div>
                        <input type="text"
                               wire:model.live.debounce.200ms="search"
                               wire:focus="generateSearchSuggestions"
                               wire:blur="hideSuggestions"
                               placeholder="Search products, SKUs, models..."
                               class="block w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg text-lg text-gray-900 placeholder-gray-500 bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>

                    <!-- Search Suggestions Dropdown -->
                    @if($showSuggestions && !empty($searchSuggestions))
                        <div class="absolute top-full left-0 right-0 z-50 mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-64 overflow-y-auto">
                            @foreach($searchSuggestions as $suggestion)
                                <button wire:click="selectSuggestion({{ json_encode($suggestion) }})"
                                        class="flex items-center w-full px-4 py-3 text-left hover:bg-gray-50 border-b border-gray-100 last:border-b-0">
                                    <div class="flex items-center space-x-3 flex-1">
                                        @if($suggestion['type'] === 'product')
                                            <div class="flex-shrink-0 w-2 h-2 bg-blue-500 rounded-full"></div>
                                            <div>
                                                <div class="font-medium text-gray-900">{{ $suggestion['text'] }}</div>
                                                <div class="text-xs text-gray-500">Product</div>
                                            </div>
                                        @elseif($suggestion['type'] === 'sku')
                                            <div class="flex-shrink-0 w-2 h-2 bg-green-500 rounded-full"></div>
                                            <div>
                                                <div class="font-medium text-gray-900 font-mono">{{ $suggestion['text'] }}</div>
                                                <div class="text-xs text-gray-500">SKU</div>
                                            </div>
                                        @endif
                                    </div>
                                    <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                    </svg>
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>

                <!-- Filter and Sort Controls -->
                <div class="flex items-center justify-center lg:justify-end gap-3">
                    <button wire:click="toggleAdvancedFilters"
                            class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <svg class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 100 4m0-4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 100 4m0-4v2m0-6V4" />
                        </svg>
                        {{ $showAdvancedFilters ? 'Hide' : 'Show' }} Filters
                    </button>

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
            <div class="w-full lg:w-64 {{ !$showAdvancedFilters ? 'hidden lg:block' : '' }}">
                <div class="bg-white rounded-lg border border-gray-200 shadow-sm sticky top-24 max-h-[calc(100vh-8rem)] overflow-hidden flex flex-col">
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
                        @if($categories->isNotEmpty())
                            <div>
                                <h4 class="text-sm font-medium text-gray-900 mb-3">Categories</h4>
                                <div class="border border-gray-200 rounded-lg max-h-48 overflow-y-auto bg-gray-50">
                                    <div class="p-3 space-y-2">
                                        @foreach($categories as $category)
                                            <label class="flex items-center cursor-pointer hover:bg-white rounded px-2 py-1 transition-colors">
                                                <input type="checkbox"
                                                       wire:model.live="selectedCategories"
                                                       value="{{ $category->id }}"
                                                       class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                                                <span class="ml-2 text-sm text-gray-700">{{ $category->name }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        @endif

                        <!-- Brands Filter -->
                        @if($brands->isNotEmpty())
                            <div>
                                <h4 class="text-sm font-medium text-gray-900 mb-3">Brands</h4>
                                <div class="border border-gray-200 rounded-lg max-h-48 overflow-y-auto bg-gray-50">
                                    <div class="p-3 space-y-2">
                                        @foreach($brands as $brand)
                                            <label class="flex items-center cursor-pointer hover:bg-white rounded px-2 py-1 transition-colors">
                                                <input type="checkbox"
                                                       wire:model.live="selectedBrands"
                                                       value="{{ $brand->id }}"
                                                       class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                                                <span class="ml-2 text-sm text-gray-700">{{ $brand->name }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        @endif

                        <!-- Manufacturers Filter -->
                        @if($manufacturers->isNotEmpty())
                            <div>
                                <h4 class="text-sm font-medium text-gray-900 mb-3">Manufacturers</h4>
                                <div class="border border-gray-200 rounded-lg max-h-48 overflow-y-auto bg-gray-50">
                                    <div class="p-3 space-y-2">
                                        @foreach($manufacturers as $manufacturer)
                                            <label class="flex items-center cursor-pointer hover:bg-white rounded px-2 py-1 transition-colors">
                                                <input type="checkbox"
                                                       wire:model.live="selectedManufacturers"
                                                       value="{{ $manufacturer->id }}"
                                                       class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                                                <span class="ml-2 text-sm text-gray-700">{{ $manufacturer->name }}</span>
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
                        <span wire:loading.remove wire:target="search,selectedCategories,selectedBrands,selectedManufacturers,minPrice,maxPrice,inStock">
                            {{ $this->getProducts()->total() }} products found
                        </span>
                        <span wire:loading wire:target="search,selectedCategories,selectedBrands,selectedManufacturers,minPrice,maxPrice,inStock"
                              class="text-blue-600 flex items-center">
                            <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Searching...
                        </span>
                    </div>

                    <div class="text-sm text-gray-500">
                        Showing {{ $this->getProducts()->firstItem() ?? 0 }}-{{ $this->getProducts()->lastItem() ?? 0 }}
                        of {{ $this->getProducts()->total() }}
                    </div>
                </div>

                <!-- Products Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6"
                     wire:loading.class="opacity-50"
                     wire:target="search,selectedCategories,selectedBrands,selectedManufacturers,minPrice,maxPrice,inStock">
                    @forelse($this->getProducts() as $product)
                        <div class="bg-white rounded-lg border border-gray-200 shadow-sm hover:shadow-md transition-shadow duration-200 overflow-hidden">
                            <!-- Product Image -->
                            <div class="aspect-square bg-gray-50 relative">
                                <img src="/images/place_holder.svg"
{{--                                     data-src="{{ $product->main_image ?? '/images/place_holder.svg' }}" --}}
                                     alt="{{ $product->name }}"
                                     class="lazy w-full h-full object-cover transition-opacity duration-300"
                                     loading="lazy">

                                <!-- Stock Badge -->
                                @if(!$product->isInStock())
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
                                <div class="text-xs text-gray-500 space-x-2">
                                    <span>{{ $product->category?->name }}</span>
                                    @if($product->brand?->name)
                                        <span>•</span>
                                        <span class="font-medium">{{ $product->brand->name }}</span>
                                    @endif
                                </div>

                                <!-- Product Name -->
                                <h3 class="font-semibold text-gray-900 line-clamp-2 leading-5">
                                    {{ $product->name }}
                                </h3>

                                <!-- SKU -->
                                <div class="text-xs text-gray-600 font-mono">
                                    SKU: {{ $product->sku }}
                                </div>

                                <!-- Technical Specs -->
                                @if($product->attributes && count($product->attributes) > 0)
                                    <div class="text-xs text-gray-600 space-y-1">
                                        @foreach(array_slice($product->attributes, 0, 3) as $key => $value)
                                            <div class="flex justify-between">
                                                <span class="capitalize">{{ str_replace('_', ' ', $key) }}:</span>
                                                <span class="font-medium">{{ $value }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif

                                <!-- Price & Stock -->
                                <div class="flex items-center justify-between pt-2 border-t border-gray-100">
                                    <div class="text-xl font-bold text-gray-900">
                                        ${{ number_format($product->price, 2) }}
                                    </div>

                                    <div class="text-sm text-gray-600">
                                        {{ $product->stock_quantity > 0 ? $product->stock_quantity . ' in stock' : 'Out of stock' }}
                                    </div>
                                </div>

                                <!-- Action Buttons -->
                                <div class="grid grid-cols-2 gap-2 pt-2">
                                    <button class="flex items-center justify-center px-3 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">
                                        <svg class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                        View
                                    </button>

                                    <button class="flex items-center justify-center px-3 py-2 rounded-md text-sm font-medium text-white transition-colors {{ $product->isInStock() ? 'bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500' : 'bg-gray-400 cursor-not-allowed' }}"
                                            {{ !$product->isInStock() ? 'disabled' : '' }}>
                                        <svg class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4m0 0L7 13m0 0l-2.5 5M7 13l2.5 5m8.5-5L19 8m-9 5h2.5" />
                                        </svg>
                                        Add to Cart
                                    </button>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="col-span-full text-center py-12">
                            <svg class="h-12 w-12 text-gray-400 mx-auto mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                            <h3 class="text-lg font-medium text-gray-900 mb-2">No products found</h3>
                            <p class="text-gray-500 mb-4">Try adjusting your search criteria or clearing some filters.</p>
                            <button wire:click="clearFilters"
                                    class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                Clear Filters
                            </button>
                        </div>
                    @endforelse
                </div>

                <!-- Pagination -->
                @if($this->getProducts()->hasPages())
                    <div class="mt-8">
                        {{ $this->getProducts()->links('custom-pagination') }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
