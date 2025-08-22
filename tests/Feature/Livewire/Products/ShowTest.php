<?php

namespace Tests\Feature\Livewire\Products;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Manufacturer;
use App\Models\Product;
use Livewire\Volt\Volt;
use Tests\TestCase;

class ShowTest extends TestCase
{
    public function test_it_can_render_product_show_page(): void
    {
        $category = Category::factory()->create(['name' => 'Test Category']);
        $brand = Brand::factory()->create(['name' => 'Test Brand']);
        $manufacturer = Manufacturer::factory()->create(['name' => 'Test Manufacturer']);

        $product = Product::factory()->create([
            'name' => 'Test Product',
            'description' => 'Test product description',
            'price' => 99.99,
            'sku' => 'TEST-SKU-123',
            'stock_quantity' => 10,
            'status' => 'active',
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'manufacturer_id' => $manufacturer->id,
            'category_name' => $category->name,
            'brand_name' => $brand->name,
            'manufacturer_name' => $manufacturer->name,
        ]);

        $component = Volt::test('products.show', ['product' => $product]);

        $component->assertSee('Test Product')
            ->assertSee('Test product description')
            ->assertSee('$99.99')
            ->assertSee('TEST-SKU-123')
            ->assertSee('Test Category')
            ->assertSee('Test Brand')
            ->assertSee('Test Manufacturer')
            ->assertSee('10 available')
            ->assertSee('Add to Cart');
    }

    public function test_it_shows_out_of_stock_for_products_with_zero_stock(): void
    {
        $category = Category::factory()->create();
        $brand = Brand::factory()->create();
        $manufacturer = Manufacturer::factory()->create();

        $product = Product::factory()->create([
            'name' => 'Out of Stock Product',
            'stock_quantity' => 0,
            'status' => 'active',
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'manufacturer_id' => $manufacturer->id,
            'category_name' => $category->name,
            'brand_name' => $brand->name,
            'manufacturer_name' => $manufacturer->name,
        ]);

        $component = Volt::test('products.show', ['product' => $product]);

        $component->assertSee('Out of stock')
            ->assertSee('Out of Stock')
            ->assertDontSee('Add to Cart');
    }

    public function test_it_shows_404_for_inactive_products(): void
    {
        $category = Category::factory()->create();
        $brand = Brand::factory()->create();
        $manufacturer = Manufacturer::factory()->create();

        $product = Product::factory()->create([
            'status' => 'inactive',
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'manufacturer_id' => $manufacturer->id,
        ]);

        $response = $this->get(route('products.show', $product));

        $response->assertNotFound();
    }

    public function test_it_can_be_accessed_via_route(): void
    {
        $category = Category::factory()->create();
        $brand = Brand::factory()->create();
        $manufacturer = Manufacturer::factory()->create();

        $product = Product::factory()->create([
            'name' => 'Routable Product',
            'status' => 'active',
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'manufacturer_id' => $manufacturer->id,
            'category_name' => $category->name,
            'brand_name' => $brand->name,
            'manufacturer_name' => $manufacturer->name,
        ]);

        $response = $this->get(route('products.show', $product));

        $response->assertOk()
            ->assertSee('Routable Product');
    }
}
