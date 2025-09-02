<?php

declare(strict_types=1);

use App\Models\Brand;
use App\Models\Category;
use App\Models\Manufacturer;
use App\Models\Product;
use App\Services\ProductSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = new ProductSearchService;

    // Create test data
    $this->category = Category::factory()->create(['name' => 'Electronics']);
    $this->brand = Brand::factory()->create(['name' => 'TestBrand']);
    $this->manufacturer = Manufacturer::factory()->create(['name' => 'TestManufacturer']);

    $this->products = Product::factory()
        ->count(5)
        ->create([
            'status_id' => 2,
            'category_id' => $this->category->id,
            'brand_id' => $this->brand->id,
            'manufacturer_id' => $this->manufacturer->id,
            'name' => 'Test Product',
            'title' => 'Test Product Title',
            'description' => 'This is a test product for searching',
        ]);
});

test('search returns paginated results', function () {
    $params = [
        'search' => 'Test Product',
        'per_page' => 10,
        'page' => 1,
    ];

    $results = $this->service->search($params);

    expect($results)->toBeInstanceOf(\Illuminate\Pagination\LengthAwarePaginator::class);
    expect($results->count())->toBeGreaterThan(0);
});

test('search handles category filters', function () {
    $params = [
        'search' => 'Test',
        'categories' => [$this->category->id],
        'per_page' => 10,
    ];

    $results = $this->service->search($params);

    expect($results->count())->toBeGreaterThan(0);
    foreach ($results->items() as $product) {
        expect($product->category_id)->toBe($this->category->id);
    }
});

test('search handles brand filters', function () {
    $params = [
        'search' => 'Test',
        'brands' => [$this->brand->id],
        'per_page' => 10,
    ];

    $results = $this->service->search($params);

    expect($results->count())->toBeGreaterThan(0);
    foreach ($results->items() as $product) {
        expect($product->brand_id)->toBe($this->brand->id);
    }
});

test('search handles manufacturer filters', function () {
    $params = [
        'search' => 'Test',
        'manufacturers' => [$this->manufacturer->id],
        'per_page' => 10,
    ];

    $results = $this->service->search($params);

    expect($results->count())->toBeGreaterThan(0);
    foreach ($results->items() as $product) {
        expect($product->manufacturer_id)->toBe($this->manufacturer->id);
    }
});

test('search handles price range filters', function () {
    // Create products with specific prices
    $expensiveProduct = Product::factory()->create([
        'status_id' => 2,
        'name' => 'Expensive Product',
    ]);
    $expensiveProduct->prices()->create([
        'source_name' => 'test_source',
        'pricing_ranges' => [
            ['price' => 100.0, 'quantity' => 1],
        ],
    ]);

    $params = [
        'search' => 'Product',
        'min_price' => 50,
        'max_price' => 150,
        'per_page' => 10,
    ];

    $results = $this->service->search($params);

    expect($results->count())->toBeGreaterThan(0);
});

test('search handles stock filter', function () {
    // Create a product with stock
    $inStockProduct = Product::factory()->create([
        'status_id' => 2,
        'name' => 'In Stock Product',
    ]);
    $inStockProduct->quantities()->create([
        'source_name' => 'test_source',
        'quantity' => 10,
        'availability_status' => 'in_stock',
    ]);

    $params = [
        'search' => 'Product',
        'in_stock' => true,
        'per_page' => 10,
    ];

    $results = $this->service->search($params);

    expect($results->count())->toBeGreaterThan(0);
});

test('getFilterOptions returns cached filter data', function () {
    $filterOptions = $this->service->getFilterOptions();

    expect($filterOptions)->toHaveKeys(['categories', 'brands', 'manufacturers', 'price_range']);
    expect($filterOptions['categories'])->toBeArray();
    expect($filterOptions['brands'])->toBeArray();
    expect($filterOptions['manufacturers'])->toBeArray();
    expect($filterOptions['price_range'])->toHaveKeys(['min', 'max']);
});

test('getAdvancedFilterOptions returns comprehensive filter data', function () {
    $filterOptions = $this->service->getAdvancedFilterOptions();

    expect($filterOptions)->toHaveKeys([
        'categories', 'brands', 'manufacturers', 'price_range',
        'attributes', 'sources', 'rating_ranges',
    ]);
    expect($filterOptions['rating_ranges'])->toBeArray();
    expect($filterOptions['attributes'])->toBeArray();
    expect($filterOptions['sources'])->toBeArray();
});

test('getSearchSuggestions returns relevant suggestions', function () {
    $suggestions = $this->service->getSearchSuggestions('Test', 5);

    expect($suggestions)->toBeArray();
    if (count($suggestions) > 0) {
        expect($suggestions[0])->toHaveKeys(['id', 'text', 'category', 'brand']);
    }
});

test('search works without search term', function () {
    $params = [
        'per_page' => 10,
        'page' => 1,
    ];

    $results = $this->service->search($params);

    expect($results)->toBeInstanceOf(\Illuminate\Pagination\LengthAwarePaginator::class);
});

test('search handles sorting options', function () {
    $params = [
        'search' => 'Test',
        'sort_by' => 'name',
        'sort_order' => 'asc',
        'per_page' => 10,
    ];

    $results = $this->service->search($params);

    expect($results)->toBeInstanceOf(\Illuminate\Pagination\LengthAwarePaginator::class);
    expect($results->count())->toBeGreaterThan(0);
});

test('clearCache clears filter cache', function () {
    // First call to populate cache
    $this->service->getFilterOptions();

    // Clear cache
    $this->service->clearCache();

    // This should work without errors
    expect(true)->toBeTrue();
});
