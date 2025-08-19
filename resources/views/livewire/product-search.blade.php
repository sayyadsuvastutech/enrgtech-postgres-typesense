<div class="max-w-7xl mx-auto p-4 space-y-6">
    {{-- Search Header --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6">
        <div class="flex flex-col lg:flex-row gap-4 items-center">
            {{-- Search Input --}}
            <div class="flex-1 relative">
                <flux:input
                    wire:model.live.debounce.300ms="query"
                    placeholder="Search products, part numbers, manufacturers..."
                    class="w-full"
                    wire:focus="updateSuggestions"
                />

                {{-- Search Suggestions --}}
                @if($showSuggestions && !empty($suggestions))
                    <div class="absolute z-50 w-full mt-1 bg-white dark:bg-gray-800 rounded-md shadow-lg border border-gray-200 dark:border-gray-700">
                        @foreach($suggestions as $suggestion)
                            <div
                                class="px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer border-b border-gray-100 dark:border-gray-600 last:border-b-0"
                                wire:click="selectSuggestion({{ json_encode($suggestion) }})"
                            >
                                <div class="font-medium text-gray-900 dark:text-gray-100">{{ $suggestion['title'] }}</div>
                                <div class="text-sm text-gray-500 dark:text-gray-400">{{ $suggestion['subtitle'] }}</div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Sort Controls --}}
            <div class="flex items-center gap-3">
                <flux:select
                    wire:model.live="sortBy"
                    class="min-w-32"
                >
                    @foreach($filterOptions['sort_options'] as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </flux:select>

                {{-- View Toggle --}}
                <div class="flex">
                    <flux:button
                        variant="{{ $viewMode === 'grid' ? 'primary' : 'ghost' }}"
                        wire:click="updateViewMode('grid')"
                        size="sm"
                    >
                        <flux:icon name="table-cells" class="w-4 h-4" />
                    </flux:button>
                    <flux:button
                        variant="{{ $viewMode === 'list' ? 'primary' : 'ghost' }}"
                        wire:click="updateViewMode('list')"
                        size="sm"
                    >
                        <flux:icon name="queue-list" class="w-4 h-4" />
                    </flux:button>
                </div>
            </div>
        </div>

        {{-- Active Filters --}}
        @if(!empty($this->activeFilters))
            <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                <div class="flex items-center gap-2 mb-3">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Active Filters:</span>
                    <flux:button
                        variant="ghost"
                        size="sm"
                        wire:click="clearAllFilters"
                    >
                        Clear All
                    </flux:button>
                </div>
                <div class="flex flex-wrap gap-2">
                    @foreach($this->activeFilters as $filter)
                        <flux:badge variant="primary" class="flex items-center gap-1">
                            {{ $filter['label'] }}
                            <button
                                wire:click="clearFilter('{{ $filter['name'] }}')"
                                class="ml-1 hover:bg-white hover:bg-opacity-20 rounded-full p-0.5"
                            >
                                <flux:icon name="x-mark" class="w-3 h-3" />
                            </button>
                        </flux:badge>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    <div class="flex gap-6">
        {{-- Sidebar Filters --}}
        <div class="w-80 hidden lg:block">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6 space-y-6">
                <flux:heading size="lg">Filters</flux:heading>

                {{-- Categories Filter --}}
                @if(!empty($facets['categories']))
                    <div>
                        <flux:heading size="sm" class="mb-3">Categories</flux:heading>
                        <div class="space-y-2 max-h-48 overflow-y-auto">
                            @foreach($facets['categories'] as $categoryName => $count)
                                @php
                                    $category = collect($filterOptions['categories'])->firstWhere('name', $categoryName);
                                @endphp
                                @if($category)
                                    <flux:checkbox
                                        wire:model.live="filters.categories.{{ $category['id'] }}"
                                        label="{{ $categoryName }} ({{ $count }})"
                                    />
                                @endif
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Manufacturers Filter --}}
                @if(!empty($facets['manufacturers']))
                    <div>
                        <flux:heading size="sm" class="mb-3">Manufacturers</flux:heading>
                        <div class="space-y-2 max-h-48 overflow-y-auto">
                            @foreach($facets['manufacturers'] as $manufacturerName => $count)
                                @php
                                    $manufacturer = collect($filterOptions['manufacturers'])->firstWhere('name', $manufacturerName);
                                @endphp
                                @if($manufacturer)
                                    <flux:checkbox
                                        wire:model.live="filters.manufacturers.{{ $manufacturer['id'] }}"
                                        label="{{ $manufacturerName }} ({{ $count }})"
                                    />
                                @endif
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Price Range Filter --}}
                @if(!empty($facets['price_ranges']))
                    <div>
                        <flux:heading size="sm" class="mb-3">Price Range</flux:heading>
                        <div class="space-y-2">
                            @foreach($facets['price_ranges'] as $range => $count)
                                <flux:checkbox
                                    wire:model.live="filters.price_range.{{ $range }}"
                                    label="{{ $this->formatPriceRange($range) }} ({{ $count }})"
                                />
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Availability Filters --}}
                @if(!empty($facets['availability']))
                    <div>
                        <flux:heading size="sm" class="mb-3">Availability</flux:heading>
                        <div class="space-y-2">
                            @if($facets['availability']['in_stock'] > 0)
                                <flux:checkbox
                                    wire:model.live="filters.in_stock"
                                    label="In Stock ({{ $facets['availability']['in_stock'] }})"
                                />
                            @endif
                            @if($facets['availability']['rohs_compliant'] > 0)
                                <flux:checkbox
                                    wire:model.live="filters.rohs_compliant"
                                    label="RoHS Compliant ({{ $facets['availability']['rohs_compliant'] }})"
                                />
                            @endif
                        </div>
                    </div>
                @endif
            </div>
        </div>

        {{-- Main Content --}}
        <div class="flex-1">
            {{-- Results Header --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4 mb-6">
                <div class="flex items-center justify-between">
                    <div>
                        @if($total > 0)
                            <flux:text class="text-lg font-medium">
                                {{ number_format($total) }} products found
                                @if($query)
                                    for "{{ $query }}"
                                @endif
                            </flux:text>
                        @else
                            <flux:text class="text-lg font-medium">No products found</flux:text>
                        @endif
                    </div>

                    <div wire:loading class="flex items-center gap-2">
                        <div class="animate-spin rounded-full h-4 w-4 border-b-2 border-blue-600"></div>
                        <span class="text-sm text-gray-500">Searching...</span>
                    </div>
                </div>
            </div>

            {{-- Results Grid/List --}}
            @if($results->count() > 0)
                <div class="space-y-6">
                    @if($viewMode === 'grid')
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                            @foreach($results as $product)
                                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 hover:shadow-md transition-shadow">
                                    <div class="p-4">
                                        {{-- Product Image --}}
                                        <div class="w-48 h-48 mx-auto bg-gray-100 dark:bg-gray-700 rounded-lg mb-4 flex items-center justify-center overflow-hidden">
                                            @if($product->primary_image)
                                                <img src="{{ $product->primary_image }}" alt="{{ $product->name }}" class="max-w-full max-h-full object-contain rounded-lg">
                                            @else
                                                <flux:icon name="photo" class="w-12 h-12 text-gray-400" />
                                            @endif
                                        </div>

                                        {{-- Product Info --}}
                                        <div class="space-y-2">
                                            <flux:heading size="sm" class="line-clamp-2">
                                                <a href="{{ route('products.show', $product->pnum) }}" class="hover:text-blue-600">
                                                    {{ $product->name }}
                                                </a>
                                            </flux:heading>

                                            <flux:text size="sm" class="text-gray-500">
                                                {{ $product->manufacturer->name ?? '' }}
                                            </flux:text>

                                            <flux:text size="sm" class="font-mono">
                                                {{ $product->mf_pnum }}
                                            </flux:text>

                                            @if($product->lowest_price)
                                                <div class="text-lg font-bold text-green-600">
                                                    £{{ number_format($product->lowest_price, 2) }}
                                                </div>
                                            @endif

                                            <div class="flex items-center gap-2">
                                                @if($product->hasStock())
                                                    <flux:badge variant="success" size="sm">In Stock</flux:badge>
                                                @endif
                                                @if($product->isRohsCompliant())
                                                    <flux:badge variant="primary" size="sm">RoHS</flux:badge>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        {{-- List View --}}
                        <div class="space-y-4">
                            @foreach($results as $product)
                                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                                    <div class="flex gap-4">
                                        {{-- Product Image --}}
                                        <div class="w-48 h-48 bg-gray-100 dark:bg-gray-700 rounded-lg flex-shrink-0 flex items-center justify-center overflow-hidden">
                                            @if($product->primary_image)
                                                <img src="{{ $product->primary_image }}" alt="{{ $product->name }}" class="max-w-full max-h-full object-contain rounded-lg">
                                            @else
                                                <flux:icon name="photo" class="w-8 h-8 text-gray-400" />
                                            @endif
                                        </div>

                                        {{-- Product Details --}}
                                        <div class="flex-1 space-y-2">
                                            <flux:heading size="lg">
                                                <a href="{{ route('products.show', $product->pnum) }}" class="hover:text-blue-600">
                                                    {{ $product->name }}
                                                </a>
                                            </flux:heading>

                                            <div class="flex items-center gap-4">
                                                <flux:text class="text-gray-500">{{ $product->manufacturer->name ?? '' }}</flux:text>
                                                <flux:text class="font-mono">{{ $product->mf_pnum }}</flux:text>
                                            </div>

                                            @if($product->description)
                                                <flux:text class="text-gray-600 dark:text-gray-300">
                                                    {{ Str::limit($product->description, 150) }}
                                                </flux:text>
                                            @endif

                                            <div class="flex items-center gap-2">
                                                @if($product->hasStock())
                                                    <flux:badge variant="success" size="sm">In Stock</flux:badge>
                                                @endif
                                                @if($product->isRohsCompliant())
                                                    <flux:badge variant="primary" size="sm">RoHS</flux:badge>
                                                @endif
                                            </div>
                                        </div>

                                        {{-- Price and Actions --}}
                                        <div class="text-right space-y-2 flex-shrink-0">
                                            @if($product->lowest_price)
                                                <div class="text-2xl font-bold text-green-600">
                                                    £{{ number_format($product->lowest_price, 2) }}
                                                </div>
                                            @endif

                                            <flux:button variant="primary" size="sm">
                                                View Details
                                            </flux:button>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    {{-- Pagination --}}
                    <div class="mt-8">
                        {{ $results->links() }}
                    </div>
                </div>
            @else
                {{-- Empty State --}}
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-12 text-center">
                    <flux:icon name="magnifying-glass" class="w-16 h-16 text-gray-400 mx-auto mb-4" />
                    <flux:heading size="xl" class="mb-2">No products found</flux:heading>
                    <flux:text class="text-gray-500 mb-6">
                        Try adjusting your search terms or filters to find what you're looking for.
                    </flux:text>
                    @if(!empty($this->filters))
                        <flux:button variant="primary" wire:click="clearAllFilters">
                            Clear All Filters
                        </flux:button>
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>
