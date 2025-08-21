<?php

use App\Models\Product;
use App\Models\Category;
use App\Models\Brand;
use App\Models\Manufacturer;
use App\Services\ProductSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestDatabaseHelper;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Configure PostgreSQL testing database
    TestDatabaseHelper::configurePgSQLTesting();
    
    // Ensure PostgreSQL extensions are available
    TestDatabaseHelper::ensurePostgreSQLExtensions();
    
    // Create base test data
    $this->category = Category::factory()->create(['name' => 'Test Category']);
    $this->brand = Brand::factory()->create(['name' => 'Test Brand']);
    $this->manufacturer = Manufacturer::factory()->create(['name' => 'Test Manufacturer']);
    
    $this->searchService = new ProductSearchService();
    
    // Clear cache before each test
    Cache::flush();
});

describe('ProductSearchService Performance Tests', function () {
    
    test('search performance with small dataset (100 products)', function () {
        // Create 100 products
        $products = Product::factory()->count(100)->create([
            'category_id' => $this->category->id,
            'brand_id' => $this->brand->id,
            'manufacturer_id' => $this->manufacturer->id,
            'category_name' => $this->category->name,
            'brand_name' => $this->brand->name,
            'manufacturer_name' => $this->manufacturer->name,
        ]);
        
        $startTime = microtime(true);
        $startMemory = memory_get_usage();
        
        $results = $this->searchService->search([
            'search' => 'test',
            'per_page' => 20,
            'page' => 1
        ]);
        
        $endTime = microtime(true);
        $endMemory = memory_get_usage();
        
        $executionTime = ($endTime - $startTime) * 1000; // Convert to milliseconds
        $memoryUsage = ($endMemory - $startMemory) / 1024; // Convert to KB
        
        // Performance assertions
        expect($executionTime)->toBeLessThan(500, "Search should complete in less than 500ms with 100 products");
        expect($memoryUsage)->toBeLessThan(5000, "Memory usage should be less than 5MB");
        expect($results)->toBeInstanceOf(\Illuminate\Pagination\LengthAwarePaginator::class);
        
        // Log performance metrics
        dump("Small dataset performance:", [
            'execution_time_ms' => round($executionTime, 2),
            'memory_usage_kb' => round($memoryUsage, 2),
            'results_count' => $results->count(),
        ]);
    });
    
    test('search performance with medium dataset (1000 products)', function () {
        // Create 1000 products in batches to avoid memory issues
        for ($i = 0; $i < 10; $i++) {
            Product::factory()->count(100)->create([
                'category_id' => $this->category->id,
                'brand_id' => $this->brand->id,
                'manufacturer_id' => $this->manufacturer->id,
                'category_name' => $this->category->name,
                'brand_name' => $this->brand->name,
                'manufacturer_name' => $this->manufacturer->name,
            ]);
        }
        
        $startTime = microtime(true);
        $startMemory = memory_get_usage();
        
        $results = $this->searchService->search([
            'search' => 'test',
            'per_page' => 20,
            'page' => 1
        ]);
        
        $endTime = microtime(true);
        $endMemory = memory_get_usage();
        
        $executionTime = ($endTime - $startTime) * 1000;
        $memoryUsage = ($endMemory - $startMemory) / 1024;
        
        expect($executionTime)->toBeLessThan(1000, "Search should complete in less than 1s with 1000 products");
        expect($memoryUsage)->toBeLessThan(10000, "Memory usage should be less than 10MB");
        expect($results->count())->toBeGreaterThan(0);
        
        dump("Medium dataset performance:", [
            'execution_time_ms' => round($executionTime, 2),
            'memory_usage_kb' => round($memoryUsage, 2),
            'results_count' => $results->count(),
        ]);
    });
    
    test('search performance with large dataset (5000 products)', function () {
        // Create 5000 products in batches
        for ($i = 0; $i < 50; $i++) {
            Product::factory()->count(100)->create([
                'category_id' => $this->category->id,
                'brand_id' => $this->brand->id,
                'manufacturer_id' => $this->manufacturer->id,
                'category_name' => $this->category->name,
                'brand_name' => $this->brand->name,
                'manufacturer_name' => $this->manufacturer->name,
            ]);
        }
        
        $startTime = microtime(true);
        $startMemory = memory_get_usage();
        
        $results = $this->searchService->search([
            'search' => 'test',
            'per_page' => 20,
            'page' => 1
        ]);
        
        $endTime = microtime(true);
        $endMemory = memory_get_usage();
        
        $executionTime = ($endTime - $startTime) * 1000;
        $memoryUsage = ($endMemory - $startMemory) / 1024;
        
        expect($executionTime)->toBeLessThan(2000, "Search should complete in less than 2s with 5000 products");
        expect($memoryUsage)->toBeLessThan(20000, "Memory usage should be less than 20MB");
        expect($results->count())->toBeGreaterThan(0);
        
        dump("Large dataset performance:", [
            'execution_time_ms' => round($executionTime, 2),
            'memory_usage_kb' => round($memoryUsage, 2),
            'results_count' => $results->count(),
        ]);
    });
    
    test('pagination performance across multiple pages', function () {
        // Create 500 products
        Product::factory()->count(500)->create([
            'category_id' => $this->category->id,
            'brand_id' => $this->brand->id,
            'manufacturer_id' => $this->manufacturer->id,
            'category_name' => $this->category->name,
            'brand_name' => $this->brand->name,
            'manufacturer_name' => $this->manufacturer->name,
        ]);
        
        $pageTimes = [];
        
        // Test first 10 pages
        for ($page = 1; $page <= 10; $page++) {
            $startTime = microtime(true);
            
            $results = $this->searchService->search([
                'search' => 'test',
                'per_page' => 20,
                'page' => $page
            ]);
            
            $endTime = microtime(true);
            $executionTime = ($endTime - $startTime) * 1000;
            $pageTimes[$page] = $executionTime;
            
            expect($executionTime)->toBeLessThan(1000, "Page {$page} should load in less than 1s");
            expect($results->count())->toBeGreaterThan(0);
        }
        
        $avgTime = array_sum($pageTimes) / count($pageTimes);
        $maxTime = max($pageTimes);
        
        expect($avgTime)->toBeLessThan(500, "Average page load time should be less than 500ms");
        
        dump("Pagination performance:", [
            'average_time_ms' => round($avgTime, 2),
            'max_time_ms' => round($maxTime, 2),
            'page_times' => array_map(fn($time) => round($time, 2), $pageTimes)
        ]);
    });
    
    test('complex filter performance', function () {
        // Create diverse test data
        $categories = Category::factory()->count(5)->create();
        $brands = Brand::factory()->count(5)->create();
        $manufacturers = Manufacturer::factory()->count(3)->create();
        
        // Create 1000 products with various attributes
        for ($i = 0; $i < 1000; $i++) {
            $category = $categories->random();
            $brand = $brands->random();
            $manufacturer = $manufacturers->random();
            
            Product::factory()->create([
                'category_id' => $category->id,
                'brand_id' => $brand->id,
                'manufacturer_id' => $manufacturer->id,
                'category_name' => $category->name,
                'brand_name' => $brand->name,
                'manufacturer_name' => $manufacturer->name,
                'price' => fake()->randomFloat(2, 10, 1000),
                'stock_quantity' => fake()->numberBetween(0, 100),
                'attributes' => [
                    'voltage' => fake()->randomElement(['12V', '18V', '20V', '24V']),
                    'battery' => fake()->randomElement(['Lithium Ion', 'NiMH', 'Lead Acid']),
                    'weight' => fake()->randomFloat(2, 0.5, 10)
                ]
            ]);
        }
        
        $startTime = microtime(true);
        $startMemory = memory_get_usage();
        
        // Complex search with multiple filters
        $results = $this->searchService->search([
            'search' => 'test',
            'categories' => $categories->take(2)->pluck('id')->toArray(),
            'brands' => $brands->take(3)->pluck('id')->toArray(),
            'min_price' => 50,
            'max_price' => 500,
            'in_stock' => true,
            'sort_by' => 'price',
            'sort_order' => 'asc',
            'per_page' => 20
        ]);
        
        $endTime = microtime(true);
        $endMemory = memory_get_usage();
        
        $executionTime = ($endTime - $startTime) * 1000;
        $memoryUsage = ($endMemory - $startMemory) / 1024;
        
        expect($executionTime)->toBeLessThan(1500, "Complex filtered search should complete in less than 1.5s");
        expect($memoryUsage)->toBeLessThan(15000, "Memory usage should be reasonable");
        
        dump("Complex filter performance:", [
            'execution_time_ms' => round($executionTime, 2),
            'memory_usage_kb' => round($memoryUsage, 2),
            'results_count' => $results->count(),
        ]);
    });
    
    test('cache performance comparison', function () {
        // Create test data
        Product::factory()->count(500)->create([
            'category_id' => $this->category->id,
            'brand_id' => $this->brand->id,
            'manufacturer_id' => $this->manufacturer->id,
            'category_name' => $this->category->name,
            'brand_name' => $this->brand->name,
            'manufacturer_name' => $this->manufacturer->name,
        ]);
        
        // First call (cache miss)
        Cache::flush();
        $startTime = microtime(true);
        $filterOptions = $this->searchService->getFilterOptions();
        $firstCallTime = (microtime(true) - $startTime) * 1000;
        
        // Second call (cache hit)
        $startTime = microtime(true);
        $filterOptions2 = $this->searchService->getFilterOptions();
        $secondCallTime = (microtime(true) - $startTime) * 1000;
        
        // Cache should significantly improve performance
        expect($secondCallTime)->toBeLessThan($firstCallTime / 2, "Cached call should be at least 50% faster");
        expect($filterOptions)->toEqual($filterOptions2, "Cached data should be identical");
        
        dump("Cache performance:", [
            'first_call_ms' => round($firstCallTime, 2),
            'second_call_ms' => round($secondCallTime, 2),
            'improvement_percent' => round((($firstCallTime - $secondCallTime) / $firstCallTime) * 100, 2)
        ]);
    });
    
    test('search suggestions performance', function () {
        // Create products with varied names for suggestions
        $productNames = [
            'DeWalt Power Drill', 'DeWalt Impact Driver', 'DeWalt Circular Saw',
            'Milwaukee Hammer Drill', 'Milwaukee Angle Grinder', 'Milwaukee Reciprocating Saw',
            'Makita Router', 'Makita Planer', 'Makita Orbital Sander'
        ];
        
        foreach ($productNames as $name) {
            Product::factory()->create([
                'name' => $name,
                'category_id' => $this->category->id,
                'brand_id' => $this->brand->id,
                'manufacturer_id' => $this->manufacturer->id,
                'category_name' => $this->category->name,
                'brand_name' => $this->brand->name,
                'manufacturer_name' => $this->manufacturer->name,
            ]);
        }
        
        $searchTerms = ['De', 'Mil', 'Mak', 'Power', 'Drill'];
        $totalTime = 0;
        
        foreach ($searchTerms as $term) {
            $startTime = microtime(true);
            $suggestions = $this->searchService->getSearchSuggestions($term, 5);
            $endTime = microtime(true);
            
            $executionTime = ($endTime - $startTime) * 1000;
            $totalTime += $executionTime;
            
            expect($executionTime)->toBeLessThan(200, "Suggestion search for '{$term}' should be fast");
            expect($suggestions)->toBeArray();
        }
        
        $avgTime = $totalTime / count($searchTerms);
        
        expect($avgTime)->toBeLessThan(100, "Average suggestion time should be very fast");
        
        dump("Search suggestions performance:", [
            'average_time_ms' => round($avgTime, 2),
            'total_time_ms' => round($totalTime, 2),
            'searches_count' => count($searchTerms)
        ]);
    });
    
    test('database query count optimization', function () {
        // Create test data
        Product::factory()->count(100)->create([
            'category_id' => $this->category->id,
            'brand_id' => $this->brand->id,
            'manufacturer_id' => $this->manufacturer->id,
            'category_name' => $this->category->name,
            'brand_name' => $this->brand->name,
            'manufacturer_name' => $this->manufacturer->name,
        ]);
        
        // Enable query logging
        DB::enableQueryLog();
        
        $results = $this->searchService->search([
            'search' => 'test',
            'per_page' => 20,
            'page' => 1
        ]);
        
        $queries = DB::getQueryLog();
        $queryCount = count($queries);
        
        // Search should use minimal queries
        expect($queryCount)->toBeLessThan(5, "Search should use minimal database queries");
        
        // Log query information for analysis
        dump("Database queries:", [
            'query_count' => $queryCount,
            'queries' => array_map(function ($query) {
                return [
                    'sql' => $query['query'],
                    'time' => $query['time']
                ];
            }, $queries)
        ]);
        
        DB::disableQueryLog();
    });
});