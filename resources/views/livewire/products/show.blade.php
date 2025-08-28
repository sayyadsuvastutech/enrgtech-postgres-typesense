<?php

use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use App\Models\Product;

new #[Layout('components.layouts.public')]
class extends Component {
    public Product $product;
    public string $searchTerm = '';

    public function mount(Product $product): void
    {
        // Ensure product is active before loading relationships
        if (!$product->isActive()) {
            abort(404);
        }
        
        // Get search term from query parameter if available
        $this->searchTerm = request('search', '');
        
        // Load relationships for better performance
        $this->product = $product->load(['category', 'brand', 'manufacturer', 'prices', 'quantities', 'attributes', 'images']);
    }

    public function highlightSearchTerms(string $text): string
    {
        if (empty($this->searchTerm)) {
            return $text;
        }
        
        $terms = explode(' ', $this->searchTerm);
        $terms = array_filter($terms, fn($term) => strlen(trim($term)) >= 2);
        
        foreach ($terms as $term) {
            $term = trim($term);
            if (strlen($term) >= 2) {
                $text = preg_replace(
                    '/(' . preg_quote($term, '/') . ')/i',
                    '<mark class="bg-yellow-200 text-yellow-900 font-semibold px-1 rounded">$1</mark>',
                    $text
                );
            }
        }
        
        return $text;
    }
}; ?>

