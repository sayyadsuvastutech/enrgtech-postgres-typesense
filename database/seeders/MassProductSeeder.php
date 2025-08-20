<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Manufacturer;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MassProductSeeder extends Seeder
{
    private int $batchSize = 1000;
    private int $totalProducts = 2000000;

    public function run(): void
    {
        $this->command->info("🚀 Starting mass product seeding for {$this->totalProducts} products...");

        // Get all available categories, brands, and manufacturers
        $categories = Category::all();
        $brands = Brand::all();
        $manufacturers = Manufacturer::all();

        if ($categories->isEmpty() || $brands->isEmpty() || $manufacturers->isEmpty()) {
            $this->command->error('❌ Please run the main DatabaseSeeder first to create categories, brands, and manufacturers.');
            return;
        }

        $this->command->info("📊 Using {$categories->count()} categories, {$brands->count()} brands, and {$manufacturers->count()} manufacturers");

        // Calculate batches
        $batches = ceil($this->totalProducts / $this->batchSize);
        $bar = $this->command->getOutput()->createProgressBar($batches);
        $bar->start();

        // Use chunked processing for memory efficiency
        for ($batch = 0; $batch < $batches; $batch++) {
            $remaining = $this->totalProducts - ($batch * $this->batchSize);
            $currentBatchSize = min($this->batchSize, $remaining);

            $this->createProductBatch($currentBatchSize, $categories, $brands, $manufacturers);
            $bar->advance();

            // Memory cleanup every 10 batches
            if ($batch % 10 === 0) {
                $this->command->info("\n🧹 Memory cleanup at batch " . ($batch + 1));
                gc_collect_cycles();
            }
        }

        $bar->finish();
        $this->command->newLine();

        // Update denormalized fields in batches
        $this->command->info('🔄 Updating denormalized relationship fields...');
        $this->updateDenormalizedFields();

        $this->command->info('✅ Mass product seeding completed successfully!');
        $this->printFinalStats();
    }

    private function createProductBatch(int $count, $categories, $brands, $manufacturers): void
    {
        $products = [];
        $timestamp = now();

        for ($i = 0; $i < $count; $i++) {
            $category = $categories->random();
            $brand = $brands->random();
            $manufacturer = $manufacturers->random();

            // Generate realistic product data based on category
            $productData = $this->generateRealisticProduct($category, $brand, $manufacturer);

            $products[] = [
                'name' => $productData['name'],
                'slug' => $productData['slug'],
                'description' => $productData['description'],
                'sku' => $productData['sku'],
                'price' => $productData['price'],
                'stock_quantity' => $productData['stock_quantity'],
                'status' => $productData['status'],
                'category_id' => $category->id,
                'brand_id' => $brand->id,
                'manufacturer_id' => $manufacturer->id,
                'category_name' => $category->name,
                'brand_name' => $brand->name,
                'manufacturer_name' => $manufacturer->name,
                'images' => $productData['images'],
                'thumbnails' => $productData['thumbnails'],
                'attributes' => $productData['attributes'],
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];
        }

        // Bulk insert for performance
        DB::table('products')->insert($products);
    }

    private function generateRealisticProduct($category, $brand, $manufacturer): array
    {
        $categoryName = strtolower($category->name);
        $timestamp = now()->getTimestamp();
        $random = fake()->numberBetween(1000, 9999);

        // Generate product names based on category
        $productName = $this->generateProductName($categoryName, $brand->name);

        // Generate realistic pricing based on category and brand
        $pricing = $this->generatePricing($categoryName, $brand->name);

        // Generate SKU
        $sku = strtoupper(substr($brand->name, 0, 3)) . '-' .
               strtoupper(substr($category->name, 0, 3)) . '-' .
               $timestamp . '-' . $random;

        return [
            'name' => $productName,
            'slug' => \Illuminate\Support\Str::slug($productName) . '-' . $timestamp . '-' . $random,
            'description' => $this->generateProductDescription($categoryName, $brand->name, $productName),
            'price' => $pricing['price'],
            'sku' => $sku,
            'stock_quantity' => fake()->numberBetween(0, 500),
            'status' => fake()->randomElement(['active', 'inactive', 'draft', 'discontinued']),
            'images' => json_encode($this->generateRandomImages()),
            'thumbnails' => json_encode($this->generateRandomThumbnails()),
            'attributes' => json_encode($this->generateRandomAttributes($categoryName)),
        ];
    }

    private function generateProductName(string $categoryName, string $brandName): string
    {
        $productTypes = [
            'screwdrivers' => ['Phillips Head Screwdriver', 'Flathead Screwdriver', 'Torx Screwdriver', 'Robertson Screwdriver', 'Precision Screwdriver Set'],
            'wrenches' => ['Combination Wrench', 'Socket Wrench', 'Adjustable Wrench', 'Pipe Wrench', 'Box End Wrench'],
            'hammers' => ['Claw Hammer', 'Ball Peen Hammer', 'Sledge Hammer', 'Dead Blow Hammer', 'Framing Hammer'],
            'pliers' => ['Needle Nose Pliers', 'Wire Strippers', 'Diagonal Cutters', 'Locking Pliers', 'Crimping Pliers'],
            'drills' => ['Cordless Drill', 'Impact Driver', 'Hammer Drill', 'Right Angle Drill', 'Magnetic Drill'],
            'saws' => ['Circular Saw', 'Jigsaw', 'Reciprocating Saw', 'Miter Saw', 'Band Saw'],
            'sanders' => ['Random Orbit Sander', 'Belt Sander', 'Palm Sander', 'Detail Sander', 'Drum Sander'],
            'grinders' => ['Angle Grinder', 'Die Grinder', 'Bench Grinder', 'Surface Grinder', 'Cut-off Tool'],
            'fuses' => ['Circuit Breaker Fuse', 'Cartridge Fuse', 'Blade Fuse', 'Glass Tube Fuse', 'Ceramic Fuse'],
            'switches' => ['Toggle Switch', 'Rocker Switch', 'Push Button Switch', 'Rotary Switch', 'Slide Switch'],
            'connectors' => ['Wire Connector', 'Terminal Block', 'Junction Box', 'Cable Connector', 'Splice Connector'],
            'outlets' => ['GFCI Outlet', 'USB Outlet', 'Weather Resistant Outlet', 'Smart Outlet', 'Industrial Outlet'],
        ];

        $types = $productTypes[$categoryName] ?? ['Professional Tool', 'Industrial Tool', 'Commercial Tool'];
        $baseProduct = fake()->randomElement($types);

        // Add specifications
        $specs = [
            '12V', '18V', '20V', '24V', 'Heavy Duty', 'Professional', 'Compact', 'Brushless',
            'Cordless', 'Pneumatic', 'Electric', 'Manual', 'Digital', 'Analog', 'Precision'
        ];

        $spec = fake()->randomElement($specs);

        return "{$brandName} {$spec} {$baseProduct}";
    }

    private function generatePricing(string $categoryName, string $brandName): array
    {
        // Premium brands command higher prices
        $premiumBrands = ['milwaukee', 'dewalt', 'makita', 'bosch', 'festool', 'hilti', 'fluke', 'klein'];
        $isPremium = in_array(strtolower($brandName), $premiumBrands);

        // Base price ranges by category
        $priceRanges = [
            'screwdrivers' => ['min' => 8, 'max' => 150],
            'wrenches' => ['min' => 15, 'max' => 300],
            'hammers' => ['min' => 20, 'max' => 200],
            'pliers' => ['min' => 12, 'max' => 180],
            'drills' => ['min' => 50, 'max' => 800],
            'saws' => ['min' => 80, 'max' => 1200],
            'sanders' => ['min' => 60, 'max' => 500],
            'grinders' => ['min' => 45, 'max' => 600],
            'fuses' => ['min' => 2, 'max' => 50],
            'switches' => ['min' => 5, 'max' => 100],
            'connectors' => ['min' => 3, 'max' => 80],
            'outlets' => ['min' => 8, 'max' => 150],
        ];

        $range = $priceRanges[$categoryName] ?? ['min' => 10, 'max' => 200];

        $minPrice = $isPremium ? $range['min'] * 1.5 : $range['min'];
        $maxPrice = $isPremium ? $range['max'] * 1.8 : $range['max'];

        $price = fake()->randomFloat(2, $minPrice, $maxPrice);

        return [
            'price' => round($price, 2),
        ];
    }

    private function generateProductDescription(string $categoryName, string $brandName, string $productName): string
    {
        $templates = [
            "Professional-grade {$productName} designed for heavy-duty industrial applications. Built with precision engineering and durable materials to withstand demanding work environments.",
            "High-performance {$productName} featuring advanced technology and ergonomic design. Perfect for contractors, electricians, and serious DIY enthusiasts.",
            "Premium {$productName} with superior build quality and reliability. Engineered for maximum efficiency and long-lasting performance in professional settings.",
            "Industrial-strength {$productName} built to handle the toughest jobs. Features robust construction and innovative design for exceptional durability.",
            "Commercial-grade {$productName} offering outstanding performance and value. Ideal for professional tradespeople and industrial applications."
        ];

        return fake()->randomElement($templates);
    }

    private function generateRandomImages(): array
    {
        $count = fake()->numberBetween(1, 4);
        $images = [];
        
        for ($i = 0; $i < $count; $i++) {
            $images[] = "https://picsum.photos/800/600?random=" . fake()->numberBetween(1, 50000);
        }
        
        return $images;
    }

    private function generateRandomThumbnails(): array
    {
        return [
            '150x150' => "https://picsum.photos/150/150?random=" . fake()->numberBetween(1, 50000),
            '300x300' => "https://picsum.photos/300/300?random=" . fake()->numberBetween(1, 50000),
        ];
    }

    private function generateRandomAttributes(string $categoryName): array
    {
        $attributesByCategory = [
            'drills' => [
                'voltage' => fake()->randomElement(['12V', '18V', '20V', '24V']),
                'battery_type' => 'Li-ion',
                'torque' => fake()->numberBetween(300, 1500) . ' in-lbs',
                'speed' => fake()->numberBetween(1200, 2000) . ' RPM',
                'weight' => fake()->randomFloat(1, 2.5, 8.0) . ' lbs'
            ],
            'saws' => [
                'blade_size' => fake()->randomElement(['7-1/4"', '10"', '12"']),
                'motor_power' => fake()->randomElement(['15A', '10A', '13A']),
                'cutting_depth' => fake()->randomFloat(1, 2.0, 4.0) . '"',
                'weight' => fake()->randomFloat(1, 8.0, 25.0) . ' lbs'
            ],
            'fuses' => [
                'amperage' => fake()->randomElement(['15A', '20A', '30A', '40A', '50A']),
                'voltage_rating' => fake()->randomElement(['125V', '250V', '600V']),
                'type' => fake()->randomElement(['Fast-Acting', 'Time-Delay', 'Current-Limiting']),
                'material' => 'Ceramic/Glass'
            ],
            'switches' => [
                'amperage' => fake()->randomElement(['15A', '20A', '30A']),
                'voltage_rating' => '120-277V',
                'poles' => fake()->randomElement(['Single', 'Double', 'Triple']),
                'color' => fake()->randomElement(['White', 'Ivory', 'Light Almond'])
            ]
        ];

        // Default attributes for categories not specifically defined
        $defaultAttributes = [
            'material' => fake()->randomElement(['Steel', 'Aluminum', 'Plastic', 'Composite']),
            'weight' => fake()->randomFloat(2, 0.1, 10.0) . ' lbs',
            'length' => fake()->randomFloat(1, 4.0, 24.0) . ' inches',
            'color' => fake()->randomElement(['Black', 'Red', 'Blue', 'Yellow', 'Silver'])
        ];

        return $attributesByCategory[$categoryName] ?? $defaultAttributes;
    }

    private function updateDenormalizedFields(): void
    {
        // Update in smaller chunks to avoid memory issues
        $chunkSize = 5000;
        $bar = $this->command->getOutput()->createProgressBar(ceil($this->totalProducts / $chunkSize));
        $bar->start();

        Product::with(['category', 'brand', 'manufacturer'])->chunk($chunkSize, function ($products) use ($bar) {
            $updates = [];
            foreach ($products as $product) {
                $updates[] = [
                    'id' => $product->id,
                    'category_name' => $product->category->name,
                    'brand_name' => $product->brand->name,
                    'manufacturer_name' => $product->manufacturer->name,
                ];
            }

            // Bulk update using raw SQL for better performance
            foreach ($updates as $update) {
                DB::table('products')
                    ->where('id', $update['id'])
                    ->update([
                        'category_name' => $update['category_name'],
                        'brand_name' => $update['brand_name'],
                        'manufacturer_name' => $update['manufacturer_name'],
                    ]);
            }

            $bar->advance();
        });

        $bar->finish();
        $this->command->newLine();
    }

    private function printFinalStats(): void
    {
        $this->command->table(['Metric', 'Count'], [
            ['Total Products', number_format(Product::count())],
            ['Categories', Category::count()],
            ['Brands', Brand::count()],
            ['Manufacturers', Manufacturer::count()],
            ['Average Products per Category', number_format(Product::count() / Category::count(), 0)],
            ['Average Products per Brand', number_format(Product::count() / Brand::count(), 0)],
        ]);

        $this->command->info('💾 Database size impact: ~' . number_format(Product::count() * 0.5, 0) . ' MB estimated');
    }
}
