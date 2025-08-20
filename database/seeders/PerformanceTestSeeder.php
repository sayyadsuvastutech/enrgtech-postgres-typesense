<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Manufacturer;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PerformanceTestSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('⚡ Starting performance test seeding with millions of records...');
        $this->command->warn('⚠️  This seeder will create millions of records for performance testing.');
        
        if (!$this->command->confirm('Do you want to continue?', false)) {
            $this->command->info('Performance test seeding cancelled.');
            return;
        }

        $startTime = microtime(true);

        // Ensure we have base data first
        $this->ensureBaseData();

        // Create millions of products in chunks for better performance
        $this->createMassProducts(1000000); // 1 million products

        $endTime = microtime(true);
        $duration = round($endTime - $startTime, 2);

        $this->command->info("⚡ Performance test seeding completed in {$duration} seconds!");
        $this->printPerformanceSummary();
    }

    private function ensureBaseData(): void
    {
        // Ensure we have enough base data for relationships
        if (Category::count() < 20) {
            $this->command->info('Creating additional categories for performance testing...');
            Category::factory()->count(50)->create();
        }

        if (Brand::count() < 50) {
            $this->command->info('Creating additional brands for performance testing...');
            Brand::factory()->count(100)->create();
        }

        if (Manufacturer::count() < 50) {
            $this->command->info('Creating additional manufacturers for performance testing...');
            Manufacturer::factory()->count(100)->create();
        }
    }

    private function createMassProducts(int $totalCount): void
    {
        $chunkSize = 10000; // Insert 10K records at a time
        $chunks = ceil($totalCount / $chunkSize);
        
        $categoryIds = Category::pluck('id')->toArray();
        $brandIds = Brand::pluck('id')->toArray();
        $manufacturerIds = Manufacturer::pluck('id')->toArray();

        $this->command->info("Creating {$totalCount} products in {$chunks} chunks of {$chunkSize}...");
        
        $progressBar = $this->command->getOutput()->createProgressBar($chunks);
        $progressBar->start();

        for ($i = 0; $i < $chunks; $i++) {
            $currentChunkSize = min($chunkSize, $totalCount - ($i * $chunkSize));
            
            $this->createProductChunk($currentChunkSize, $categoryIds, $brandIds, $manufacturerIds);
            
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->command->newLine();
    }

    private function createProductChunk(int $count, array $categoryIds, array $brandIds, array $manufacturerIds): void
    {
        $products = [];
        $now = now();

        // Pre-fetch the name mappings for better performance
        $categoryNames = Category::pluck('name', 'id')->toArray();
        $brandNames = Brand::pluck('name', 'id')->toArray();
        $manufacturerNames = Manufacturer::pluck('name', 'id')->toArray();

        for ($i = 0; $i < $count; $i++) {
            $sku = 'PT' . str_pad($i + (time() % 100000), 8, '0', STR_PAD_LEFT);
            $name = $this->generateRandomProductName();
            
            $categoryId = $categoryIds[array_rand($categoryIds)];
            $brandId = $brandIds[array_rand($brandIds)];
            $manufacturerId = $manufacturerIds[array_rand($manufacturerIds)];
            
            $products[] = [
                'name' => $name,
                'slug' => \Illuminate\Support\Str::slug($name . '-' . $sku),
                'description' => $this->generateRandomDescription(),
                'sku' => $sku,
                'price' => fake()->randomFloat(2, 1, 999),
                'stock_quantity' => fake()->numberBetween(0, 1000),
                'status' => fake()->randomElement(['active', 'inactive']),
                'category_id' => $categoryId,
                'brand_id' => $brandId,
                'manufacturer_id' => $manufacturerId,
                'category_name' => $categoryNames[$categoryId],
                'brand_name' => $brandNames[$brandId],
                'manufacturer_name' => $manufacturerNames[$manufacturerId],
                'images' => json_encode($this->generateRandomImages()),
                'thumbnails' => json_encode($this->generateRandomThumbnails()),
                'attributes' => json_encode($this->generateRandomAttributes()),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // Insert in single query for better performance
        DB::table('products')->insert($products);
    }

    private function generateRandomProductName(): string
    {
        $prefixes = ['Pro', 'Max', 'Ultra', 'Super', 'Heavy', 'Industrial', 'Professional', 'Premium'];
        $tools = [
            'Drill', 'Saw', 'Grinder', 'Hammer', 'Wrench', 'Screwdriver', 'Pliers',
            'Fuse', 'Switch', 'Connector', 'Cable', 'Outlet', 'Breaker',
            'Gloves', 'Helmet', 'Glasses', 'Vest', 'Belt'
        ];
        $suffixes = ['Pro', 'XL', 'HD', 'Max', 'Plus', 'Elite', 'Master'];

        return fake()->randomElement($prefixes) . ' ' . 
               fake()->randomElement($tools) . ' ' .
               fake()->randomElement($suffixes) . ' ' .
               fake()->numberBetween(100, 9999);
    }

    private function generateRandomDescription(): string
    {
        $templates = [
            'High-performance tool designed for professional use in demanding environments.',
            'Durable construction ensures long-lasting performance and reliability.',
            'Essential equipment for construction, electrical, and industrial applications.',
            'Precision-engineered for maximum efficiency and user safety.',
            'Professional-grade quality meets industry standards and certifications.'
        ];

        return fake()->randomElement($templates);
    }

    private function generateRandomImages(): array
    {
        $count = fake()->numberBetween(2, 4);
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

    private function generateRandomAttributes(): array
    {
        $attributeTypes = [
            ['material' => 'steel', 'weight' => fake()->randomFloat(2, 0.1, 10) . ' lbs'],
            ['voltage' => fake()->randomElement(['12V', '18V', '20V']), 'battery_type' => 'Li-ion'],
            ['amperage' => fake()->randomElement(['15A', '20A', '30A']), 'voltage_rating' => '250V'],
            ['size' => fake()->randomElement(['S', 'M', 'L', 'XL']), 'color' => 'yellow'],
        ];

        return fake()->randomElement($attributeTypes);
    }


    private function printPerformanceSummary(): void
    {
        $this->command->table(['Model', 'Count'], [
            ['Categories', number_format(Category::count())],
            ['Brands', number_format(Brand::count())],
            ['Manufacturers', number_format(Manufacturer::count())],
            ['Products', number_format(Product::count())],
        ]);

        // Show some performance metrics
        $this->command->info('Performance Test Data Created:');
        $this->command->line('• Database size increased significantly for testing');
        $this->command->line('• Use this data to test search performance, pagination, and indexing');
        $this->command->line('• Monitor query performance with PostgreSQL EXPLAIN ANALYZE');
        $this->command->line('• Test full-text search capabilities with large datasets');
    }
}
