<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Manufacturer;
use App\Models\Product;
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

        // Create root categories
        $this->command->info('📂 Creating category hierarchy...');
        $rootCategories = $this->createRootCategories();
        
        // Create subcategories
        $subcategories = $this->createSubcategories($rootCategories);
        
        // Create brands
        $this->command->info('🏷️ Creating tool and electrical brands...');
        $brands = $this->createBrands();
        
        // Create manufacturers
        $this->command->info('🏭 Creating manufacturers...');
        $manufacturers = $this->createManufacturers();
        
        // Create specific product types
        $this->command->info('🔧 Creating realistic tool and electrical products...');
        $this->createRealisticProducts($subcategories, $brands, $manufacturers);
        
        // Update denormalized fields
        $this->command->info('🔄 Updating denormalized relationship fields...');
        $this->updateDenormalizedFields();

        $this->command->info('✅ Database seeding completed successfully!');
        $this->printSeedingSummary();
    }

    private function createRootCategories(): array
    {
        $rootCategories = [];
        
        $categories = [
            'Hand Tools' => 'Professional hand tools for construction and electrical work',
            'Power Tools' => 'Cordless and corded power tools for heavy-duty applications', 
            'Electrical Components' => 'Electrical fuses, switches, connectors and components',
            'Safety Equipment' => 'Personal protective equipment and workplace safety gear'
        ];

        foreach ($categories as $name => $description) {
            $rootCategories[$name] = Category::firstOrCreate([
                'slug' => \Illuminate\Support\Str::slug($name),
            ], [
                'name' => $name,
                'description' => $description,
                'parent_id' => null,
            ]);
        }

        return $rootCategories;
    }

    private function createSubcategories(array $rootCategories): array
    {
        $subcategories = [];
        
        // Hand Tools subcategories
        $handToolSubs = [
            'Screwdrivers' => 'Phillips, flathead, Torx and specialty screwdrivers',
            'Wrenches' => 'Combination, socket, and adjustable wrenches',
            'Hammers' => 'Claw, ball peen, and dead blow hammers',
            'Pliers' => 'Needle nose, diagonal cutters, and wire strippers'
        ];
        
        foreach ($handToolSubs as $name => $description) {
            $subcategories['Hand Tools'][] = Category::firstOrCreate([
                'slug' => \Illuminate\Support\Str::slug($name),
                'parent_id' => $rootCategories['Hand Tools']->id,
            ], [
                'name' => $name,
                'description' => $description,
            ]);
        }

        // Power Tools subcategories  
        $powerToolSubs = [
            'Drills' => 'Cordless drills, impact drivers, and hammer drills',
            'Saws' => 'Circular saws, jigsaws, and reciprocating saws',
            'Sanders' => 'Random orbit, belt, and palm sanders',
            'Grinders' => 'Angle grinders and die grinders'
        ];
        
        foreach ($powerToolSubs as $name => $description) {
            $subcategories['Power Tools'][] = Category::firstOrCreate([
                'slug' => \Illuminate\Support\Str::slug($name),
                'parent_id' => $rootCategories['Power Tools']->id,
            ], [
                'name' => $name,
                'description' => $description,
            ]);
        }

        // Electrical Components subcategories
        $electricalSubs = [
            'Fuses' => 'Circuit breaker, cartridge, and blade fuses',
            'Switches' => 'Toggle, rocker, and push button switches',
            'Connectors' => 'Wire connectors, terminal blocks, and junction boxes',
            'Outlets' => 'GFCI, standard, and USB outlets'
        ];
        
        foreach ($electricalSubs as $name => $description) {
            $subcategories['Electrical Components'][] = Category::firstOrCreate([
                'slug' => \Illuminate\Support\Str::slug($name),
                'parent_id' => $rootCategories['Electrical Components']->id,
            ], [
                'name' => $name,
                'description' => $description,
            ]);
        }

        // Safety Equipment subcategories
        $safetySubs = [
            'Eye Protection' => 'Safety glasses, goggles, and face shields',
            'Hand Protection' => 'Work gloves and cut-resistant gloves',
            'Head Protection' => 'Hard hats and bump caps',
            'Body Protection' => 'Knee pads, back support belts, and vests'
        ];
        
        foreach ($safetySubs as $name => $description) {
            $subcategories['Safety Equipment'][] = Category::firstOrCreate([
                'slug' => \Illuminate\Support\Str::slug($name),
                'parent_id' => $rootCategories['Safety Equipment']->id,
            ], [
                'name' => $name,
                'description' => $description,
            ]);
        }

        return $subcategories;
    }

    private function createBrands(): array
    {
        $brands = [];
        
        // Create specific brand types
        $brands['power_tools'] = Brand::factory()->count(15)->powerToolBrand()->create();
        $brands['electrical'] = Brand::factory()->count(12)->electricalBrand()->create();
        $brands['hand_tools'] = Brand::factory()->count(10)->handToolBrand()->create();
        $brands['safety'] = Brand::factory()->count(8)->safetyBrand()->create();
        
        return $brands;
    }

    private function createManufacturers(): array
    {
        return [
            'real' => Manufacturer::factory()->count(25)->realManufacturer()->create(),
            'fictional' => Manufacturer::factory()->count(20)->fictionalManufacturer()->create(),
        ];
    }

    private function createRealisticProducts(array $subcategories, array $brands, array $manufacturers): void
    {
        $allBrands = collect($brands)->flatten();
        $allManufacturers = collect($manufacturers)->flatten();
        
        // Create 100+ real fuses with actual specifications
        $this->createElectricalFuses($subcategories, $brands['electrical'], $allManufacturers, 120);
        
        // Create 50+ hand tools
        $this->createHandTools($subcategories, $brands['hand_tools'], $allManufacturers, 80);
        
        // Create power tools
        $this->createPowerTools($subcategories, $brands['power_tools'], $allManufacturers, 120);
        
        // Create safety equipment
        $this->createSafetyEquipment($subcategories, $brands['safety'], $allManufacturers, 60);
        
        // Create additional electrical components
        $this->createElectricalComponents($subcategories, $brands['electrical'], $allManufacturers, 100);
        
        // Create more diverse products to reach 2000+ total
        $this->createDiverseProducts($subcategories, $allBrands, $allManufacturers, 1500);
    }

    private function createElectricalFuses($subcategories, $brands, $manufacturers, int $count): void
    {
        $fuseCategory = collect($subcategories['Electrical Components'])->firstWhere('name', 'Fuses');
        
        Product::factory()->count($count)->electricalFuse()->create([
            'category_id' => $fuseCategory->id,
            'brand_id' => $brands->random()->id,
            'manufacturer_id' => $manufacturers->random()->id,
        ]);
    }

    private function createHandTools($subcategories, $brands, $manufacturers, int $count): void
    {
        $handToolCategories = $subcategories['Hand Tools'];
        
        foreach ($handToolCategories as $category) {
            Product::factory()->count($count / count($handToolCategories))->handTool()->create([
                'category_id' => $category->id,
                'brand_id' => $brands->random()->id,
                'manufacturer_id' => $manufacturers->random()->id,
            ]);
        }
    }

    private function createPowerTools($subcategories, $brands, $manufacturers, int $count): void
    {
        $powerToolCategories = $subcategories['Power Tools'];
        
        foreach ($powerToolCategories as $category) {
            Product::factory()->count($count / count($powerToolCategories))->powerTool()->create([
                'category_id' => $category->id,
                'brand_id' => $brands->random()->id,
                'manufacturer_id' => $manufacturers->random()->id,
            ]);
        }
    }

    private function createSafetyEquipment($subcategories, $brands, $manufacturers, int $count): void
    {
        $safetyCategories = $subcategories['Safety Equipment'];
        
        foreach ($safetyCategories as $category) {
            Product::factory()->count($count / count($safetyCategories))->safetyEquipment()->create([
                'category_id' => $category->id,
                'brand_id' => $brands->random()->id,
                'manufacturer_id' => $manufacturers->random()->id,
            ]);
        }
    }

    private function createElectricalComponents($subcategories, $brands, $manufacturers, int $count): void
    {
        $electricalCategories = collect($subcategories['Electrical Components'])->reject(function($category) {
            return $category->name === 'Fuses'; // Already created
        });
        
        foreach ($electricalCategories as $category) {
            Product::factory()->count($count / $electricalCategories->count())->create([
                'category_id' => $category->id,
                'brand_id' => $brands->random()->id,
                'manufacturer_id' => $manufacturers->random()->id,
                'price' => fake()->randomFloat(2, 5, 200),
            ]);
        }
    }

    private function createDiverseProducts($subcategories, $brands, $manufacturers, int $count): void
    {
        $allCategories = collect($subcategories)->flatten();
        
        Product::factory()->count($count)->create()->each(function ($product) use ($allCategories, $brands, $manufacturers) {
            $product->update([
                'category_id' => $allCategories->random()->id,
                'brand_id' => $brands->random()->id,
                'manufacturer_id' => $manufacturers->random()->id,
            ]);
        });
    }

    private function updateDenormalizedFields(): void
    {
        Product::with(['category', 'brand', 'manufacturer'])->chunk(1000, function ($products) {
            foreach ($products as $product) {
                $product->update([
                    'category_name' => $product->category->name,
                    'brand_name' => $product->brand->name,
                    'manufacturer_name' => $product->manufacturer->name,
                ]);
            }
        });
    }

    private function printSeedingSummary(): void
    {
        $this->command->table(['Model', 'Count'], [
            ['Categories', Category::count()],
            ['Brands', Brand::count()],
            ['Manufacturers', Manufacturer::count()],
            ['Products', Product::count()],
            ['Users', User::count()],
        ]);
    }
}
