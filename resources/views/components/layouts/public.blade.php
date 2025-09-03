<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-gray-50 antialiased">
        <!-- Public Navigation Header -->
        <header class="bg-white shadow-sm border-b border-gray-200 sticky top-0 z-50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center h-16">
                    <!-- Logo -->
                    <div class="flex items-center">
                        <a href="{{ route('home') }}" class="flex items-center space-x-3" wire:navigate>
                            <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-600">
                                <span class="text-white font-bold text-sm">{{ substr(config('app.name', 'L'), 0, 1) }}</span>
                            </div>
                            <span class="text-xl font-bold text-gray-900">{{ config('app.name', 'Laravel') }}</span>
                        </a>
                    </div>

                    <!-- Navigation Links -->
                    <nav class="hidden md:flex items-center space-x-8">
                        <a href="{{ route('home') }}"
                           class="text-gray-600 hover:text-gray-900 px-3 py-2 rounded-md text-sm font-medium transition-colors {{ request()->routeIs('home') ? 'text-blue-600 bg-blue-50' : '' }}"
                           wire:navigate>
                            Home
                        </a>
                        <a href="{{ route('products.search') }}"
                           class="text-gray-600 hover:text-gray-900 px-3 py-2 rounded-md text-sm font-medium transition-colors {{ request()->routeIs('products.*') ? 'text-blue-600 bg-blue-50' : '' }}"
                           wire:navigate>
                            Products
                        </a>
                        <a href="{{ route('ecommerce.search') }}"
                           class="text-gray-600 hover:text-gray-900 px-3 py-2 rounded-md text-sm font-medium transition-colors {{ request()->routeIs('ecommerce.search') ? 'text-blue-600 bg-blue-50' : '' }}"
                           wire:navigate>
                            <div class="flex items-center space-x-1">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                </svg>
                                <span>Ecommerce Search</span>
                            </div>
                        </a>
                    </nav>

                    <!-- Auth Links -->
                    <div class="flex items-center space-x-4">
                        @auth
                            <a href="{{ route('dashboard') }}"
                               class="text-gray-600 hover:text-gray-900 px-3 py-2 rounded-md text-sm font-medium transition-colors"
                               wire:navigate>
                                Dashboard
                            </a>
                            <form method="POST" action="{{ route('logout') }}" class="inline">
                                @csrf
                                <button type="submit"
                                        class="text-gray-600 hover:text-gray-900 px-3 py-2 rounded-md text-sm font-medium transition-colors">
                                    Logout
                                </button>
                            </form>
                        @else
                            <a href="{{ route('login') }}"
                               class="text-gray-600 hover:text-gray-900 px-3 py-2 rounded-md text-sm font-medium transition-colors"
                               wire:navigate>
                                Login
                            </a>
                            <a href="{{ route('register') }}"
                               class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium transition-colors"
                               wire:navigate>
                                Register
                            </a>
                        @endauth
                    </div>

                    <!-- Mobile menu button -->
                    <div class="md:hidden">
                        <button type="button"
                                class="text-gray-600 hover:text-gray-900 hover:bg-gray-50 p-2 rounded-md"
                                x-data="{ open: false }"
                                @click="open = !open">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="min-h-screen bg-gray-50">
            {{ $slot }}
        </main>

        <!-- Footer -->
        <footer class="bg-white border-t border-gray-200">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <div class="text-center text-gray-600">
                    <p>&copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
                </div>
            </div>
        </footer>

        <!-- Lazy Loading Script -->
{{--        <script>--}}
{{--            document.addEventListener('DOMContentLoaded', function() {--}}
{{--                const lazyImages = document.querySelectorAll('img.lazy');--}}
{{--                --}}
{{--                if ('IntersectionObserver' in window) {--}}
{{--                    const imageObserver = new IntersectionObserver(function(entries, observer) {--}}
{{--                        entries.forEach(function(entry) {--}}
{{--                            if (entry.isIntersecting) {--}}
{{--                                const img = entry.target;--}}
{{--                                const actualSrc = img.getAttribute('data-src');--}}
{{--                                --}}
{{--                                if (actualSrc && actualSrc !== img.src) {--}}
{{--                                    img.src = actualSrc;--}}
{{--                                    img.classList.add('opacity-0');--}}
{{--                                    --}}
{{--                                    img.onload = function() {--}}
{{--                                        img.classList.remove('opacity-0');--}}
{{--                                        img.classList.add('opacity-100');--}}
{{--                                    };--}}
{{--                                    --}}
{{--                                    img.onerror = function() {--}}
{{--                                        img.src = '/images/place_holder.svg';--}}
{{--                                        img.classList.remove('opacity-0');--}}
{{--                                        img.classList.add('opacity-100');--}}
{{--                                    };--}}
{{--                                }--}}
{{--                                --}}
{{--                                img.classList.remove('lazy');--}}
{{--                                observer.unobserve(img);--}}
{{--                            }--}}
{{--                        });--}}
{{--                    }, {--}}
{{--                        rootMargin: '50px 0px',--}}
{{--                        threshold: 0.1--}}
{{--                    });--}}

{{--                    lazyImages.forEach(function(img) {--}}
{{--                        imageObserver.observe(img);--}}
{{--                    });--}}
{{--                } else {--}}
{{--                    // Fallback for browsers that don't support IntersectionObserver--}}
{{--                    lazyImages.forEach(function(img) {--}}
{{--                        const actualSrc = img.getAttribute('data-src');--}}
{{--                        if (actualSrc) {--}}
{{--                            img.src = actualSrc;--}}
{{--                        }--}}
{{--                    });--}}
{{--                }--}}
{{--            });--}}

{{--            // Re-initialize lazy loading when Livewire updates the DOM--}}
{{--            document.addEventListener('livewire:navigated', function() {--}}
{{--                const lazyImages = document.querySelectorAll('img.lazy');--}}
{{--                --}}
{{--                if ('IntersectionObserver' in window && lazyImages.length > 0) {--}}
{{--                    const imageObserver = new IntersectionObserver(function(entries, observer) {--}}
{{--                        entries.forEach(function(entry) {--}}
{{--                            if (entry.isIntersecting) {--}}
{{--                                const img = entry.target;--}}
{{--                                const actualSrc = img.getAttribute('data-src');--}}
{{--                                --}}
{{--                                if (actualSrc && actualSrc !== img.src) {--}}
{{--                                    img.src = actualSrc;--}}
{{--                                    img.classList.add('opacity-0');--}}
{{--                                    --}}
{{--                                    img.onload = function() {--}}
{{--                                        img.classList.remove('opacity-0');--}}
{{--                                        img.classList.add('opacity-100');--}}
{{--                                    };--}}
{{--                                    --}}
{{--                                    img.onerror = function() {--}}
{{--                                        img.src = '/images/place_holder.svg';--}}
{{--                                        img.classList.remove('opacity-0');--}}
{{--                                        img.classList.add('opacity-100');--}}
{{--                                    };--}}
{{--                                }--}}
{{--                                --}}
{{--                                img.classList.remove('lazy');--}}
{{--                                observer.unobserve(img);--}}
{{--                            }--}}
{{--                        });--}}
{{--                    }, {--}}
{{--                        rootMargin: '50px 0px',--}}
{{--                        threshold: 0.1--}}
{{--                    });--}}

{{--                    lazyImages.forEach(function(img) {--}}
{{--                        imageObserver.observe(img);--}}
{{--                    });--}}
{{--                }--}}
{{--            });--}}
{{--        </script>--}}
    </body>
</html>
