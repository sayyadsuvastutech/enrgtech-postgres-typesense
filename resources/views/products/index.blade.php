<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Product Search - {{ config('app.name') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

</head>
<body class="h-full bg-gray-50 dark:bg-gray-900">
    <flux:header container class="border-b border-zinc-200 dark:border-zinc-700">
        <flux:sidebar.toggle class="lg:hidden" />

        <flux:brand href="/" logo="https://fluxui.dev/img/demo/logo.png" name="{{ config('app.name') }}" class="max-lg:hidden dark:hidden" />
        <flux:brand href="/" logo="https://fluxui.dev/img/demo/logo-dark.png" name="{{ config('app.name') }}" class="max-lg:hidden hidden dark:flex" />

        <flux:spacer />

        <flux:navbar class="-mr-3 max-lg:hidden">
            <flux:navbar.item href="{{ route('products.index') }}" current>Products</flux:navbar.item>
        </flux:navbar>

        <flux:dropdown position="top" class="lg:hidden">
            <flux:button icon-trailing="chevron-up" size="sm">Menu</flux:button>
            <flux:menu>
                <flux:menu.item href="{{ route('products.index') }}" current>Products</flux:menu.item>
            </flux:menu>
        </flux:dropdown>
    </flux:header>

    <main class="py-6">
        @livewire('product-search')
    </main>

    @fluxScripts
</body>
</html>
