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

    private array $relatedProductData = [];

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

            $this->createProductBatch($currentBatchSize, $categories, $brands, $manufacturers, $batch);
            $bar->advance();

            // Memory cleanup every 10 batches
            if ($batch % 10 === 0) {
                $this->command->info("\n🧹 Memory cleanup at batch ".($batch + 1));
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

    private function createProductBatch(int $count, $categories, $brands, $manufacturers, int $batchNumber): void
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
                'title' => $productData['name'], // Use name as title
                'description' => $productData['description'],
                'product_number' => $productData['sku'],
                'manufacturer_product_number' => $productData['sku'],
                'category_id' => $category->id,
                'brand_id' => $brand->id,
                'manufacturer_id' => $manufacturer->id,
                'category_name' => $category->name,
                'manufacturer_name' => $manufacturer->name,
                'brand_name' => $brand->name,
                'is_rohs_compliant' => fake()->boolean(80), // 80% chance of being RoHS compliant
                'is_verified' => fake()->boolean(70), // 70% chance of being verified
                'is_pushed' => fake()->boolean(50), // 50% chance of being pushed
                'status_id' => fake()->randomElement([1, 1, 1, 2]), // Mostly active (status_id = 1)
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];

            // Store additional data for related tables
            $this->relatedProductData[] = [
                'product_index' => count($products) - 1,
                'price_data' => $productData['price_data'],
                'quantity_data' => $productData['quantity_data'],
                'images_data' => $productData['images_data'],
                'attributes_data' => $productData['attributes_data'],
            ];
        }

        // Bulk insert products for performance
        DB::table('products')->insert($products);

        // Get the inserted product IDs
        $startId = DB::table('products')
            ->orderBy('id', 'desc')
            ->limit($count)
            ->pluck('id')
            ->reverse()
            ->values();

        // Create related data
        $this->createRelatedProductData($startId);

        // Clear the related data array to free memory
        $this->relatedProductData = [];
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
        $sku = strtoupper(substr($brand->name, 0, 3)).'-'.
               strtoupper(substr($category->name, 0, 3)).'-'.
               $timestamp.'-'.$random;

        return [
            'name' => $productName,
            'description' => $this->generateProductDescription($categoryName, $brand->name, $productName),
            'sku' => $sku,
            'price_data' => [
                'pricing_ranges' => [
                    [
                        'from' => 1,
                        'to' => 9,
                        'price' => $pricing['price'],
                        'currency' => 'USD',
                        'effective_date' => now()->toDateString(),
                        'expires_date' => now()->addMonths(6)->toDateString(),
                    ],
                    [
                        'from' => 10,
                        'to' => 99,
                        'price' => round($pricing['price'] * 0.9, 2),
                        'currency' => 'USD',
                        'effective_date' => now()->toDateString(),
                        'expires_date' => now()->addMonths(6)->toDateString(),
                    ],
                    [
                        'from' => 100,
                        'to' => null,
                        'price' => round($pricing['price'] * 0.8, 2),
                        'currency' => 'USD',
                        'effective_date' => now()->toDateString(),
                        'expires_date' => now()->addMonths(6)->toDateString(),
                    ],
                ],
                'currency' => 'USD',
                'unit' => 'each',
            ],
            'quantity_data' => [
                'quantity' => fake()->numberBetween(0, 500),
                'availability_status' => fake()->randomElement(['in_stock', 'low_stock', 'out_of_stock', 'backorder']),
                'unit' => 'each',
            ],
            'images_data' => $this->generateRandomImages(),
            'attributes_data' => $this->generateRandomAttributes($categoryName),
        ];
    }

    private function generateProductName(string $categoryName, string $brandName): string
    {
        $productTypes = [
            // Energy Technology Products
            'solar_panels' => ['Monocrystalline Solar Panel', 'Polycrystalline Solar Panel', 'Thin Film Solar Panel', 'Bifacial Solar Panel', 'PERC Solar Panel'],
            'wind_turbines' => ['Horizontal Axis Wind Turbine', 'Vertical Axis Wind Turbine', 'Offshore Wind Turbine', 'Small Wind Turbine', 'Micro Wind Turbine'],
            'battery_storage' => ['Lithium-ion Battery Storage', 'Lead-acid Battery Storage', 'Flow Battery Storage', 'Sodium-ion Battery Storage', 'Solid State Battery'],
            'inverters' => ['String Inverter', 'Power Optimizer', 'Microinverter', 'Central Inverter', 'Hybrid Inverter'],
            'energy_monitoring' => ['Smart Meter', 'Energy Monitor', 'Power Analyzer', 'Load Monitor', 'Grid Tie Monitor'],
            // Traditional Products
            'screwdrivers' => ['Phillips Head Screwdriver', 'Flathead Screwdriver', 'Torx Screwdriver', 'Robertson Screwdriver', 'Precision Screwdriver Set'],
            'wrenches' => ['Combination Wrench', 'Socket Wrench', 'Adjustable Wrench', 'Pipe Wrench', 'Box End Wrench'],
            'hammers' => ['Claw Hammer', 'Ball Peen Hammer', 'Sledge Hammer', 'Dead Blow Hammer', 'Framing Hammer'],
            'pliers' => ['Needle Nose Pliers', 'Wire Strippers', 'Diagonal Cutters', 'Locking Pliers', 'Crimping Pliers'],
            'drills' => ['Cordless Drill', 'Impact Driver', 'Hammer Drill', 'Right Angle Drill', 'Magnetic Drill'],
            'saws' => ['Circular Saw', 'Jigsaw', 'Reciprocating Saw', 'Miter Saw', 'Band Saw'],
            'sanders' => ['Random Orbit Sander', 'Belt Sander', 'Palm Sander', 'Detail Sander', 'Drum Sander'],
            'grinders' => ['Angle Grinder', 'Die Grinder', 'Bench Grinder', 'Surface Grinder', 'Cut-off Tool'],
            'fuses' => ['Fast-Acting Fuse', 'Time-Delay Fuse', 'Current-Limiting Fuse', 'Cartridge Fuse', 'Blade Fuse'],
            'switches' => ['Toggle Switch', 'Rocker Switch', 'Push Button Switch', 'Rotary Switch', 'Slide Switch'],
            'connectors' => ['Wire Connector', 'Terminal Block', 'Junction Box', 'Cable Connector', 'Splice Connector'],
            'outlets' => ['GFCI Outlet', 'USB Outlet', 'Weather Resistant Outlet', 'Smart Outlet', 'Industrial Outlet'],
        ];

        $types = $productTypes[$categoryName] ?? ['Professional Tool', 'Industrial Component', 'Commercial Equipment'];
        $baseProduct = fake()->randomElement($types);

        // Add energy technology specifications
        $energySpecs = [
            '300W', '400W', '500W', '1kW', '2kW', '5kW', '10kW', '100kW', '1MW',
            'High Efficiency', 'Premium Grade', 'Commercial Grade', 'Industrial Grade',
            'Smart', 'Connected', 'IoT Enabled', 'Grid-Tie', 'Off-Grid', 'Hybrid',
        ];

        // Traditional product specs
        $traditionalSpecs = [
            '12V', '18V', '20V', '24V', 'Heavy Duty', 'Professional', 'Compact', 'Brushless',
            'Cordless', 'Pneumatic', 'Electric', 'Manual', 'Digital', 'Analog', 'Precision',
        ];

        $isEnergyTech = in_array($categoryName, ['solar_panels', 'wind_turbines', 'battery_storage', 'inverters', 'energy_monitoring']);
        $specs = $isEnergyTech ? $energySpecs : $traditionalSpecs;
        $spec = fake()->randomElement($specs);

        return "{$brandName} {$spec} {$baseProduct}";
    }

    private function generatePricing(string $categoryName, string $brandName): array
    {
        // Premium brands command higher prices
        $premiumBrands = ['sunpower', 'tesla', 'lg solar', 'vestas', 'siemens gamesa', 'milwaukee', 'dewalt', 'makita', 'bosch', 'festool', 'hilti', 'fluke', 'klein'];
        $isPremium = in_array(strtolower($brandName), $premiumBrands);

        // Base price ranges by category
        $priceRanges = [
            // Energy Technology Pricing
            'solar_panels' => ['min' => 200, 'max' => 800],
            'wind_turbines' => ['min' => 500000, 'max' => 5000000], // MW scale pricing
            'battery_storage' => ['min' => 5000, 'max' => 50000],
            'inverters' => ['min' => 1000, 'max' => 15000],
            'energy_monitoring' => ['min' => 100, 'max' => 2000],
            // Traditional Product Pricing
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
            "Commercial-grade {$productName} offering outstanding performance and value. Ideal for professional tradespeople and industrial applications.",
        ];

        return fake()->randomElement($templates);
    }

    private function generateRandomImages(): array
    {
        $count = fake()->numberBetween(2, 5);
        $images = [];

        for ($i = 0; $i < $count; $i++) {
            $images[] = [
                'url' => 'https://picsum.photos/800/600?random='.fake()->numberBetween(1, 50000),
                'path' => 'full/'.fake()->sha1().'.jpg',
                'thumbnail' => 'https://picsum.photos/300/300?random='.fake()->numberBetween(1, 50000),
                'is_primary' => $i === 0,
                'alt_text' => fake()->randomElement(['Product main view', 'Product detail', 'Product in use', 'Product packaging']),
                'caption' => fake()->optional()->sentence(),
                'dimensions' => [
                    'width' => fake()->randomElement([800, 1024, 1200]),
                    'height' => fake()->randomElement([600, 768, 900]),
                ],
                'file_size' => fake()->numberBetween(100000, 500000),
                'format' => 'jpg',
                'created_at' => now()->toISOString(),
            ];
        }

        return $images;
    }

    private function generateRandomThumbnails(): array
    {
        return [
            '150x150' => 'https://picsum.photos/150/150?random='.fake()->numberBetween(1, 50000),
            '300x300' => 'https://picsum.photos/300/300?random='.fake()->numberBetween(1, 50000),
        ];
    }

    private function generateRandomAttributes(string $categoryName): array
    {
        $attributesByCategory = [
            // Energy Technology Attributes
            'solar_panels' => [
                'technology' => fake()->randomElement(['Monocrystalline', 'Polycrystalline', 'Thin Film']),
                'power_output' => fake()->randomElement([300, 350, 400, 450, 500]).'W',
                'efficiency' => fake()->randomFloat(1, 18.5, 22.8).'%',
                'voltage_max_power' => fake()->randomFloat(1, 30, 40).'V',
                'operating_temperature' => '-40°C to +85°C',
                'warranty' => fake()->randomElement([20, 25]).' years',
                'certification' => 'IEC 61215, IEC 61730',
            ],
            'wind_turbines' => [
                'turbine_type' => fake()->randomElement(['Horizontal Axis', 'Vertical Axis']),
                'rated_power' => fake()->randomElement([1.5, 2.0, 2.5, 3.0, 4.0]).'MW',
                'rotor_diameter' => fake()->randomFloat(1, 80, 150).'m',
                'cut_in_wind_speed' => fake()->randomFloat(1, 3, 4).' m/s',
                'design_life' => '20 years',
                'certification' => 'IEC 61400-1, IEC 61400-22',
            ],
            'battery_storage' => [
                'battery_type' => fake()->randomElement(['Lithium-ion', 'Lead-acid', 'Flow Battery']),
                'usable_capacity' => fake()->randomElement([5, 7, 10, 13.5, 16, 20]).'kWh',
                'round_trip_efficiency' => fake()->randomFloat(1, 90, 96).'%',
                'cycle_life' => fake()->randomElement(['6000', '8000', '10000']),
                'operating_temperature' => '-10°C to +50°C',
                'warranty' => fake()->randomElement([10, 15, 20]).' years',
            ],
            'inverters' => [
                'inverter_type' => fake()->randomElement(['String', 'Power Optimizer', 'Microinverter']),
                'ac_power_rating' => fake()->randomElement([3, 5, 7.5, 10, 15]).'kW',
                'efficiency' => fake()->randomFloat(1, 95, 99).'%',
                'mppt_trackers' => fake()->randomElement([1, 2, 3, 4]),
                'warranty' => fake()->randomElement([10, 15, 20]).' years',
            ],
            'energy_monitoring' => [
                'device_type' => fake()->randomElement(['Smart Meter', 'Energy Monitor', 'Power Analyzer']),
                'measurement_accuracy' => fake()->randomElement(['±0.5%', '±1%', '±2%']),
                'communication' => fake()->randomElement(['Wi-Fi', 'Ethernet', 'Zigbee']),
                'warranty' => fake()->randomElement([2, 3, 5]).' years',
            ],
            // Traditional Product Attributes
            'drills' => [
                'voltage' => fake()->randomElement(['12V', '18V', '20V', '24V']),
                'battery_type' => 'Li-ion',
                'torque' => fake()->numberBetween(300, 1500).' in-lbs',
                'speed' => fake()->numberBetween(1200, 2000).' RPM',
                'weight' => fake()->randomFloat(1, 2.5, 8.0).' lbs',
            ],
            'saws' => [
                'blade_size' => fake()->randomElement(['7-1/4"', '10"', '12"']),
                'motor_power' => fake()->randomElement(['15A', '10A', '13A']),
                'cutting_depth' => fake()->randomFloat(1, 2.0, 4.0).'"',
                'weight' => fake()->randomFloat(1, 8.0, 25.0).' lbs',
            ],
            'fuses' => [
                'amperage' => fake()->randomElement(['5A', '10A', '15A', '20A', '30A']),
                'voltage_rating' => fake()->randomElement(['125V', '250V', '600V']),
                'fuse_type' => fake()->randomElement(['Fast-Acting', 'Time-Delay', 'Current-Limiting']),
                'material' => 'Ceramic/Glass',
                'certification' => 'UL Listed, CSA Certified',
            ],
            'screwdrivers' => [
                'screwdriver_type' => fake()->randomElement(['Phillips', 'Flathead', 'Torx']),
                'tip_size' => fake()->randomElement(['#1', '#2', '#3', '1/4"']),
                'handle_material' => fake()->randomElement(['Rubber Grip', 'Cushion Grip']),
                'length' => fake()->randomElement(['6"', '8"', '10"']),
                'warranty' => fake()->randomElement(['Lifetime', '5 Years']),
            ],
            'switches' => [
                'amperage' => fake()->randomElement(['15A', '20A', '30A']),
                'voltage_rating' => '120-277V',
                'poles' => fake()->randomElement(['Single', 'Double', 'Triple']),
                'color' => fake()->randomElement(['White', 'Ivory', 'Light Almond']),
            ],
        ];

        // Default attributes for categories not specifically defined
        $defaultAttributes = [
            'material' => fake()->randomElement(['Steel', 'Aluminum', 'Plastic', 'Composite']),
            'weight' => fake()->randomFloat(2, 0.1, 10.0).' lbs',
            'operating_temperature' => fake()->randomElement(['-20°C to +60°C', '-10°C to +50°C']),
            'certification' => fake()->randomElement(['UL Listed', 'CE', 'RoHS']),
            'warranty' => fake()->randomElement([1, 2, 3, 5]).' years',
        ];

        return $attributesByCategory[$categoryName] ?? $defaultAttributes;
    }

    private function createRelatedProductData($productIds): void
    {
        $sources = ['dk', 'rs', 'ct', 'vp', 'et'];
        $timestamp = now();

        // Prepare bulk insert arrays
        $productSources = [];
        $productPrices = [];
        $productQuantities = [];
        $productImages = [];
        $productAttributes = [];

        foreach ($productIds as $index => $productId) {
            $relatedData = $this->relatedProductData[$index] ?? null;
            if (! $relatedData) {
                continue;
            }

            $sourceName = fake()->randomElement($sources);

            // Create product source
            $productSources[] = [
                'product_id' => $productId,
                'source_name' => $sourceName,
                'source_product_id' => fake()->numberBetween(10000, 99999),
                'source_url' => 'https://example.com/product/'.fake()->numberBetween(10000, 99999),
                'source_date' => json_encode(['last_updated' => now()->toDateString()]),
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];

            // Create product price
            $productPrices[] = [
                'product_id' => $productId,
                'source_name' => $sourceName,
                'pricing_ranges' => json_encode($relatedData['price_data']['pricing_ranges']),
                'currency' => $relatedData['price_data']['currency'],
                'unit' => $relatedData['price_data']['unit'],
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];

            // Create product quantity
            $productQuantities[] = [
                'product_id' => $productId,
                'source_name' => $sourceName,
                'unit' => $relatedData['quantity_data']['unit'],
                'quantity' => $relatedData['quantity_data']['quantity'],
                'availability_status' => $relatedData['quantity_data']['availability_status'],
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];

            // Create product images
            $productImages[] = [
                'product_id' => $productId,
                'source_name' => $sourceName,
                'images' => json_encode($relatedData['images_data']),
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];

            // Create product attributes
            $productAttributes[] = [
                'product_id' => $productId,
                'source_name' => $sourceName,
                'attributes' => json_encode($relatedData['attributes_data']),
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];
        }

        // Bulk insert all related data
        if (! empty($productSources)) {
            DB::table('product_sources')->insert($productSources);
        }
        if (! empty($productPrices)) {
            DB::table('product_prices')->insert($productPrices);
        }
        if (! empty($productQuantities)) {
            DB::table('product_quantities')->insert($productQuantities);
        }
        if (! empty($productImages)) {
            DB::table('product_images')->insert($productImages);
        }
        if (! empty($productAttributes)) {
            DB::table('product_attributes')->insert($productAttributes);
        }
    }

    private function updateDenormalizedFields(): void
    {
        // Update in smaller chunks to avoid memory issues
        $chunkSize = 5000;
        $bar = $this->command->getOutput()->createProgressBar(ceil($this->totalProducts / $chunkSize));
        $bar->start();

        Product::with(['category', 'manufacturer', 'brand'])->chunk($chunkSize, function ($products) use ($bar) {
            $updates = [];
            foreach ($products as $product) {
                $updates[] = [
                    'id' => $product->id,
                    'category_name' => $product->category?->name,
                    'manufacturer_name' => $product->manufacturer?->name,
                    'brand_name' => $product->brand?->name,
                ];
            }

            // Bulk update using raw SQL for better performance
            foreach ($updates as $update) {
                DB::table('products')
                    ->where('id', $update['id'])
                    ->update([
                        'category_name' => $update['category_name'],
                        'manufacturer_name' => $update['manufacturer_name'],
                        'brand_name' => $update['brand_name'],
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
            ['Average Products per Category', number_format(Product::count() / max(Category::count(), 1), 0)],
            ['Average Products per Manufacturer', number_format(Product::count() / max(Manufacturer::count(), 1), 0)],
        ]);

        $this->command->info('💾 Database size impact: ~'.number_format(Product::count() * 0.5, 0).' MB estimated');
    }
}
