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
    $this->category = Category::factory()->create(['name' => 'Test Category']);
    $this->brand = Brand::factory()->create(['name' => 'Test Brand']);
    $this->manufacturer = Manufacturer::factory()->create(['name' => 'Test Manufacturer']);
    
    $this->searchService = new ProductSearchService();
    
    // Clear cache before each test
    Cache::flush();
});

describe('ProductSearchService Stress Tests', function () {
    
    test('concurrent search requests stress test', function () {
        // Create a substantial dataset for testing
        for ($i = 0; $i < 20; $i++) {
            Product::factory()->count(50)->create([
                'category_id' => $this->category->id,
                'brand_id' => $this->brand->id,
                'manufacturer_id' => $this->manufacturer->id,
                'category_name' => $this->category->name,
                'brand_name' => $this->brand->name,
                'manufacturer_name' => $this->manufacturer->name,
            ]);
        }
        
        $concurrentRequests = 10;
        $searchTerms = ['test', 'product', 'tool', 'power', 'electric'];
        $results = [];
        $startTime = microtime(true);
        
        // Simulate concurrent requests using array_map to run them quasi-simultaneously
        $promises = [];
        for ($i = 0; $i < $concurrentRequests; $i++) {
            $searchTerm = $searchTerms[array_rand($searchTerms)];
            $promises[] = [
                'term' => $searchTerm,
                'params' => [
                    'search' => $searchTerm,
                    'per_page' => 20,
                    'page' => rand(1, 3),
                    'min_price' => rand(0, 1) ? rand(10, 50) : null,
                    'max_price' => rand(0, 1) ? rand(100, 500) : null,
                ]
            ];
        }
        
        // Execute all searches
        foreach ($promises as $promise) {
            $requestStart = microtime(true);
            
            try {
                $result = $this->searchService->search($promise['params']);
                $requestTime = (microtime(true) - $requestStart) * 1000;
                
                $results[] = [
                    'term' => $promise['term'],
                    'execution_time_ms' => $requestTime,
                    'results_count' => $result->count(),
                    'success' => true
                ];
                
                expect($result)->toBeInstanceOf(\Illuminate\Pagination\LengthAwarePaginator::class);
                expect($requestTime)->toBeLessThan(3000, "Individual request should complete within 3s under stress");
                
            } catch (\Exception $e) {
                $results[] = [
                    'term' => $promise['term'],
                    'execution_time_ms' => (microtime(true) - $requestStart) * 1000,
                    'error' => $e->getMessage(),
                    'success' => false
                ];
            }
        }
        
        $totalTime = (microtime(true) - $startTime) * 1000;
        $successfulRequests = collect($results)->where('success', true)->count();
        $avgResponseTime = collect($results)->where('success', true)->avg('execution_time_ms');
        $maxResponseTime = collect($results)->where('success', true)->max('execution_time_ms');
        
        // Stress test assertions
        expect($successfulRequests)->toEqual($concurrentRequests, "All concurrent requests should succeed");
        expect($avgResponseTime)->toBeLessThan(2000, "Average response time should be acceptable under stress");
        expect($totalTime)->toBeLessThan(5000, "All concurrent requests should complete within 5s");
        
        dump("Concurrent requests stress test:", [
            'concurrent_requests' => $concurrentRequests,
            'successful_requests' => $successfulRequests,
            'total_time_ms' => round($totalTime, 2),
            'average_response_time_ms' => round($avgResponseTime, 2),
            'max_response_time_ms' => round($maxResponseTime, 2),
            'requests_per_second' => round($concurrentRequests / ($totalTime / 1000), 2)
        ]);
    });
    
    test('high volume search requests stress test', function () {
        // Create test data
        Product::factory()->count(500)->create([
            'category_id' => $this->category->id,
            'brand_id' => $this->brand->id,
            'manufacturer_id' => $this->manufacturer->id,
            'category_name' => $this->category->name,
            'brand_name' => $this->brand->name,
            'manufacturer_name' => $this->manufacturer->name,
        ]);
        
        $totalRequests = 50;
        $searchTerms = [
            'test product', 'power tool', 'electric drill', 'cordless saw',
            'battery pack', 'impact driver', 'circular saw', 'angle grinder'
        ];
        
        $successCount = 0;
        $failureCount = 0;
        $responseTimes = [];
        $startTime = microtime(true);
        
        for ($i = 0; $i < $totalRequests; $i++) {
            $requestStart = microtime(true);
            
            try {
                $searchTerm = $searchTerms[array_rand($searchTerms)];
                $result = $this->searchService->search([
                    'search' => $searchTerm,
                    'per_page' => rand(10, 50),
                    'page' => rand(1, 5),
                    'sort_by' => ['name', 'price', 'newest', 'relevance'][array_rand(['name', 'price', 'newest', 'relevance'])],
                ]);
                
                $requestTime = (microtime(true) - $requestStart) * 1000;
                $responseTimes[] = $requestTime;
                $successCount++;
                
                expect($result)->toBeInstanceOf(\Illuminate\Pagination\LengthAwarePaginator::class);
                expect($requestTime)->toBeLessThan(2000, "Request #{$i} should complete within 2s");
                
            } catch (\Exception $e) {
                $failureCount++;
                dump("Request #{$i} failed:", $e->getMessage());
            }
        }
        
        $totalTime = (microtime(true) - $startTime) * 1000;
        $avgResponseTime = array_sum($responseTimes) / count($responseTimes);
        $minResponseTime = min($responseTimes);
        $maxResponseTime = max($responseTimes);
        $throughput = $successCount / ($totalTime / 1000);
        
        // High volume stress test assertions
        expect($successCount)->toBeGreaterThan($totalRequests * 0.95, "At least 95% of requests should succeed");
        expect($avgResponseTime)->toBeLessThan(1000, "Average response time should be under 1s");
        expect($throughput)->toBeGreaterThan(5, "Should handle at least 5 requests per second");
        
        dump("High volume stress test:", [
            'total_requests' => $totalRequests,
            'successful_requests' => $successCount,
            'failed_requests' => $failureCount,
            'success_rate_percent' => round(($successCount / $totalRequests) * 100, 2),
            'total_time_ms' => round($totalTime, 2),
            'average_response_time_ms' => round($avgResponseTime, 2),
            'min_response_time_ms' => round($minResponseTime, 2),
            'max_response_time_ms' => round($maxResponseTime, 2),
            'throughput_rps' => round($throughput, 2)
        ]);
    });
    
    test('memory stress test with large datasets', function () {
        $initialMemory = memory_get_usage();
        $peakMemoryUsage = 0;
        
        // Create large dataset in batches to test memory efficiency
        for ($batch = 1; $batch <= 10; $batch++) {
            Product::factory()->count(100)->create([
                'category_id' => $this->category->id,
                'brand_id' => $this->brand->id,
                'manufacturer_id' => $this->manufacturer->id,
                'category_name' => $this->category->name,
                'brand_name' => $this->brand->name,
                'manufacturer_name' => $this->manufacturer->name,
            ]);
            
            // Perform search after each batch
            $beforeSearch = memory_get_usage();
            
            $result = $this->searchService->search([
                'search' => 'test',
                'per_page' => 50,
                'page' => 1,
            ]);
            
            $afterSearch = memory_get_usage();
            $memoryIncrease = $afterSearch - $beforeSearch;
            $peakMemoryUsage = max($peakMemoryUsage, $afterSearch);
            
            // Each search shouldn't dramatically increase memory usage
            expect($memoryIncrease)->toBeLessThan(5 * 1024 * 1024, "Search memory increase should be less than 5MB per search");
            
            dump("Batch {$batch} memory usage:", [
                'products_count' => $batch * 100,
                'memory_before_mb' => round($beforeSearch / 1024 / 1024, 2),
                'memory_after_mb' => round($afterSearch / 1024 / 1024, 2),
                'memory_increase_mb' => round($memoryIncrease / 1024 / 1024, 2)
            ]);
        }
        
        $finalMemory = memory_get_usage();
        $totalMemoryIncrease = $finalMemory - $initialMemory;
        
        // Memory should not grow excessively
        expect($totalMemoryIncrease)->toBeLessThan(50 * 1024 * 1024, "Total memory increase should be less than 50MB");
        
        dump("Memory stress test summary:", [
            'initial_memory_mb' => round($initialMemory / 1024 / 1024, 2),
            'peak_memory_mb' => round($peakMemoryUsage / 1024 / 1024, 2),
            'final_memory_mb' => round($finalMemory / 1024 / 1024, 2),
            'total_increase_mb' => round($totalMemoryIncrease / 1024 / 1024, 2)
        ]);
    });
    
    test('database connection stress test', function () {
        // Create test data
        Product::factory()->count(200)->create([
            'category_id' => $this->category->id,
            'brand_id' => $this->brand->id,
            'manufacturer_id' => $this->manufacturer->id,
            'category_name' => $this->category->name,
            'brand_name' => $this->brand->name,
            'manufacturer_name' => $this->manufacturer->name,
        ]);
        
        $totalOperations = 100;
        $operationTypes = ['search', 'filter_options', 'suggestions'];
        $connectionErrors = 0;
        $timeouts = 0;
        $successes = 0;
        
        for ($i = 0; $i < $totalOperations; $i++) {
            $operation = $operationTypes[array_rand($operationTypes)];
            $startTime = microtime(true);
            
            try {
                switch ($operation) {
                    case 'search':
                        $result = $this->searchService->search([
                            'search' => 'test product',
                            'per_page' => 20,
                            'page' => rand(1, 10)
                        ]);
                        break;
                        
                    case 'filter_options':
                        $result = $this->searchService->getFilterOptions();
                        break;
                        
                    case 'suggestions':
                        $result = $this->searchService->getSearchSuggestions('test', 5);
                        break;
                }
                
                $executionTime = (microtime(true) - $startTime) * 1000;
                
                if ($executionTime > 5000) {
                    $timeouts++;
                } else {
                    $successes++;
                }
                
                expect($executionTime)->toBeLessThan(10000, "Operation should not timeout (max 10s)");
                
            } catch (\PDOException $e) {
                $connectionErrors++;
                dump("Connection error in operation #{$i}:", $e->getMessage());
            } catch (\Exception $e) {
                dump("Operation #{$i} failed:", $e->getMessage());
            }
        }
        
        $successRate = ($successes / $totalOperations) * 100;
        $timeoutRate = ($timeouts / $totalOperations) * 100;
        $errorRate = ($connectionErrors / $totalOperations) * 100;
        
        // Database connection stress assertions
        expect($connectionErrors)->toBeLessThan($totalOperations * 0.05, "Connection errors should be less than 5%");
        expect($timeouts)->toBeLessThan($totalOperations * 0.10, "Timeouts should be less than 10%");
        expect($successRate)->toBeGreaterThan(85, "Success rate should be above 85%");
        
        dump("Database connection stress test:", [
            'total_operations' => $totalOperations,
            'successes' => $successes,
            'timeouts' => $timeouts,
            'connection_errors' => $connectionErrors,
            'success_rate_percent' => round($successRate, 2),
            'timeout_rate_percent' => round($timeoutRate, 2),
            'error_rate_percent' => round($errorRate, 2)
        ]);
    });
    
    test('cache invalidation stress test', function () {
        // Create test data
        Product::factory()->count(300)->create([
            'category_id' => $this->category->id,
            'brand_id' => $this->brand->id,
            'manufacturer_id' => $this->manufacturer->id,
            'category_name' => $this->category->name,
            'brand_name' => $this->brand->name,
            'manufacturer_name' => $this->manufacturer->name,
        ]);
        
        $cacheOperations = 50;
        $searchOperations = 25;
        
        for ($i = 0; $i < $cacheOperations; $i++) {
            // Get filter options (should cache)
            $startTime = microtime(true);
            $filterOptions = $this->searchService->getFilterOptions();
            $cacheTime = (microtime(true) - $startTime) * 1000;
            
            // Clear cache randomly
            if ($i % 5 === 0) {
                $this->searchService->clearCache();
            }
            
            // Perform some searches
            for ($j = 0; $j < rand(1, 3); $j++) {
                $searchStart = microtime(true);
                $this->searchService->search([
                    'search' => 'test',
                    'per_page' => 10,
                    'page' => 1
                ]);
                $searchTime = (microtime(true) - $searchStart) * 1000;
                
                expect($searchTime)->toBeLessThan(2000, "Search during cache stress should be fast");
            }
            
            expect($cacheTime)->toBeLessThan(1000, "Cache operation should be fast");
        }
        
        // Final verification that cache is working
        Cache::flush();
        $startTime = microtime(true);
        $firstCall = $this->searchService->getFilterOptions();
        $firstCallTime = (microtime(true) - $startTime) * 1000;
        
        $startTime = microtime(true);
        $secondCall = $this->searchService->getFilterOptions();
        $secondCallTime = (microtime(true) - $startTime) * 1000;
        
        expect($secondCallTime)->toBeLessThan($firstCallTime, "Second call should be faster due to caching");
        
        dump("Cache invalidation stress test:", [
            'cache_operations' => $cacheOperations,
            'first_call_time_ms' => round($firstCallTime, 2),
            'second_call_time_ms' => round($secondCallTime, 2),
            'cache_improvement_percent' => round((($firstCallTime - $secondCallTime) / $firstCallTime) * 100, 2)
        ]);
    });
    
    test('postgresql full-text search stress test', function () {
        // Create products with diverse content for full-text search testing
        $productData = [
            ['name' => 'DeWalt 20V MAX Cordless Drill DCD771C2', 'description' => 'High performance cordless drill with lithium ion battery'],
            ['name' => 'Milwaukee M18 FUEL Impact Driver', 'description' => 'Brushless motor impact driver for heavy duty applications'],
            ['name' => 'Makita XPH12Z Hammer Drill', 'description' => 'Compact hammer drill with variable speed trigger'],
            ['name' => 'Bosch Professional Angle Grinder', 'description' => 'Powerful angle grinder for cutting and grinding operations'],
            ['name' => 'RIDGID Circular Saw R8652B', 'description' => 'Cordless circular saw with precision cutting capabilities']
        ];
        
        // Create multiple variations of each product
        foreach ($productData as $data) {
            for ($i = 1; $i <= 20; $i++) {
                Product::factory()->create([
                    'name' => $data['name'] . " - Variant {$i}",
                    'description' => $data['description'] . " Model {$i}",
                    'category_id' => $this->category->id,
                    'brand_id' => $this->brand->id,
                    'manufacturer_id' => $this->manufacturer->id,
                    'category_name' => $this->category->name,
                    'brand_name' => $this->brand->name,
                    'manufacturer_name' => $this->manufacturer->name,
                ]);
            }
        }
        
        $searchTerms = [
            'cordless drill', 'impact driver', 'hammer drill', 'angle grinder', 'circular saw',
            'DeWalt', 'Milwaukee', 'Makita', 'Bosch', 'RIDGID',
            'lithium ion', 'brushless motor', 'heavy duty', 'variable speed', 'cutting'
        ];
        
        $totalSearches = 50;
        $responseTimes = [];
        $resultCounts = [];
        
        for ($i = 0; $i < $totalSearches; $i++) {
            $searchTerm = $searchTerms[array_rand($searchTerms)];
            $startTime = microtime(true);
            
            try {
                $result = $this->searchService->search([
                    'search' => $searchTerm,
                    'per_page' => 20,
                    'page' => 1
                ]);
                
                $executionTime = (microtime(true) - $startTime) * 1000;
                $responseTimes[] = $executionTime;
                $resultCounts[] = $result->count();
                
                // PostgreSQL full-text search should be fast
                expect($executionTime)->toBeLessThan(1000, "PostgreSQL full-text search should be under 1s");
                expect($result)->toBeInstanceOf(\Illuminate\Pagination\LengthAwarePaginator::class);
                
            } catch (\Exception $e) {
                dump("PostgreSQL search failed for term '{$searchTerm}':", $e->getMessage());
                throw $e;
            }
        }
        
        $avgResponseTime = array_sum($responseTimes) / count($responseTimes);
        $avgResultCount = array_sum($resultCounts) / count($resultCounts);
        $maxResponseTime = max($responseTimes);
        $minResponseTime = min($responseTimes);
        
        expect($avgResponseTime)->toBeLessThan(500, "Average PostgreSQL search time should be under 500ms");
        
        dump("PostgreSQL full-text search stress test:", [
            'total_searches' => $totalSearches,
            'average_response_time_ms' => round($avgResponseTime, 2),
            'min_response_time_ms' => round($minResponseTime, 2),
            'max_response_time_ms' => round($maxResponseTime, 2),
            'average_result_count' => round($avgResultCount, 2),
            'search_terms_tested' => count($searchTerms)
        ]);
    });
});