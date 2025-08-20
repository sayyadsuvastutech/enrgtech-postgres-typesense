<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\Product;
use App\Models\Category;
use App\Models\Brand;
use App\Models\Manufacturer;
use Illuminate\Database\Eloquent\Builder;

new class extends Component {
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
    public $priceRanges;
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
        $this->categories = Category::toTree(Category::withProducts()->with('children')->get());

        $this->brands = Brand::withProducts()->popular(20)->get();
        $this->manufacturers = Manufacturer::withProducts()->popular(15)->get();
        $this->commonAttributes = $this->getCommonAttributes();
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

            // Get brand suggestions
            $brandSuggestions = Brand::where('name', 'ILIKE', '%' . $this->search . '%')
                ->withProducts()
                ->limit(2)
                ->pluck('name')
                ->map(fn($brand) => ['type' => 'brand', 'text' => $brand]);

            // Get category suggestions
            $categorySuggestions = Category::where('name', 'ILIKE', '%' . $this->search . '%')
                ->withProducts()
                ->limit(2)
                ->pluck('name')
                ->map(fn($category) => ['type' => 'category', 'text' => $category]);

            $this->searchSuggestions = $suggestions
                ->concat($productSuggestions)
                ->concat($skuSuggestions)
                ->concat($brandSuggestions)
                ->concat($categorySuggestions)
                ->unique('text')
                ->take(8)
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

    private function getCommonAttributes(): array
    {
        // Get the most common attributes from products
        $attributes = Product::whereNotNull('attributes')
            ->get()
            ->pluck('attributes')
            ->collapse()
            ->keys()
            ->countBy()
            ->sortDesc()
            ->take(15)
            ->keys()
            ->toArray();

        return array_filter($attributes, function($attr) {
            return in_array(strtolower($attr), [
                'voltage', 'amperage', 'wattage', 'material', 'size',
                'length', 'width', 'height', 'weight', 'color',
                'certification', 'thread_size', 'drive_size', 'capacity'
            ]);
        });
    }

    #[\Livewire\Attributes\Computed]
    public function getProducts()
    {
        $query = Product::query()->active();

        // Apply search
        if (!empty($this->search)) {
            if (strlen($this->search) >= 3) {
                // Use full-text search for longer queries
                $query->where(function($q) {
                    $q->searchWithRank($this->search)
                      ->orWhere('sku', 'ILIKE', '%' . $this->search . '%')
                      ->orWhere('name', 'ILIKE', '%' . $this->search . '%');
                });
            } else {
                // Use simple LIKE for shorter queries
                $query->where(function($q) {
                    $q->where('sku', 'ILIKE', '%' . $this->search . '%')
                      ->orWhere('name', 'ILIKE', '%' . $this->search . '%');
                });
            }
        }

        // Apply category filters
        if (!empty($this->selectedCategories)) {
            $query->whereIn('category_id', $this->selectedCategories);
        }

        // Apply brand filters
        if (!empty($this->selectedBrands)) {
            $query->whereIn('brand_id', $this->selectedBrands);
        }

        // Apply manufacturer filters
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

        // Apply stock filter
        if ($this->inStock) {
            $query->inStock();
        }

        // Apply technical filters
        foreach ($this->technicalFilters as $attribute => $value) {
            if (!empty($value)) {
                $query->whereJsonContains("attributes->{$attribute}", $value);
            }
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
            case 'relevance':
            default:
                if (!empty($this->search) && strlen($this->search) >= 3) {
                    // Already ordered by relevance in searchWithRank
                } else {
                    $query->orderBy('name', 'asc');
                }
                break;
        }

        return $query->with(['category', 'brand', 'manufacturer'])->paginate(20);
    }

}; ?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Search Header -->
    <div class="mb-8">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <div class="flex-1 max-w-2xl relative">
                <flux:field>
                    <flux:input
                        wire:model.live.debounce.200ms="search"
                        wire:focus="generateSearchSuggestions"
                        wire:blur="hideSuggestions"
                        placeholder="Search products, SKUs, models..."
                        class="text-lg py-3"
                        icon="magnifying-glass"
                    />
                </flux:field>

                <!-- Search Suggestions Dropdown -->
                @if($showSuggestions && !empty($searchSuggestions))
                    <div class="absolute top-full left-0 right-0 z-50 mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-64 overflow-y-auto">
                        @foreach($searchSuggestions as $suggestion)
                            <button
                                wire:click="selectSuggestion({{ json_encode($suggestion) }})"
                                class="flex items-center w-full px-4 py-3 text-left hover:bg-gray-50 border-b border-gray-100 last:border-b-0"
                            >
                                <div class="flex items-center space-x-3 flex-1">
                                    @switch($suggestion['type'])
                                        @case('product')
                                            <flux:icon name="cube" class="size-4 text-blue-500" />
                                            <div>
                                                <div class="font-medium text-gray-900">{{ $suggestion['text'] }}</div>
                                                <div class="text-xs text-gray-500">Product</div>
                                            </div>
                                            @break
                                        @case('sku')
                                            <flux:icon name="hashtag" class="size-4 text-green-500" />
                                            <div>
                                                <div class="font-medium text-gray-900 font-mono">{{ $suggestion['text'] }}</div>
                                                <div class="text-xs text-gray-500">SKU</div>
                                            </div>
                                            @break
                                        @case('brand')
                                            <flux:icon name="building-office" class="size-4 text-purple-500" />
                                            <div>
                                                <div class="font-medium text-gray-900">{{ $suggestion['text'] }}</div>
                                                <div class="text-xs text-gray-500">Brand</div>
                                            </div>
                                            @break
                                        @case('category')
                                            <flux:icon name="folder" class="size-4 text-orange-500" />
                                            <div>
                                                <div class="font-medium text-gray-900">{{ $suggestion['text'] }}</div>
                                                <div class="text-xs text-gray-500">Category</div>
                                            </div>
                                            @break
                                    @endswitch
                                </div>
                                <flux:icon name="arrow-up-right" class="size-3 text-gray-400" />
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="flex items-center gap-3">
                <flux:button
                    wire:click="toggleAdvancedFilters"
                    variant="ghost"
                    size="sm"
                    class="text-gray-600"
                >
                    <flux:icon name="adjustments-horizontal" class="size-4" />
                    {{ $showAdvancedFilters ? 'Hide' : 'Show' }} Filters
                </flux:button>

                <flux:select wire:model.live="sortBy" class="w-40">
                    <option value="relevance">Best Match</option>
                    <option value="name">Name A-Z</option>
                    <option value="price">Price</option>
                    <option value="newest">Newest</option>
                </flux:select>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
        <!-- Filters Sidebar -->
        <div class="lg:col-span-1 {{ !$showAdvancedFilters ? 'hidden lg:block' : '' }}">
            <div class="sticky top-8 space-y-6 bg-white rounded-lg border border-gray-200 p-6">
                <!-- Clear Filters -->
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-900">Filters</h3>
                    <flux:button wire:click="clearFilters" variant="ghost" size="sm">
                        Clear All
                    </flux:button>
                </div>

                <!-- Stock Filter -->
                <div>
                    <flux:field>
                        <flux:checkbox wire:model.live="inStock" id="in-stock">
                            <flux:label for="in-stock">In Stock Only</flux:label>
                        </flux:checkbox>
                    </flux:field>
                </div>

                <!-- Price Range -->
                <div>
                    <flux:heading size="sm" class="mb-3">Price Range</flux:heading>

                    <!-- Price Presets -->
                    <div class="space-y-2 mb-4">
                        @foreach($pricePresets as $preset)
                            <button
                                wire:click="setPriceRange({{ $preset['min'] }}, {{ $preset['max'] }})"
                                class="block w-full text-left px-3 py-2 text-sm rounded-lg hover:bg-gray-50 {{
                                    ($minPrice == $preset['min'] && $maxPrice == $preset['max']) ? 'bg-blue-50 text-blue-700' : 'text-gray-700'
                                }}"
                            >
                                {{ $preset['label'] }}
                            </button>
                        @endforeach
                    </div>

                    <!-- Custom Price Range -->
                    <div class="grid grid-cols-2 gap-2">
                        <flux:field>
                            <flux:label>Min Price</flux:label>
                            <flux:input
                                wire:model.live.debounce.500ms="minPrice"
                                type="number"
                                min="0"
                                step="0.01"
                                placeholder="0"
                            />
                        </flux:field>
                        <flux:field>
                            <flux:label>Max Price</flux:label>
                            <flux:input
                                wire:model.live.debounce.500ms="maxPrice"
                                type="number"
                                min="0"
                                step="0.01"
                                placeholder="∞"
                            />
                        </flux:field>
                    </div>
                </div>

                <!-- Category Filter -->
                @if($categories->isNotEmpty())
                <div>
                    <flux:heading size="sm" class="mb-3">Categories</flux:heading>
                    <div class="space-y-2 max-h-48 overflow-y-auto">
                        @foreach($categories as $category)
                            <div>
                                <flux:field>
                                    <flux:checkbox
                                        wire:model.live="selectedCategories"
                                        value="{{ $category->id }}"
                                        id="category-{{ $category->id }}"
                                    >
                                        <flux:label for="category-{{ $category->id }}" class="text-sm">
                                            {{ $category->name }}
                                        </flux:label>
                                    </flux:checkbox>
                                </flux:field>

                                @if($category->children->isNotEmpty())
                                    <div class="ml-4 mt-1 space-y-1">
                                        @foreach($category->children as $child)
                                            <flux:field>
                                                <flux:checkbox
                                                    wire:model.live="selectedCategories"
                                                    value="{{ $child->id }}"
                                                    id="category-{{ $child->id }}"
                                                >
                                                    <flux:label for="category-{{ $child->id }}" class="text-xs text-gray-600">
                                                        {{ $child->name }}
                                                    </flux:label>
                                                </flux:checkbox>
                                            </flux:field>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
                @endif

                <!-- Brand Filter -->
                @if($brands->isNotEmpty())
                <div>
                    <flux:heading size="sm" class="mb-3">Brands</flux:heading>
                    <div class="space-y-2 max-h-48 overflow-y-auto">
                        @foreach($brands as $brand)
                            <flux:field>
                                <flux:checkbox
                                    wire:model.live="selectedBrands"
                                    value="{{ $brand->id }}"
                                    id="brand-{{ $brand->id }}"
                                >
                                    <flux:label for="brand-{{ $brand->id }}" class="text-sm">
                                        {{ $brand->name }}
                                        <span class="text-xs text-gray-500">({{ $brand->products_count ?? 0 }})</span>
                                    </flux:label>
                                </flux:checkbox>
                            </flux:field>
                        @endforeach
                    </div>
                </div>
                @endif

                <!-- Manufacturer Filter -->
                @if($manufacturers->isNotEmpty())
                <div>
                    <flux:heading size="sm" class="mb-3">Manufacturers</flux:heading>
                    <div class="space-y-2 max-h-48 overflow-y-auto">
                        @foreach($manufacturers as $manufacturer)
                            <flux:field>
                                <flux:checkbox
                                    wire:model.live="selectedManufacturers"
                                    value="{{ $manufacturer->id }}"
                                    id="manufacturer-{{ $manufacturer->id }}"
                                >
                                    <flux:label for="manufacturer-{{ $manufacturer->id }}" class="text-sm">
                                        {{ $manufacturer->name }}
                                        <span class="text-xs text-gray-500">({{ $manufacturer->products_count ?? 0 }})</span>
                                    </flux:label>
                                </flux:checkbox>
                            </flux:field>
                        @endforeach
                    </div>
                </div>
                @endif

                <!-- Technical Filters -->
                @if(!empty($commonAttributes))
                <div>
                    <flux:heading size="sm" class="mb-3">Technical Specifications</flux:heading>
                    <div class="space-y-3">
                        @foreach($commonAttributes as $attribute)
                            <flux:field>
                                <flux:label class="text-sm">{{ ucwords(str_replace('_', ' ', $attribute)) }}</flux:label>
                                <flux:input
                                    wire:model.live.debounce.500ms="technicalFilters.{{ $attribute }}"
                                    placeholder="Any {{ strtolower(str_replace('_', ' ', $attribute)) }}"
                                    class="text-sm"
                                />
                            </flux:field>
                        @endforeach
                    </div>
                </div>
                @endif
            </div>
        </div>

        <!-- Results -->
        <div class="lg:col-span-3">
            <!-- Results Header -->
            <div class="flex items-center justify-between mb-6">
                <div class="text-sm text-gray-600">
                    <span wire:loading.remove wire:target="search,selectedCategories,selectedBrands,selectedManufacturers,minPrice,maxPrice,technicalFilters,inStock">
                        {{ $this->getProducts->total() }} products found
                    </span>
                    <span wire:loading wire:target="search,selectedCategories,selectedBrands,selectedManufacturers,minPrice,maxPrice,technicalFilters,inStock" class="text-blue-600">
                        Searching...
                    </span>
                </div>

                <div class="text-sm text-gray-500">
                    Showing {{ $this->getProducts->firstItem() ?? 0 }}-{{ $this->getProducts->lastItem() ?? 0 }} of {{ $this->getProducts->total() }}
                </div>
            </div>

            <!-- Loading State -->
            <div wire:loading.delay wire:target="search,selectedCategories,selectedBrands,selectedManufacturers,minPrice,maxPrice,technicalFilters,inStock"
                 class="absolute inset-0 bg-white bg-opacity-75 flex items-center justify-center rounded-lg">
                <div class="flex items-center space-x-2">
                    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
                    <span class="text-gray-600">Loading products...</span>
                </div>
            </div>

            <!-- Products Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
                @forelse($this->getProducts as $product)
                    <div class="bg-white rounded-lg border border-gray-200 shadow-sm hover:shadow-md transition-shadow duration-200">
                        <!-- Product Image -->
                        <div class="aspect-square bg-gray-50 rounded-t-lg relative overflow-hidden">
                            @if($product->main_image)
                                <img src="{{ $product->main_image }}" alt="{{ $product->name }}"
                                     class="w-full h-full object-cover">
                            @else
                                <div class="w-full h-full flex items-center justify-center">
                                    <flux:icon name="photo" class="size-12 text-gray-400" />
                                </div>
                            @endif

                            <!-- Stock Badge -->
                            @if(!$product->isInStock())
                                <div class="absolute top-2 right-2">
                                    <flux:badge color="red" size="sm">Out of Stock</flux:badge>
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
                                <flux:button variant="outline" size="sm" class="w-full">
                                    <flux:icon name="eye" class="size-4" />
                                    View Details
                                </flux:button>

                                <flux:button
                                    variant="primary"
                                    size="sm"
                                    class="w-full"
                                    :disabled="!$product->isInStock()"
                                >
                                    <flux:icon name="shopping-cart" class="size-4" />
                                    Add to Cart
                                </flux:button>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-span-full text-center py-12">
                        <flux:icon name="magnifying-glass" class="size-12 text-gray-400 mx-auto mb-4" />
                        <h3 class="text-lg font-medium text-gray-900 mb-2">No products found</h3>
                        <p class="text-gray-500 mb-4">Try adjusting your search criteria or clearing some filters.</p>
                        <flux:button wire:click="clearFilters" variant="outline">
                            Clear Filters
                        </flux:button>
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
