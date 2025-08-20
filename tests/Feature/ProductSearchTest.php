<?php

use App\Models\Product;
use App\Models\Category;
use App\Models\Brand;
use App\Models\Manufacturer;
use Livewire\Volt\Volt;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create test data
    $category = Category::factory()->create(['name' => 'Tools']);
    $brand = Brand::factory()->create(['name' => 'DeWalt']);
    $manufacturer = Manufacturer::factory()->create(['name' => 'Stanley Black & Decker']);
    
    Product::factory()->create([
        'name' => 'DeWalt 20V MAX Cordless Drill',
        'sku' => 'DCD771C2',
        'price' => 129.99,
        'stock_quantity' => 15,
        'category_id' => $category->id,
        'brand_id' => $brand->id,
        'manufacturer_id' => $manufacturer->id,
        'category_name' => $category->name,
        'brand_name' => $brand->name,
        'manufacturer_name' => $manufacturer->name,
        'attributes' => [
            'voltage' => '20V',
            'battery' => 'Lithium Ion',
            'chuck_size' => '1/2 inch'
        ]
    ]);
    
    Product::factory()->create([
        'name' => 'Milwaukee M18 Impact Driver',
        'sku' => 'M18BID-0',
        'price' => 159.99,
        'stock_quantity' => 8,
        'category_id' => $category->id,
        'brand_id' => Brand::factory()->create(['name' => 'Milwaukee'])->id,
        'manufacturer_id' => $manufacturer->id,
        'category_name' => $category->name,
        'brand_name' => 'Milwaukee',
        'manufacturer_name' => $manufacturer->name,
        'attributes' => [
            'voltage' => '18V',
            'battery' => 'Lithium Ion',
            'torque' => '180 Nm'
        ]
    ]);
});

test('search page loads', function () {
    $response = $this->get('/search');
    $response->assertSuccessful();
});

test('can search by product name', function () {
    Volt::test('products.search')
        ->set('search', 'DeWalt')
        ->assertSee('DeWalt 20V MAX Cordless Drill')
        ->assertDontSee('Milwaukee M18 Impact Driver');
});

test('can search by sku', function () {
    Volt::test('products.search')
        ->set('search', 'DCD771C2')
        ->assertSee('DeWalt 20V MAX Cordless Drill')
        ->assertDontSee('Milwaukee M18 Impact Driver');
});

test('can filter by price range', function () {
    Volt::test('products.search')
        ->set('minPrice', 100)
        ->set('maxPrice', 150)
        ->assertSee('DeWalt 20V MAX Cordless Drill')
        ->assertDontSee('Milwaukee M18 Impact Driver');
});

test('can filter by brand', function () {
    $brand = Brand::where('name', 'DeWalt')->first();
    
    Volt::test('products.search')
        ->set('selectedBrands', [$brand->id])
        ->assertSee('DeWalt 20V MAX Cordless Drill')
        ->assertDontSee('Milwaukee M18 Impact Driver');
});

test('can filter by stock availability', function () {
    // Create an out of stock product
    $category = Category::first();
    $brand = Brand::first();
    $manufacturer = Manufacturer::first();
    
    Product::factory()->create([
        'name' => 'Out of Stock Tool',
        'sku' => 'OOS001',
        'price' => 99.99,
        'stock_quantity' => 0,
        'category_id' => $category->id,
        'brand_id' => $brand->id,
        'manufacturer_id' => $manufacturer->id,
        'category_name' => $category->name,
        'brand_name' => $brand->name,
        'manufacturer_name' => $manufacturer->name,
    ]);

    Volt::test('products.search')
        ->set('inStock', true)
        ->assertSee('DeWalt 20V MAX Cordless Drill')
        ->assertSee('Milwaukee M18 Impact Driver')
        ->assertDontSee('Out of Stock Tool');
});

test('can filter by technical specifications', function () {
    Volt::test('products.search')
        ->set('technicalFilters.voltage', '20V')
        ->assertSee('DeWalt 20V MAX Cordless Drill')
        ->assertDontSee('Milwaukee M18 Impact Driver');
});

test('search suggestions work', function () {
    $component = Volt::test('products.search')
        ->set('search', 'De')
        ->call('generateSearchSuggestions');
        
    expect($component->get('searchSuggestions'))
        ->toContain(['type' => 'product', 'text' => 'DeWalt 20V MAX Cordless Drill']);
});

test('can clear filters', function () {
    Volt::test('products.search')
        ->set('minPrice', 100)
        ->set('inStock', true)
        ->call('clearFilters')
        ->assertSet('minPrice', null)
        ->assertSet('inStock', false);
});
