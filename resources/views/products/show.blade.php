<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $product->name }} - {{ config('app.name') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

</head>
<body class="h-full bg-gray-50 dark:bg-gray-900">
    <flux:header container class="border-b border-zinc-200 dark:border-zinc-700">
        <flux:sidebar.toggle class="lg:hidden" />

        <flux:brand href="/" logo="https://fluxui.dev/img/demo/logo.png" name="{{ config('app.name') }}" class="max-lg:hidden dark:hidden" />
        <flux:brand href="/" logo="https://fluxui.dev/img/demo/logo-dark.png" name="{{ config('app.name') }}" class="max-lg:hidden hidden dark:flex" />

        <flux:spacer />

        <flux:navbar class="-mr-3 max-lg:hidden">
            <flux:navbar.item href="{{ route('products.index') }}">Products</flux:navbar.item>
        </flux:navbar>
    </flux:header>

    <main class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Breadcrumb -->
            <flux:breadcrumbs class="mb-6">
                <flux:breadcrumbs.item href="{{ route('products.index') }}">Products</flux:breadcrumbs.item>
                <flux:breadcrumbs.item>{{ $product->name }}</flux:breadcrumbs.item>
            </flux:breadcrumbs>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- Product Image -->
                <div class="aspect-w-1 aspect-h-1 bg-white rounded-lg border border-gray-200">
                    @if($product->primary_image)
                        <img src="{{ $product->primary_image }}" alt="{{ $product->name }}" class="w-full h-full object-contain rounded-lg">
                    @else
                        <div class="flex items-center justify-center h-96">
                            <flux:icon name="photo" class="w-24 h-24 text-gray-400" />
                        </div>
                    @endif
                </div>

                <!-- Product Details -->
                <div class="space-y-6">
                    <div>
                        <flux:heading size="2xl">{{ $product->name }}</flux:heading>

                        <div class="mt-2 space-y-1">
                            <flux:text class="text-gray-600">{{ $product->manufacturer->name ?? 'Unknown Manufacturer' }}</flux:text>
                            <flux:text class="font-mono text-sm">Part #: {{ $product->mf_pnum }}</flux:text>
                            <flux:text class="font-mono text-sm">Internal #: {{ $product->pnum }}</flux:text>
                        </div>
                    </div>

                    @if($product->description)
                        <div>
                            <flux:heading size="xl">Description</flux:heading>
                            <flux:text class="mt-2">{{ $product->description }}</flux:text>
                        </div>
                    @endif

                    <!-- Status Badges -->
                    <div class="flex flex-wrap gap-2">
                        @if($product->hasStock())
                            <flux:badge variant="success">In Stock</flux:badge>
                        @else
                            <flux:badge variant="danger">Out of Stock</flux:badge>
                        @endif

                        @if($product->isRohsCompliant())
                            <flux:badge variant="primary">RoHS Compliant</flux:badge>
                        @endif

                        <flux:badge variant="outline">{{ $product->category->name ?? 'Uncategorized' }}</flux:badge>
                    </div>

                    <!-- Pricing -->
                    @if($product->lowest_price)
                        <div class="bg-green-50 dark:bg-green-900/20 rounded-lg p-4">
                            <flux:heading size="xl">Price</flux:heading>
                            <div class="text-3xl font-bold text-green-600 mt-1">
                                £{{ number_format($product->lowest_price, 2) }}
                            </div>
                            <flux:text size="sm" class="text-green-700 dark:text-green-300 mt-1">Starting from</flux:text>
                        </div>
                    @endif

                    <!-- Actions -->
                    <div class="flex gap-3">
                        <flux:button variant="primary" size="lg" class="flex-1">
                            Add to Quote
                        </flux:button>
                        <flux:button variant="outline" size="lg">
                            <flux:icon name="heart" class="w-5 h-5" />
                        </flux:button>
                        <flux:button variant="outline" size="lg">
                            <flux:icon name="share" class="w-5 h-5" />
                        </flux:button>
                    </div>
                </div>
            </div>

            <!-- Additional Information -->
            @if($product->attributes || $product->documents)
                <div class="mt-12 grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <!-- Specifications -->
                    @if(!empty($product->attributes))
                        <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6">
                            <flux:heading size="xl" class="mb-4">Specifications</flux:heading>
                            <div class="space-y-3">
                                @foreach(array_slice($product->attributes, 0, 10) as $spec)
                                    <div class="flex justify-between py-2 border-b border-gray-100 dark:border-gray-600 last:border-b-0">
                                        <flux:text class="font-medium">{{ $spec['aname'] ?? 'Property' }}</flux:text>
                                        <flux:text class="text-gray-600 dark:text-gray-300">{{ $spec['avalue'] ?? 'N/A' }}</flux:text>
                                    </div>
                                @endforeach

                                @if(count($product->attributes) > 10)
                                    <flux:text size="sm" class="text-gray-500 mt-3">
                                        ... and {{ count($product->attributes) - 10 }} more specifications
                                    </flux:text>
                                @endif
                            </div>
                        </div>
                    @endif

                    <!-- Documents -->
                    @if(!empty($product->documents))
                        <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6">
                            <flux:heading size="xl" class="mb-4">Documentation</flux:heading>
                            <div class="space-y-2">
                                @foreach(array_slice($product->documents, 0, 5) as $doc)
                                    <a
                                        href="{{ $doc['url'] ?? '#' }}"
                                        target="_blank"
                                        class="flex items-center gap-3 p-3 rounded-md hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                                    >
                                        <flux:icon name="document" class="w-5 h-5 text-blue-600" />
                                        <div>
                                            <flux:text class="font-medium">{{ $doc['doc_name'] ?? 'Document' }}</flux:text>
                                            <flux:text size="sm" class="text-gray-500">{{ $doc['folder_name'] ?? 'General' }}</flux:text>
                                        </div>
                                        <flux:icon name="link" class="w-4 h-4 text-gray-400 ml-auto" />
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </main>

    @fluxScripts
</body>
</html>
