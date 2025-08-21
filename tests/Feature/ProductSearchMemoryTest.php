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
    
    // Create base test data
    $this->category = Category::factory()->create(['name' => 'Memory Test Category']);
    $this->brand = Brand::factory()->create(['name' => 'Memory Test Brand']);
    $this->manufacturer = Manufacturer::factory()->create(['name' => 'Memory Test Manufacturer']);
    
    $this->searchService = new ProductSearchService();
    
    // Clear cache before each test
    Cache::flush();
});

describe('ProductSearchService Memory Usage Tests', function () {
    
    test('memory usage with incremental dataset growth', function () {
        $baselineMemory = memory_get_usage(true);
        $memoryProfile = [];
        $batchSizes = [100, 500, 1000, 2500, 5000];
        
        foreach ($batchSizes as $totalSize) {
            // Clear any existing data and reset
            Product::query()->delete();
            Cache::flush();
            gc_collect_cycles(); // Force garbage collection
            
            $beforeCreation = memory_get_usage(true);
            
            // Create products in smaller batches to monitor memory usage
            for ($i = 0; $i < $totalSize; $i += 100) {
                $batchSize = min(100, $totalSize - $i);
                Product::factory()->count($batchSize)->create([
                    'category_id' => $this->category->id,
                    'brand_id' => $this->brand->id,
                    'manufacturer_id' => $this->manufacturer->id,
                    'category_name' => $this->category->name,
                    'brand_name' => $this->brand->name,
                    'manufacturer_name' => $this->manufacturer->name,
                    'attributes' => [
                        'voltage' => fake()->randomElement(['12V', '18V', '20V', '24V']),
                        'battery' => fake()->randomElement(['Lithium Ion', 'NiMH', 'Lead Acid']),
                        'weight' => fake()->randomFloat(2, 0.5, 10),
                        'dimensions' => [
                            'length' => fake()->numberBetween(10, 50),
                            'width' => fake()->numberBetween(5, 25),
                            'height' => fake()->numberBetween(5, 25)
                        ]
                    ]
                ]);
                
                // Force garbage collection between batches
                if ($i % 500 === 0) {
                    gc_collect_cycles();
                }
            }
            
            $afterCreation = memory_get_usage(true);
            
            // Perform search operations and measure memory usage
            $beforeSearch = memory_get_usage(true);
            $peakMemory = $beforeSearch;
            
            $searchResults = [];
            $searchTerms = ['test', 'product', 'tool', 'power', 'electric', 'battery', 'voltage'];
            
            foreach ($searchTerms as $term) {
                $result = $this->searchService->search([
                    'search' => $term,
                    'per_page' => 50,
                    'page' => 1
                ]);
                
                $currentMemory = memory_get_usage(true);
                $peakMemory = max($peakMemory, $currentMemory);
                
                $searchResults[] = [
                    'term' => $term,
                    'results_count' => $result->count(),
                    'memory_after_search' => $currentMemory
                ];
                
                expect($result)->toBeInstanceOf(\Illuminate\Pagination\LengthAwarePaginator::class);
            }
            
            $afterSearch = memory_get_usage(true);
            
            // Test pagination memory usage
            $paginationMemoryStart = memory_get_usage(true);
            for ($page = 1; $page <= 10; $page++) {
                $this->searchService->search([
                    'search' => 'test',
                    'per_page' => 20,
                    'page' => $page
                ]);
                
                $paginationMemory = memory_get_usage(true);
                $peakMemory = max($peakMemory, $paginationMemory);
            }
            $paginationMemoryEnd = memory_get_usage(true);
            
            // Test filter operations memory usage
            $filterMemoryStart = memory_get_usage(true);
            $filterOptions = $this->searchService->getFilterOptions();
            $filterMemoryEnd = memory_get_usage(true);
            
            // Test suggestions memory usage
            $suggestionsMemoryStart = memory_get_usage(true);
            $suggestions = $this->searchService->getSearchSuggestions('test', 10);
            $suggestionsMemoryEnd = memory_get_usage(true);
            
            $peakMemory = max($peakMemory, $filterMemoryEnd, $suggestionsMemoryEnd);
            
            $memoryProfile[$totalSize] = [
                'dataset_size' => $totalSize,
                'baseline_memory_mb' => round($baselineMemory / 1024 / 1024, 2),
                'before_creation_mb' => round($beforeCreation / 1024 / 1024, 2),
                'after_creation_mb' => round($afterCreation / 1024 / 1024, 2),
                'creation_memory_increase_mb' => round(($afterCreation - $beforeCreation) / 1024 / 1024, 2),
                'before_search_mb' => round($beforeSearch / 1024 / 1024, 2),
                'after_search_mb' => round($afterSearch / 1024 / 1024, 2),
                'search_memory_increase_mb' => round(($afterSearch - $beforeSearch) / 1024 / 1024, 2),
                'pagination_memory_increase_mb' => round(($paginationMemoryEnd - $paginationMemoryStart) / 1024 / 1024, 2),
                'filter_memory_increase_mb' => round(($filterMemoryEnd - $filterMemoryStart) / 1024 / 1024, 2),
                'suggestions_memory_increase_mb' => round(($suggestionsMemoryEnd - $suggestionsMemoryStart) / 1024 / 1024, 2),
                'peak_memory_mb' => round($peakMemory / 1024 / 1024, 2),
                'total_memory_increase_mb' => round(($peakMemory - $baselineMemory) / 1024 / 1024, 2),
                'search_results' => $searchResults
            ];
            
            // Memory usage expectations based on dataset size
            $expectedMaxMemoryIncrease = match(true) {
                $totalSize <= 500 => 20,    // 20MB max increase
                $totalSize <= 1000 => 40,   // 40MB max increase
                $totalSize <= 2500 => 75,   // 75MB max increase
                $totalSize <= 5000 => 120,  // 120MB max increase
                default => 200              // 200MB max increase
            };
            
            expect($memoryProfile[$totalSize]['total_memory_increase_mb'])
                ->toBeLessThan($expectedMaxMemoryIncrease, 
                    "Memory increase for {$totalSize} products should be less than {$expectedMaxMemoryIncrease}MB");
            
            // Search operations should not use excessive memory
            expect($memoryProfile[$totalSize]['search_memory_increase_mb'])
                ->toBeLessThan(10, "Search operations memory increase should be less than 10MB");
                
            dump("Memory profile for {$totalSize} products:", $memoryProfile[$totalSize]);
        }
        
        dump("Complete Memory Usage Profile:", $memoryProfile);
        
        // Analyze memory scaling efficiency
        $this->analyzeMemoryScaling($memoryProfile);
    });
    
    test('memory efficiency during concurrent operations', function () {
        // Create moderate dataset
        Product::factory()->count(500)->create([
            'category_id' => $this->category->id,
            'brand_id' => $this->brand->id,
            'manufacturer_id' => $this->manufacturer->id,
            'category_name' => $this->category->name,
            'brand_name' => $this->brand->name,
            'manufacturer_name' => $this->manufacturer->name,
        ]);
        
        $baselineMemory = memory_get_usage(true);
        $memorySnapshots = [];
        
        // Simulate concurrent operations
        $operations = [
            'search_basic' => fn() => $this->searchService->search(['search' => 'test', 'per_page' => 20]),
            'search_filtered' => fn() => $this->searchService->search([
                'search' => 'test',
                'min_price' => 50,
                'max_price' => 500,
                'per_page' => 20
            ]),
            'filter_options' => fn() => $this->searchService->getFilterOptions(),
            'suggestions' => fn() => $this->searchService->getSearchSuggestions('test', 5),
            'pagination' => fn() => $this->searchService->search(['search' => 'test', 'per_page' => 10, 'page' => 5])
        ];
        
        // Execute operations in sequence and monitor memory
        foreach ($operations as $operationName => $operation) {
            $beforeOperation = memory_get_usage(true);
            
            // Execute operation multiple times to simulate concurrent load
            for ($i = 0; $i < 5; $i++) {
                $result = $operation();
                
                expect($result)->not->toBeNull();
                
                $memorySnapshots[] = [
                    'operation' => $operationName,
                    'iteration' => $i + 1,
                    'memory_mb' => round(memory_get_usage(true) / 1024 / 1024, 2)
                ];
            }
            
            $afterOperation = memory_get_usage(true);
            $operationMemoryIncrease = ($afterOperation - $beforeOperation) / 1024 / 1024;
            
            // Individual operation memory increase should be reasonable
            expect($operationMemoryIncrease)->toBeLessThan(5, 
                "Operation '{$operationName}' memory increase should be less than 5MB");
            
            dump("Operation '{$operationName}' memory analysis:", [
                'before_mb' => round($beforeOperation / 1024 / 1024, 2),
                'after_mb' => round($afterOperation / 1024 / 1024, 2),
                'increase_mb' => round($operationMemoryIncrease, 2)
            ]);
        }
        
        $finalMemory = memory_get_usage(true);
        $totalMemoryIncrease = ($finalMemory - $baselineMemory) / 1024 / 1024;
        
        // Total memory increase should be reasonable
        expect($totalMemoryIncrease)->toBeLessThan(25, "Total memory increase should be less than 25MB");
        
        dump("Concurrent operations memory summary:", [
            'baseline_memory_mb' => round($baselineMemory / 1024 / 1024, 2),
            'final_memory_mb' => round($finalMemory / 1024 / 1024, 2),
            'total_increase_mb' => round($totalMemoryIncrease, 2),
            'memory_snapshots' => $memorySnapshots
        ]);
    });
    
    test('memory leak detection in repetitive operations', function () {
        // Create test dataset
        Product::factory()->count(200)->create([
            'category_id' => $this->category->id,
            'brand_id' => $this->brand->id,
            'manufacturer_id' => $this->manufacturer->id,
            'category_name' => $this->category->name,
            'brand_name' => $this->brand->name,
            'manufacturer_name' => $this->manufacturer->name,
        ]);
        
        $memoryMeasurements = [];
        $iterations = 50;
        $operations = ['search', 'filters', 'suggestions'];
        
        foreach ($operations as $operationType) {
            $operationMemory = [];
            
            for ($i = 0; $i < $iterations; $i++) {
                $beforeIteration = memory_get_usage(true);
                
                switch ($operationType) {
                    case 'search':
                        $this->searchService->search([
                            'search' => 'test product ' . $i,
                            'per_page' => 20,
                            'page' => ($i % 5) + 1
                        ]);
                        break;
                        
                    case 'filters':
                        $this->searchService->getFilterOptions();
                        break;
                        
                    case 'suggestions':
                        $this->searchService->getSearchSuggestions('test' . ($i % 10), 5);
                        break;
                }
                
                $afterIteration = memory_get_usage(true);
                $operationMemory[] = $afterIteration;
                
                // Force garbage collection every 10 iterations
                if ($i % 10 === 0) {
                    gc_collect_cycles();
                }
            }
            
            $memoryMeasurements[$operationType] = $operationMemory;
            
            // Analyze memory growth trend
            $firstQuarterAvg = array_sum(array_slice($operationMemory, 0, 12)) / 12;
            $lastQuarterAvg = array_sum(array_slice($operationMemory, -12)) / 12;
            
            $memoryGrowth = ($lastQuarterAvg - $firstQuarterAvg) / 1024 / 1024; // MB
            
            // Memory should not grow significantly over iterations (potential leak detection)
            expect($memoryGrowth)->toBeLessThan(10, 
                "Memory growth for '{$operationType}' should be less than 10MB over {$iterations} iterations");
            
            $maxMemory = max($operationMemory);
            $minMemory = min($operationMemory);
            $memoryVariance = ($maxMemory - $minMemory) / 1024 / 1024;
            
            dump("Memory leak analysis for '{$operationType}':", [
                'iterations' => $iterations,
                'first_quarter_avg_mb' => round($firstQuarterAvg / 1024 / 1024, 2),
                'last_quarter_avg_mb' => round($lastQuarterAvg / 1024 / 1024, 2),
                'memory_growth_mb' => round($memoryGrowth, 2),
                'max_memory_mb' => round($maxMemory / 1024 / 1024, 2),
                'min_memory_mb' => round($minMemory / 1024 / 1024, 2),
                'memory_variance_mb' => round($memoryVariance, 2)
            ]);
        }
    });
    
    test('large result set memory handling', function () {
        // Create large dataset with complex attributes
        for ($i = 0; $i < 2000; $i++) {
            Product::factory()->create([
                'category_id' => $this->category->id,
                'brand_id' => $this->brand->id,
                'manufacturer_id' => $this->manufacturer->id,
                'category_name' => $this->category->name,
                'brand_name' => $this->brand->name,
                'manufacturer_name' => $this->manufacturer->name,
                'attributes' => [
                    'specifications' => [
                        'voltage' => fake()->randomElement(['12V', '18V', '20V', '24V']),
                        'battery_capacity' => fake()->randomFloat(2, 1.5, 6.0),
                        'motor_type' => fake()->randomElement(['Brushed', 'Brushless']),
                        'torque' => fake()->numberBetween(50, 300),
                        'speed_settings' => fake()->numberBetween(1, 3)
                    ],
                    'features' => fake()->words(10),
                    'dimensions' => [
                        'length' => fake()->numberBetween(200, 400),
                        'width' => fake()->numberBetween(50, 150),
                        'height' => fake()->numberBetween(150, 250)
                    ],
                    'certifications' => fake()->words(5)
                ]
            ]);
            
            // Garbage collect every 200 products
            if ($i % 200 === 0) {
                gc_collect_cycles();
            }
        }
        
        $testScenarios = [
            'small_pages' => ['per_page' => 10, 'pages' => [1, 5, 10, 20, 50]],
            'medium_pages' => ['per_page' => 25, 'pages' => [1, 3, 6, 12, 25]],
            'large_pages' => ['per_page' => 50, 'pages' => [1, 2, 4, 8, 15]],
            'very_large_pages' => ['per_page' => 100, 'pages' => [1, 2, 3, 5, 10]]
        ];
        
        $scenarioResults = [];
        
        foreach ($testScenarios as $scenarioName => $scenario) {
            $scenarioMemory = [];
            $beforeScenario = memory_get_usage(true);
            
            foreach ($scenario['pages'] as $page) {
                $beforePage = memory_get_usage(true);
                
                $result = $this->searchService->search([
                    'search' => 'test',
                    'per_page' => $scenario['per_page'],
                    'page' => $page
                ]);
                
                $afterPage = memory_get_usage(true);
                $pageMemoryIncrease = ($afterPage - $beforePage) / 1024 / 1024;
                
                $scenarioMemory[] = [
                    'page' => $page,
                    'per_page' => $scenario['per_page'],
                    'results_count' => $result->count(),
                    'memory_before_mb' => round($beforePage / 1024 / 1024, 2),
                    'memory_after_mb' => round($afterPage / 1024 / 1024, 2),
                    'memory_increase_mb' => round($pageMemoryIncrease, 2)
                ];
                
                // Memory increase per page should be proportional and reasonable
                $expectedMaxIncrease = ($scenario['per_page'] / 10) * 2; // 2MB per 10 items
                expect($pageMemoryIncrease)->toBeLessThan($expectedMaxIncrease, 
                    "Page {$page} with {$scenario['per_page']} items should use less than {$expectedMaxIncrease}MB");
                
                expect($result)->toBeInstanceOf(\Illuminate\Pagination\LengthAwarePaginator::class);
            }
            
            $afterScenario = memory_get_usage(true);
            $scenarioMemoryIncrease = ($afterScenario - $beforeScenario) / 1024 / 1024;
            
            $scenarioResults[$scenarioName] = [
                'scenario_memory_increase_mb' => round($scenarioMemoryIncrease, 2),
                'page_details' => $scenarioMemory
            ];
            
            dump("Large result set scenario '{$scenarioName}':", $scenarioResults[$scenarioName]);
        }
    });
    
    test('cache memory efficiency and cleanup', function () {
        // Create test data
        Product::factory()->count(300)->create([
            'category_id' => $this->category->id,
            'brand_id' => $this->brand->id,
            'manufacturer_id' => $this->manufacturer->id,
            'category_name' => $this->category->name,
            'brand_name' => $this->brand->name,
            'manufacturer_name' => $this->manufacturer->name,
        ]);
        
        $baselineMemory = memory_get_usage(true);
        
        // Test cache memory usage
        $cacheOperations = [
            'filter_options' => fn() => $this->searchService->getFilterOptions(),
            'suggestions' => fn() => $this->searchService->getSearchSuggestions('test', 10)
        ];
        
        $cacheMemoryProfile = [];
        
        foreach ($cacheOperations as $operationName => $operation) {
            // Clear cache first
            Cache::flush();
            $beforeCacheMiss = memory_get_usage(true);
            
            // Cache miss
            $result1 = $operation();
            $afterCacheMiss = memory_get_usage(true);
            
            // Cache hit
            $beforeCacheHit = memory_get_usage(true);
            $result2 = $operation();
            $afterCacheHit = memory_get_usage(true);
            
            $cacheMissIncrease = ($afterCacheMiss - $beforeCacheMiss) / 1024 / 1024;
            $cacheHitIncrease = ($afterCacheHit - $beforeCacheHit) / 1024 / 1024;
            
            $cacheMemoryProfile[$operationName] = [
                'cache_miss_memory_increase_mb' => round($cacheMissIncrease, 2),
                'cache_hit_memory_increase_mb' => round($cacheHitIncrease, 2),
                'memory_efficiency' => $cacheHitIncrease < ($cacheMissIncrease * 0.1) // Cache hit should use <10% of miss memory
            ];
            
            // Cache hit should use significantly less memory
            expect($cacheHitIncrease)->toBeLessThan($cacheMissIncrease * 0.2, 
                "Cache hit for '{$operationName}' should use less than 20% of cache miss memory");
            
            expect($result1)->toEqual($result2, "Cached and non-cached results should be identical");
        }
        
        // Test cache cleanup
        $beforeCacheFlush = memory_get_usage(true);
        Cache::flush();
        gc_collect_cycles(); // Force garbage collection after cache flush
        $afterCacheFlush = memory_get_usage(true);
        
        $cacheCleanupMemory = ($beforeCacheFlush - $afterCacheFlush) / 1024 / 1024;
        
        $finalMemory = memory_get_usage(true);
        $totalMemoryIncrease = ($finalMemory - $baselineMemory) / 1024 / 1024;
        
        dump("Cache memory efficiency analysis:", [
            'baseline_memory_mb' => round($baselineMemory / 1024 / 1024, 2),
            'final_memory_mb' => round($finalMemory / 1024 / 1024, 2),
            'total_memory_increase_mb' => round($totalMemoryIncrease, 2),
            'cache_cleanup_freed_mb' => round($cacheCleanupMemory, 2),
            'operation_profiles' => $cacheMemoryProfile
        ]);
        
        // Overall memory usage should be reasonable
        expect($totalMemoryIncrease)->toBeLessThan(15, "Total memory increase should be less than 15MB");
    });
    
    private function analyzeMemoryScaling(array $memoryProfile): void
    {
        $report = "=== MEMORY SCALING ANALYSIS ===\n\n";
        
        $sizes = array_keys($memoryProfile);
        sort($sizes);
        
        $report .= "Dataset Size vs Memory Usage:\n";
        foreach ($sizes as $size) {
            $profile = $memoryProfile[$size];
            $report .= "  {$size} products: {$profile['total_memory_increase_mb']}MB total increase\n";
        }
        
        $report .= "\nMemory Efficiency Analysis:\n";
        for ($i = 1; $i < count($sizes); $i++) {
            $prevSize = $sizes[$i-1];
            $currentSize = $sizes[$i];
            
            $sizeRatio = $currentSize / $prevSize;
            $memoryRatio = $memoryProfile[$currentSize]['total_memory_increase_mb'] / $memoryProfile[$prevSize]['total_memory_increase_mb'];
            
            $efficiency = $sizeRatio / $memoryRatio;
            $scalingType = match(true) {
                $efficiency > 1.2 => 'Excellent (sub-linear)',
                $efficiency > 0.8 => 'Good (linear)', 
                $efficiency > 0.5 => 'Acceptable',
                default => 'Poor (super-linear)'
            };
            
            $report .= "  {$prevSize} → {$currentSize}: {$sizeRatio}x data, {$memoryRatio}x memory ({$scalingType})\n";
        }
        
        dump($report);
    }
});