<div class="bg-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Breadcrumb -->
        <nav class="flex mb-8" aria-label="Breadcrumb">
            <ol class="inline-flex items-center space-x-1 md:space-x-3">
                <li class="inline-flex items-center">
                    <a href="{{ route('products.search') }}" class="text-gray-700 hover:text-gray-900 inline-flex items-center">
                        <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"></path>
                        </svg>
                        Products
                    </a>
                </li>
                @if($product->category)
                    <li>
                        <div class="flex items-center">
                            <svg class="w-6 h-6 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                            </svg>
                            <span class="ml-1 text-gray-500 md:ml-2">{{ $product->category->name }}</span>
                        </div>
                    </li>
                @endif
                <li>
                    <div class="flex items-center">
                        <svg class="w-6 h-6 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                        </svg>
                        <span class="ml-1 text-gray-500 md:ml-2 font-medium">{{ $product->name }}</span>
                    </div>
                </li>
            </ol>
        </nav>

        <div class="lg:grid lg:grid-cols-2 lg:gap-x-8 lg:items-start">
            <!-- Image gallery -->
            <div class="flex flex-col-reverse">
                <!-- Image selector -->
                @if($product->images && $product->images->count() > 1)
                    <div class="hidden mt-6 w-full max-w-2xl mx-auto sm:block lg:max-w-none">
                        <div class="grid grid-cols-4 gap-6">
                            @foreach($product->images as $index => $imageSource)
                                @if(isset($imageSource->images) && is_array($imageSource->images))
                                    @foreach($imageSource->images as $imageIndex => $image)
                                        <button class="relative h-24 bg-white rounded-md flex items-center justify-center text-sm font-medium uppercase text-gray-900 cursor-pointer hover:bg-gray-50 focus:outline-none focus:ring focus:ring-offset-4 focus:ring-blue-500">
                                            <span class="sr-only">{{ $product->name }} image {{ $index + 1 }}-{{ $imageIndex + 1 }}</span>
                                            <img src="{{ $image['path'] ?? '/images/place_holder.svg' }}" alt="{{ $product->name }}" class="w-full h-full object-center object-cover rounded-md">
                                        </button>
                                    @endforeach
                                @endif
                            @endforeach
                        </div>
                    </div>
                @endif

                <!-- Main image -->
                <div class="w-full aspect-square">
                    @php
                        $primaryImage = $product->images->first()?->images[0]['path'] ?? '/images/place_holder.svg';
                    @endphp
                    <img src="{{ $primaryImage }}" alt="{{ $product->name }}" class="w-full h-full object-center object-cover sm:rounded-lg">
                </div>
            </div>

            <!-- Product info -->
            <div class="mt-10 px-4 sm:px-0 sm:mt-16 lg:mt-0">
                <!-- Product name and basic info -->
                <div class="mb-6">
                    <h1 class="text-3xl font-bold tracking-tight text-gray-900">{{ $product->name }}</h1>
                    
                    <!-- Category, Brand, Manufacturer -->
                    <div class="mt-3 flex flex-wrap items-center gap-2 text-sm text-gray-600">
                        @if($product->category)
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                {{ $product->category->name }}
                            </span>
                        @endif
                        @if($product->brand)
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                {{ $product->brand->name }}
                            </span>
                        @endif
                        @if($product->manufacturer)
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                {{ $product->manufacturer->name }}
                            </span>
                        @endif
                    </div>

                    <!-- SKU -->
                    <div class="mt-3 text-sm text-gray-600">
                        <span class="font-medium">SKU:</span> 
                        <span class="font-mono">{{ $product->pnum }}</span>
                        @if($product->mf_pnum && $product->mf_pnum !== $product->pnum)
                            <br><span class="font-medium">MFG Part:</span> 
                            <span class="font-mono">{{ $product->mf_pnum }}</span>
                        @endif
                    </div>
                </div>

                <!-- Price -->
                <div class="mb-6">
                    <p class="text-3xl font-bold text-gray-900">${{ number_format($product->price, 2) }}</p>
                </div>

                <!-- Stock status -->
                <div class="mb-6">
                    @if($product->isInStock())
                        <div class="flex items-center">
                            <svg class="flex-shrink-0 h-5 w-5 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                            </svg>
                            <span class="ml-2 text-sm text-gray-900">
                                <span class="font-medium">In stock</span>
                                ({{ $product->stock_quantity }} available)
                            </span>
                        </div>
                    @else
                        <div class="flex items-center">
                            <svg class="flex-shrink-0 h-5 w-5 text-red-500" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                            </svg>
                            <span class="ml-2 text-sm text-gray-900 font-medium">Out of stock</span>
                        </div>
                    @endif
                </div>

                <!-- Description -->
                @if($product->description)
                    <div class="mb-8">
                        <h3 class="text-lg font-medium text-gray-900 mb-3">Description</h3>
                        <div class="prose prose-sm text-gray-600">
                            {!! nl2br($this->highlightSearchTerms(e($product->description))) !!}
                        </div>
                    </div>
                @endif

                <!-- Search Context (if coming from search) -->
                @if($searchTerm)
                    <div class="mb-8">
                        <div class="bg-amber-50 border border-amber-200 rounded-lg p-4">
                            <div class="flex items-center mb-2">
                                <svg class="w-5 h-5 text-amber-600 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd"></path>
                                </svg>
                                <h3 class="text-lg font-medium text-amber-900">Search Match for: "{{ $searchTerm }}"</h3>
                            </div>
                            <p class="text-sm text-amber-800">
                                This product was found matching your search terms. Highlighted sections below show where matches were found.
                            </p>
                        </div>
                    </div>
                @endif

                <!-- Search Relevance Information -->
                <div class="mb-8">
                    <h3 class="text-lg font-medium text-gray-900 mb-3">Product Information</h3>
                    <div class="bg-blue-50 rounded-lg p-4 space-y-3">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <dt class="text-sm font-medium text-blue-900">Product Name</dt>
                                <dd class="text-sm text-blue-800 font-semibold">{!! $this->highlightSearchTerms($product->name) !!}</dd>
                            </div>
                            @if($product->title && $product->title !== $product->name)
                                <div>
                                    <dt class="text-sm font-medium text-blue-900">Full Title</dt>
                                    <dd class="text-sm text-blue-800">{!! $this->highlightSearchTerms($product->title) !!}</dd>
                                </div>
                            @endif
                            <div>
                                <dt class="text-sm font-medium text-blue-900">Product Number</dt>
                                <dd class="text-sm text-blue-800 font-mono">{!! $this->highlightSearchTerms($product->pnum) !!}</dd>
                            </div>
                            @if($product->mf_pnum)
                                <div>
                                    <dt class="text-sm font-medium text-blue-900">Manufacturer Part Number</dt>
                                    <dd class="text-sm text-blue-800 font-mono">{!! $this->highlightSearchTerms($product->mf_pnum) !!}</dd>
                                </div>
                            @endif
                        </div>
                        @if($product->breadcrumb)
                            <div>
                                <dt class="text-sm font-medium text-blue-900">Product Category Path</dt>
                                <dd class="text-sm text-blue-800">{!! $this->highlightSearchTerms($product->breadcrumb) !!}</dd>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Detailed Attributes by Source -->
                @if($product->attributes && $product->attributes->count() > 0)
                    <div class="mb-8">
                        <h3 class="text-lg font-medium text-gray-900 mb-3">Detailed Specifications by Source</h3>
                        <div class="space-y-6">
                            @foreach($product->attributes as $attributeSource)
                                <div class="border border-gray-200 rounded-lg overflow-hidden">
                                    <!-- Source Header -->
                                    <div class="bg-gray-100 px-4 py-3 border-b border-gray-200">
                                        <h4 class="text-sm font-semibold text-gray-900 uppercase tracking-wide">
                                            Source: {{ $attributeSource->source_name }}
                                        </h4>
                                    </div>
                                    
                                    <div class="p-4">
                                        <!-- All Attributes -->
                                        @if(isset($attributeSource->attributes) && is_array($attributeSource->attributes) && count($attributeSource->attributes) > 0)
                                            <div>
                                                <h5 class="text-sm font-medium text-gray-800 mb-3">Product Specifications</h5>
                                                <div class="bg-gray-50 rounded-lg p-3">
                                                    <dl class="grid grid-cols-1 gap-x-4 gap-y-2 sm:grid-cols-2 lg:grid-cols-3">
                                                        @foreach($attributeSource->attributes as $key => $value)
                                                            <div>
                                                                <dt class="text-sm font-medium text-gray-900">{!! $this->highlightSearchTerms($key) !!}</dt>
                                                                <dd class="text-sm text-gray-600">{!! $this->highlightSearchTerms($value) !!}</dd>
                                                            </div>
                                                        @endforeach
                                                    </dl>
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <!-- Price Details from Multiple Sources -->
                @if($product->prices && $product->prices->count() > 0)
                    <div class="mb-8">
                        <h3 class="text-lg font-medium text-gray-900 mb-3">Pricing Information by Source</h3>
                        <div class="space-y-4">
                            @foreach($product->prices as $priceSource)
                                <div class="border border-gray-200 rounded-lg p-4">
                                    <div class="flex items-center justify-between mb-3">
                                        <h4 class="text-sm font-semibold text-gray-900 uppercase tracking-wide">
                                            Source: {{ $priceSource->source_name }}
                                        </h4>
                                        @if(!empty($priceSource->currency))
                                            <span class="text-xs text-gray-500">{{ $priceSource->currency }}</span>
                                        @endif
                                    </div>
                                    
                                    @if(is_array($priceSource->pricing_ranges) && count($priceSource->pricing_ranges) > 0)
                                        <div class="bg-yellow-50 rounded-lg p-3">
                                            <h5 class="text-sm font-medium text-yellow-800 mb-2">Quantity-based Pricing</h5>
                                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
                                                @foreach($priceSource->pricing_ranges as $range)
                                                    <div class="bg-white rounded p-2 border border-yellow-200">
                                                        <div class="text-xs text-yellow-700">
                                                            {{ $range['from'] }}{{ isset($range['to']) && $range['to'] ? ' - ' . $range['to'] : '+' }} units
                                                        </div>
                                                        <div class="text-sm font-semibold text-yellow-900">
                                                            {{ $priceSource->currency ?? 'USD' }} ${{ number_format(floatval($range['price']), 2) }}
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    @else
                                        <div class="bg-blue-50 rounded-lg p-3">
                                            <div class="text-lg font-bold text-blue-900">
                                                {{ $priceSource->currency ?? 'USD' }} ${{ number_format(floatval($priceSource->pricing_ranges[0]['price'] ?? 0), 2) }}
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <!-- Stock Information by Source -->
                @if($product->quantities && $product->quantities->count() > 0)
                    <div class="mb-8">
                        <h3 class="text-lg font-medium text-gray-900 mb-3">Stock Information by Source</h3>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                            @foreach($product->quantities as $quantitySource)
                                <div class="border border-gray-200 rounded-lg p-4">
                                    <h4 class="text-sm font-semibold text-gray-900 uppercase tracking-wide mb-2">
                                        {{ $quantitySource->source_name }}
                                    </h4>
                                    <div class="space-y-2">
                                        <div class="flex justify-between">
                                            <span class="text-sm text-gray-600">Quantity:</span>
                                            <span class="text-sm font-semibold text-gray-900">{{ number_format($quantitySource->quantity) }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-sm text-gray-600">Unit:</span>
                                            <span class="text-sm font-semibold text-gray-900">{{ $quantitySource->unit }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-sm text-gray-600">Status:</span>
                                            <span class="text-sm font-semibold {{ $quantitySource->availability_status === 'in_stock' ? 'text-green-600' : 'text-yellow-600' }}">
                                                {{ ucwords(str_replace('_', ' ', $quantitySource->availability_status)) }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <!-- Action buttons -->
                <div class="flex flex-col sm:flex-row gap-4">
                    <button 
                        type="button" 
                        class="flex-1 bg-blue-600 text-white py-3 px-6 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors disabled:bg-gray-300 disabled:cursor-not-allowed"
                        {{ !$product->isInStock() ? 'disabled' : '' }}
                    >
                        {{ $product->isInStock() ? 'Add to Cart' : 'Out of Stock' }}
                    </button>
                    
                    <button 
                        type="button" 
                        class="bg-gray-200 text-gray-800 py-3 px-6 rounded-md hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition-colors"
                    >
                        Add to Wishlist
                    </button>
                </div>

                <!-- Back to search link -->
                <div class="mt-8 pt-8 border-t border-gray-200">
                    <a href="{{ route('products.search') }}" class="inline-flex items-center text-sm font-medium text-blue-600 hover:text-blue-500">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                        </svg>
                        Back to search results
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
