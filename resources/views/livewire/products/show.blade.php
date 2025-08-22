<?php

use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use App\Models\Product;

new #[Layout('components.layouts.public')]
class extends Component {
    public Product $product;

    public function mount(Product $product): void
    {
        // Ensure product is active before loading relationships
        if (!$product->isActive()) {
            abort(404);
        }
        
        // Load relationships for better performance
        $this->product = $product->load(['category', 'brand', 'manufacturer']);
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
                @if($product->images && count($product->images) > 1)
                    <div class="hidden mt-6 w-full max-w-2xl mx-auto sm:block lg:max-w-none">
                        <div class="grid grid-cols-4 gap-6">
                            @foreach($product->images as $index => $image)
                                <button class="relative h-24 bg-white rounded-md flex items-center justify-center text-sm font-medium uppercase text-gray-900 cursor-pointer hover:bg-gray-50 focus:outline-none focus:ring focus:ring-offset-4 focus:ring-blue-500">
                                    <span class="sr-only">{{ $product->name }} image {{ $index + 1 }}</span>
                                    <img src="/images/place_holder.svg" alt="{{ $product->name }}" class="w-full h-full object-center object-cover rounded-md">
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endif

                <!-- Main image -->
                <div class="w-full aspect-square">
                    <img src="/images/place_holder.svg" alt="{{ $product->name }}" class="w-full h-full object-center object-cover sm:rounded-lg">
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
                        <span class="font-mono">{{ $product->sku }}</span>
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
                            {!! nl2br(e($product->description)) !!}
                        </div>
                    </div>
                @endif

                <!-- Product Attributes -->
                @if($product->attributes && is_array($product->attributes) && count($product->attributes) > 0)
                    <div class="mb-8">
                        <h3 class="text-lg font-medium text-gray-900 mb-3">Specifications</h3>
                        <div class="bg-gray-50 rounded-lg p-4">
                            <dl class="grid grid-cols-1 gap-x-4 gap-y-3 sm:grid-cols-2">
                                @foreach($product->attributes as $key => $value)
                                    @if(!empty($value))
                                        <div>
                                            <dt class="text-sm font-medium text-gray-900 capitalize">{{ str_replace('_', ' ', $key) }}</dt>
                                            <dd class="text-sm text-gray-600">{{ is_array($value) ? implode(', ', $value) : $value }}</dd>
                                        </div>
                                    @endif
                                @endforeach
                            </dl>
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
