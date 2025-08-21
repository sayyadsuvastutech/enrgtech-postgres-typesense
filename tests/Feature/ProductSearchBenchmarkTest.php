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

describe('ProductSearchService Benchmark Tests', function () {

    test('search performance baseline benchmark', function () {
        $datasets = [
            ['size' => 100, 'name' => 'small'],
            ['size' => 500, 'name' => 'medium'],
            ['size' => 1000, 'name' => 'large'],
            ['size' => 2500, 'name' => 'very_large']
        ];

        $benchmarkResults = [];

        foreach ($datasets as $dataset) {
            // Create dataset
            for ($i = 0; $i < $dataset['size']; $i += 100) {
                $batch = min(100, $dataset['size'] - $i);
                Product::factory()->count($batch)->create([
                    'category_id' => $this->category->id,
                    'brand_id' => $this->brand->id,
                    'manufacturer_id' => $this->manufacturer->id,
                    'category_name' => $this->category->name,
                    'brand_name' => $this->brand->name,
                    'manufacturer_name' => $this->manufacturer->name,
                ]);
            }

            // Run benchmark searches
            $searchTerms = ['test', 'product', 'tool', 'power', 'electric'];
            $measurements = [];

            foreach ($searchTerms as $term) {
                $iterations = 5;
                $times = [];
                $memorySamples = [];

                for ($i = 0; $i < $iterations; $i++) {
                    $startTime = microtime(true);
                    $startMemory = memory_get_usage();

                    $result = $this->searchService->search([
                        'search' => $term,
                        'per_page' => 20,
                        'page' => 1
                    ]);

                    $endTime = microtime(true);
                    $endMemory = memory_get_usage();

                    $times[] = ($endTime - $startTime) * 1000;
                    $memorySamples[] = ($endMemory - $startMemory) / 1024;

                    expect($result)->toBeInstanceOf(\Illuminate\Pagination\LengthAwarePaginator::class);
                }

                $measurements[$term] = [
                    'avg_time_ms' => array_sum($times) / count($times),
                    'min_time_ms' => min($times),
                    'max_time_ms' => max($times),
                    'median_time_ms' => $this->median($times),
                    'std_dev_ms' => $this->standardDeviation($times),
                    'avg_memory_kb' => array_sum($memorySamples) / count($memorySamples),
                    'iterations' => $iterations
                ];
            }

            $overallAvgTime = collect($measurements)->avg('avg_time_ms');
            $overallMaxTime = collect($measurements)->max('max_time_ms');
            $overallAvgMemory = collect($measurements)->avg('avg_memory_kb');

            $benchmarkResults[$dataset['name']] = [
                'dataset_size' => $dataset['size'],
                'overall_avg_time_ms' => round($overallAvgTime, 2),
                'overall_max_time_ms' => round($overallMaxTime, 2),
                'overall_avg_memory_kb' => round($overallAvgMemory, 2),
                'search_measurements' => $measurements
            ];

            // Performance expectations based on dataset size
            $expectedTime = match($dataset['name']) {
                'small' => 300,
                'medium' => 600,
                'large' => 1000,
                'very_large' => 1500
            };

            expect($overallAvgTime)->toBeLessThan($expectedTime, "Average search time for {$dataset['name']} dataset should be under {$expectedTime}ms");

            // Clear for next dataset
            if ($dataset !== end($datasets)) {
                $this->refreshApplication();
                $this->setUp();
            }
        }

        dump("Search Performance Baseline Benchmark:", $benchmarkResults);

        // Generate performance report
        $this->generatePerformanceReport($benchmarkResults);
    });

    test('pagination performance benchmark across pages', function () {
        // Create substantial dataset
        Product::factory()->count(1000)->create([
            'category_id' => $this->category->id,
            'brand_id' => $this->brand->id,
            'manufacturer_id' => $this->manufacturer->id,
            'category_name' => $this->category->name,
            'brand_name' => $this->brand->name,
            'manufacturer_name' => $this->manufacturer->name,
        ]);

        $pagePerformance = [];
        $pages = [1, 2, 5, 10, 20, 50];
        $perPageSizes = [10, 20, 50];

        foreach ($perPageSizes as $perPage) {
            $pagePerformance[$perPage] = [];

            foreach ($pages as $page) {
                $iterations = 3;
                $times = [];

                for ($i = 0; $i < $iterations; $i++) {
                    $startTime = microtime(true);

                    $result = $this->searchService->search([
                        'search' => 'test',
                        'per_page' => $perPage,
                        'page' => $page
                    ]);

                    $endTime = microtime(true);
                    $times[] = ($endTime - $startTime) * 1000;

                    expect($result)->toBeInstanceOf(\Illuminate\Pagination\LengthAwarePaginator::class);
                }

                $avgTime = array_sum($times) / count($times);
                $pagePerformance[$perPage][$page] = round($avgTime, 2);

                // Pagination performance should not degrade significantly
                expect($avgTime)->toBeLessThan(1000, "Page {$page} with {$perPage} items should load under 1s");
            }
        }

        dump("Pagination Performance Benchmark:", $pagePerformance);

        // Analyze pagination performance trends
        foreach ($perPageSizes as $perPage) {
            $firstPageTime = $pagePerformance[$perPage][1];
            $lastPageTime = $pagePerformance[$perPage][50];
            $degradation = (($lastPageTime - $firstPageTime) / $firstPageTime) * 100;

            expect($degradation)->toBeLessThan(200, "Performance degradation should be less than 200% from first to last page");

            dump("Pagination degradation analysis for {$perPage} items per page:", [
                'first_page_time_ms' => $firstPageTime,
                'last_page_time_ms' => $lastPageTime,
                'degradation_percent' => round($degradation, 2)
            ]);
        }
    });

    test('complex filter combination benchmark', function () {
        // Create diverse dataset
        $categories = Category::factory()->count(5)->create();
        $brands = Brand::factory()->count(5)->create();
        $manufacturers = Manufacturer::factory()->count(3)->create();

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
            ]);
        }

        $filterCombinations = [
            'simple' => [
                'search' => 'test'
            ],
            'search_price' => [
                'search' => 'test',
                'min_price' => 50,
                'max_price' => 500
            ],
            'search_category_brand' => [
                'search' => 'test',
                'categories' => $categories->take(2)->pluck('id')->toArray(),
                'brands' => $brands->take(2)->pluck('id')->toArray()
            ],
            'complex_all_filters' => [
                'search' => 'test',
                'categories' => $categories->take(3)->pluck('id')->toArray(),
                'brands' => $brands->take(3)->pluck('id')->toArray(),
                'manufacturers' => $manufacturers->take(2)->pluck('id')->toArray(),
                'min_price' => 100,
                'max_price' => 800,
                'in_stock' => true,
                'sort_by' => 'price',
                'sort_order' => 'asc'
            ]
        ];

        $filterBenchmark = [];

        foreach ($filterCombinations as $name => $filters) {
            $iterations = 5;
            $times = [];
            $resultCounts = [];

            for ($i = 0; $i < $iterations; $i++) {
                $startTime = microtime(true);

                $result = $this->searchService->search(array_merge($filters, [
                    'per_page' => 20,
                    'page' => 1
                ]));

                $endTime = microtime(true);
                $times[] = ($endTime - $startTime) * 1000;
                $resultCounts[] = $result->count();

                expect($result)->toBeInstanceOf(\Illuminate\Pagination\LengthAwarePaginator::class);
            }

            $avgTime = array_sum($times) / count($times);
            $avgResultCount = array_sum($resultCounts) / count($resultCounts);

            $filterBenchmark[$name] = [
                'avg_time_ms' => round($avgTime, 2),
                'min_time_ms' => round(min($times), 2),
                'max_time_ms' => round(max($times), 2),
                'avg_result_count' => round($avgResultCount, 2),
                'filter_complexity' => count($filters),
                'iterations' => $iterations
            ];

            // Complex filters should still be reasonably fast
            $maxExpectedTime = match($name) {
                'simple' => 500,
                'search_price' => 700,
                'search_category_brand' => 800,
                'complex_all_filters' => 1200
            };

            expect($avgTime)->toBeLessThan($maxExpectedTime, "Filter combination '{$name}' should complete under {$maxExpectedTime}ms");
        }

        dump("Complex Filter Combination Benchmark:", $filterBenchmark);
    });

    test('cache efficiency benchmark', function () {
        // Create test data
        Product::factory()->count(500)->create([
            'category_id' => $this->category->id,
            'brand_id' => $this->brand->id,
            'manufacturer_id' => $this->manufacturer->id,
            'category_name' => $this->category->name,
            'brand_name' => $this->brand->name,
            'manufacturer_name' => $this->manufacturer->name,
        ]);

        $operations = [
            'getFilterOptions' => [],
            'getSearchSuggestions' => ['test', 5]
        ];

        $cacheBenchmark = [];

        foreach ($operations as $method => $args) {
            // Measure cache miss
            Cache::flush();
            $startTime = microtime(true);

            if (empty($args)) {
                $result1 = $this->searchService->{$method}();
            } else {
                $result1 = $this->searchService->{$method}(...$args);
            }

            $cacheMissTime = (microtime(true) - $startTime) * 1000;

            // Measure cache hit
            $startTime = microtime(true);

            if (empty($args)) {
                $result2 = $this->searchService->{$method}();
            } else {
                $result2 = $this->searchService->{$method}(...$args);
            }

            $cacheHitTime = (microtime(true) - $startTime) * 1000;

            // Ensure results are identical
            expect($result1)->toEqual($result2, "Cached and non-cached results should be identical");

            $improvement = (($cacheMissTime - $cacheHitTime) / $cacheMissTime) * 100;

            $cacheBenchmark[$method] = [
                'cache_miss_time_ms' => round($cacheMissTime, 2),
                'cache_hit_time_ms' => round($cacheHitTime, 2),
                'improvement_percent' => round($improvement, 2),
                'speedup_factor' => round($cacheMissTime / $cacheHitTime, 2)
            ];

            // Cache should provide significant improvement
            expect($improvement)->toBeGreaterThan(50, "Cache should provide at least 50% improvement for {$method}");
            expect($cacheHitTime)->toBeLessThan(100, "Cached operation should be very fast");
        }

        dump("Cache Efficiency Benchmark:", $cacheBenchmark);
    });

    test('search ranking quality vs performance benchmark', function () {
        // Create products with varying relevance
        $productData = [
            // High relevance
            ['name' => 'Professional Power Drill DeWalt', 'description' => 'High-quality professional power drill for construction'],
            ['name' => 'DeWalt Power Drill 20V', 'description' => 'Cordless power drill with excellent battery life'],
            ['name' => 'Power Drill Heavy Duty', 'description' => 'Industrial grade power drill for professional use'],

            // Medium relevance
            ['name' => 'Electric Screwdriver Power Tool', 'description' => 'Versatile electric tool for drilling and screwing'],
            ['name' => 'Cordless Tool Kit with Drill', 'description' => 'Complete tool kit including various power tools'],

            // Low relevance
            ['name' => 'Workshop Safety Equipment', 'description' => 'Safety gear for power tool workshop'],
            ['name' => 'Tool Storage Cabinet', 'description' => 'Storage solution for workshop tools and equipment'],
        ];

        foreach ($productData as $data) {
            for ($i = 1; $i <= 10; $i++) {
                Product::factory()->create([
                    'name' => $data['name'] . " Model {$i}",
                    'description' => $data['description'] . " Version {$i}",
                    'category_id' => $this->category->id,
                    'brand_id' => $this->brand->id,
                    'manufacturer_id' => $this->manufacturer->id,
                    'category_name' => $this->category->name,
                    'brand_name' => $this->brand->name,
                    'manufacturer_name' => $this->manufacturer->name,
                ]);
            }
        }

        $searchTerm = 'power drill';
        $iterations = 10;
        $performanceData = [];
        $relevanceData = [];

        for ($i = 0; $i < $iterations; $i++) {
            $startTime = microtime(true);

            $results = $this->searchService->search([
                'search' => $searchTerm,
                'per_page' => 20,
                'page' => 1
            ]);

            $endTime = microtime(true);
            $executionTime = ($endTime - $startTime) * 1000;
            $performanceData[] = $executionTime;

            // Analyze ranking quality (first 5 results should be most relevant)
            $topResults = $results->take(5);
            $relevanceScore = 0;

            foreach ($topResults as $index => $result) {
                $name = strtolower($result->name);
                $description = strtolower($result->description ?? '');

                // Score based on relevance (higher is better)
                if (str_contains($name, 'dewalt') && str_contains($name, 'power drill')) {
                    $relevanceScore += 10;
                } elseif (str_contains($name, 'power drill')) {
                    $relevanceScore += 8;
                } elseif (str_contains($name, 'drill')) {
                    $relevanceScore += 6;
                } elseif (str_contains($description, 'power drill')) {
                    $relevanceScore += 4;
                } elseif (str_contains($name, 'power') || str_contains($name, 'electric')) {
                    $relevanceScore += 2;
                }

                // Penalty for position (later results should be less relevant)
                $relevanceScore -= $index * 0.5;
            }

            $relevanceData[] = $relevanceScore;
        }

        $avgPerformance = array_sum($performanceData) / count($performanceData);
        $avgRelevance = array_sum($relevanceData) / count($relevanceData);

        $benchmark = [
            'search_term' => $searchTerm,
            'iterations' => $iterations,
            'avg_performance_ms' => round($avgPerformance, 2),
            'min_performance_ms' => round(min($performanceData), 2),
            'max_performance_ms' => round(max($performanceData), 2),
            'avg_relevance_score' => round($avgRelevance, 2),
            'min_relevance_score' => round(min($relevanceData), 2),
            'max_relevance_score' => round(max($relevanceData), 2),
            'performance_consistency' => round($this->standardDeviation($performanceData), 2),
            'relevance_consistency' => round($this->standardDeviation($relevanceData), 2)
        ];

        // Ranking should be both fast and relevant
        expect($avgPerformance)->toBeLessThan(800, "Search with ranking should be under 800ms");
        expect($avgRelevance)->toBeGreaterThan(30, "Search relevance should be above 30 points");

        dump("Search Ranking Quality vs Performance Benchmark:", $benchmark);
    });

    test('postgresql specific features benchmark', function () {
        // Create products with varied content for PostgreSQL full-text search
        $products = [];
        $searchableContent = [
            'DeWalt 20V MAX Cordless Drill with Lithium Ion Battery',
            'Milwaukee M18 FUEL Brushless Impact Driver Heavy Duty',
            'Makita XPH12Z Hammer Drill Variable Speed Professional',
            'Bosch Professional Angle Grinder Cutting Grinding Tool',
            'RIDGID Circular Saw Precision Cutting Construction Grade'
        ];

        foreach ($searchableContent as $content) {
            for ($i = 1; $i <= 20; $i++) {
                Product::factory()->create([
                    'name' => $content,
                    'description' => "Professional grade tool. {$content}. Model {$i} with advanced features.",
                    'category_id' => $this->category->id,
                    'brand_id' => $this->brand->id,
                    'manufacturer_id' => $this->manufacturer->id,
                    'category_name' => $this->category->name,
                    'brand_name' => $this->brand->name,
                    'manufacturer_name' => $this->manufacturer->name,
                ]);
            }
        }

        $pgBenchmarks = [];

        // Test different PostgreSQL search features
        $searchMethods = [
            'basic_search' => fn($term) => $this->searchService->search(['search' => $term, 'per_page' => 20]),
            'fuzzy_search' => function($term) {
                return Product::query()
                    ->where('status', 'active')
                    ->fuzzySearch($term)
                    ->limit(20)
                    ->get();
            },
            'fulltext_search' => function($term) {
                return Product::query()
                    ->where('status', 'active')
                    ->fullTextSearch($term)
                    ->limit(20)
                    ->get();
            }
        ];

        $testTerms = ['cordless drill', 'impact driver', 'angle grinder', 'milwaukee', 'professional'];

        foreach ($searchMethods as $methodName => $method) {
            $methodResults = [];

            foreach ($testTerms as $term) {
                $iterations = 5;
                $times = [];
                $resultCounts = [];

                for ($i = 0; $i < $iterations; $i++) {
                    try {
                        $startTime = microtime(true);
                        $result = $method($term);
                        $endTime = microtime(true);

                        $executionTime = ($endTime - $startTime) * 1000;
                        $times[] = $executionTime;

                        if (method_exists($result, 'count')) {
                            $resultCounts[] = $result->count();
                        } else {
                            $resultCounts[] = count($result);
                        }

                    } catch (\Exception $e) {
                        dump("Error in {$methodName} with term '{$term}': " . $e->getMessage());
                        continue;
                    }
                }

                if (!empty($times)) {
                    $methodResults[$term] = [
                        'avg_time_ms' => round(array_sum($times) / count($times), 2),
                        'min_time_ms' => round(min($times), 2),
                        'max_time_ms' => round(max($times), 2),
                        'avg_result_count' => round(array_sum($resultCounts) / count($resultCounts), 2)
                    ];
                }
            }

            $pgBenchmarks[$methodName] = $methodResults;
        }

        dump("PostgreSQL Search Features Benchmark:", $pgBenchmarks);

        // Verify that search methods work and perform reasonably
        foreach ($pgBenchmarks as $method => $results) {
            $avgTime = collect($results)->avg('avg_time_ms');
            expect($avgTime)->toBeLessThan(1000, "PostgreSQL {$method} should average under 1000ms");
        }
    });

    // Helper methods for statistical calculations
    function median(array $values): float
    {
        sort($values);
        $count = count($values);
        $middle = floor($count / 2);

        if ($count % 2 === 0) {
            return ($values[$middle - 1] + $values[$middle]) / 2;
        } else {
            return $values[$middle];
        }
    }

    function standardDeviation(array $values): float
    {
        $mean = array_sum($values) / count($values);
        $squaredDifferences = array_map(fn($value) => pow($value - $mean, 2), $values);
        $variance = array_sum($squaredDifferences) / count($values);

        return sqrt($variance);
    }

    function generatePerformanceReport(array $benchmarkResults): void
    {
        $report = "=== SEARCH PERFORMANCE BENCHMARK REPORT ===\n\n";

        foreach ($benchmarkResults as $datasetName => $data) {
            $report .= strtoupper($datasetName) . " DATASET ({$data['dataset_size']} products):\n";
            $report .= "  - Average Response Time: {$data['overall_avg_time_ms']}ms\n";
            $report .= "  - Maximum Response Time: {$data['overall_max_time_ms']}ms\n";
            $report .= "  - Average Memory Usage: {$data['overall_avg_memory_kb']}KB\n\n";
        }

        // Performance scaling analysis
        $sizes = collect($benchmarkResults)->pluck('dataset_size')->toArray();
        $times = collect($benchmarkResults)->pluck('overall_avg_time_ms')->toArray();

        $report .= "SCALING ANALYSIS:\n";
        for ($i = 1; $i < count($sizes); $i++) {
            $sizeRatio = $sizes[$i] / $sizes[$i-1];
            $timeRatio = $times[$i] / $times[$i-1];
            $efficiency = $sizeRatio / $timeRatio;

            $report .= "  - {$sizes[$i-1]} to {$sizes[$i]} products: {$sizeRatio}x size, {$timeRatio}x time (efficiency: {$efficiency})\n";
        }

        dump($report);
    }
});
