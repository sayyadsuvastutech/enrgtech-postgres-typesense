<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Manufacturer;
use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ProductDocument;
use App\Models\ProductImage;
use App\Models\ProductPrice;
use App\Models\ProductQuantity;
use App\Models\ProductSource;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🚀 Starting comprehensive tool and electrical product seeding...');

        // Create test user
        User::firstOrCreate([
            'email' => 'test@example.com',
        ], [
            'name' => 'Test User',
            'password' => bcrypt('password'),
        ]);

        // Create categories (flat structure now)
        $this->command->info('📂 Creating categories...');
        $categories = $this->createCategories();
        
        // Create manufacturers
        $this->command->info('🏭 Creating manufacturers...');
        $manufacturers = $this->createManufacturers();
        
        // Create brands (now tied to manufacturers)
        $this->command->info('🏷️ Creating brands...');
        $brands = $this->createBrands($manufacturers);
        
        // Create products with new structure
        $this->command->info('🔧 Creating products...');
        $products = $this->createProducts($categories, $manufacturers);
        
        // Create product-related data
        $this->command->info('📊 Creating product sources, attributes, pricing, and media...');
        $this->createProductData($products);

        $this->command->info('✅ Database seeding completed successfully!');
        $this->printSeedingSummary();
    }

    private function createCategories(): \Illuminate\Database\Eloquent\Collection
    {
        // Create main categories first
        $this->command->info('Creating main categories...');
        $mainCategories = collect();
        
        $mainCategoryData = [
            'Electronics' => 'Electronic components and semiconductors',
            'Power Management' => 'Power supplies, regulators, and management ICs',
            'Sensors' => 'Measurement and detection sensors',
            'Connectivity' => 'Connectors, cables, and interface components',
            'Test Equipment' => 'Measurement and testing instruments',
            'Industrial Controls' => 'Automation and control systems'
        ];

        foreach ($mainCategoryData as $name => $description) {
            $category = Category::factory()->mainCategory()->create([
                'name' => $name,
                'description' => $description,
            ]);
            $mainCategories->push($category);
        }

        // Create subcategories for each main category
        $this->command->info('Creating subcategories...');
        $allCategories = collect($mainCategories);

        $subcategoryMap = [
            'Electronics' => ['Resistors', 'Capacitors', 'Inductors', 'Diodes', 'Transistors'],
            'Power Management' => ['Voltage Regulators', 'Power Modules', 'Battery Management', 'DC-DC Converters'],
            'Sensors' => ['Temperature Sensors', 'Pressure Sensors', 'Motion Sensors', 'Light Sensors'],
            'Connectivity' => ['USB Connectors', 'Audio Connectors', 'RF Connectors', 'Board-to-Board'],
            'Test Equipment' => ['Multimeters', 'Oscilloscopes', 'Power Supplies', 'Signal Generators'],
            'Industrial Controls' => ['PLCs', 'Motor Controllers', 'Relays', 'Switches']
        ];

        foreach ($mainCategories as $mainCategory) {
            if (isset($subcategoryMap[$mainCategory->name])) {
                foreach ($subcategoryMap[$mainCategory->name] as $subName) {
                    $subCategory = Category::factory()->subCategory()->create([
                        'name' => $subName,
                        'parent_category' => $mainCategory->id,
                    ]);
                    $allCategories->push($subCategory);
                }
            }
        }

        // Create some third-level categories
        $this->command->info('Creating third-level categories...');
        $thirdLevelData = [
            'Resistors' => ['Fixed Resistors', 'Variable Resistors', 'Precision Resistors'],
            'Capacitors' => ['Ceramic Capacitors', 'Electrolytic Capacitors', 'Film Capacitors'],
            'Temperature Sensors' => ['Thermocouples', 'RTDs', 'Digital Temperature ICs']
        ];

        foreach ($thirdLevelData as $parentName => $children) {
            $parentCategory = $allCategories->firstWhere('name', $parentName);
            if ($parentCategory) {
                foreach ($children as $childName) {
                    $childCategory = Category::factory()->subCategory()->create([
                        'name' => $childName,
                        'parent_category' => $parentCategory->id,
                        'products_count' => fake()->numberBetween(5, 50),
                    ]);
                    $allCategories->push($childCategory);
                }
            }
        }

        return $allCategories;
    }

    private function createManufacturers(): \Illuminate\Database\Eloquent\Collection
    {
        return Manufacturer::factory()->count(50)->create();
    }

    private function createBrands(\Illuminate\Database\Eloquent\Collection $manufacturers): \Illuminate\Database\Eloquent\Collection
    {
        return Brand::factory()->count(100)->create()->each(function ($brand) use ($manufacturers) {
            $brand->update(['manufacturer_id' => $manufacturers->random()->id]);
        });
    }

    private function createProducts(\Illuminate\Database\Eloquent\Collection $categories, \Illuminate\Database\Eloquent\Collection $manufacturers): \Illuminate\Database\Eloquent\Collection
    {
        return Product::factory()->count(1000)->create()->each(function ($product) use ($categories, $manufacturers) {
            $product->update([
                'category_id' => $categories->random()->id,
                'manufacturer_id' => $manufacturers->random()->id,
            ]);
        });
    }

    private function createProductData(\Illuminate\Database\Eloquent\Collection $products): void
    {
        // Create sources for products
        $this->command->info('Creating product sources...');
        $products->each(function ($product) {
            ProductSource::factory()
                ->count(fake()->numberBetween(1, 3))
                ->create(['product_id' => $product->id]);
        });

        // Create attributes for products
        $this->command->info('Creating product attributes...');
        $products->each(function ($product) {
            ProductAttribute::factory()
                ->count(fake()->numberBetween(1, 2))
                ->create(['product_id' => $product->id]);
        });

        // Create prices for products
        $this->command->info('Creating product prices...');
        $products->each(function ($product) {
            ProductPrice::factory()
                ->count(fake()->numberBetween(1, 2))
                ->create(['product_id' => $product->id]);
        });

        // Create images for products
        $this->command->info('Creating product images...');
        $products->each(function ($product) {
            ProductImage::factory()
                ->count(1)
                ->create(['product_id' => $product->id]);
        });

        // Create quantities for products
        $this->command->info('Creating product quantities...');
        $products->each(function ($product) {
            ProductQuantity::factory()
                ->count(fake()->numberBetween(1, 2))
                ->create(['product_id' => $product->id]);
        });

        // Create documents for products
        $this->command->info('Creating product documents...');
        $products->each(function ($product) {
            ProductDocument::factory()
                ->count(fake()->numberBetween(0, 1))
                ->create(['product_id' => $product->id]);
        });
    }

    private function printSeedingSummary(): void
    {
        $this->command->table(['Model', 'Count'], [
            ['Categories', Category::count()],
            ['Brands', Brand::count()],
            ['Manufacturers', Manufacturer::count()],
            ['Products', Product::count()],
            ['Product Sources', ProductSource::count()],
            ['Product Attributes', ProductAttribute::count()],
            ['Product Prices', ProductPrice::count()],
            ['Product Images', ProductImage::count()],
            ['Product Quantities', ProductQuantity::count()],
            ['Product Documents', ProductDocument::count()],
            ['Users', User::count()],
        ]);
    }
}
