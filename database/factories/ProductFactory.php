<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Manufacturer;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ProductFactory extends Factory
{
    public function definition(): array
    {
        $name = $this->generateProductName();
        $productNumber = $this->generateProductNumber();
        $title = $this->generateProductTitle($name);

        $category = Category::factory()->create();
        $manufacturer = Manufacturer::factory()->create();

        return [
            'name' => $name,
            'title' => $title,
            'code' => $this->faker->optional()->bothify('??###'),
            'product_number' => $productNumber,
            'manufacturer_product_number' => $this->faker->optional()->bothify('MPN-####'),
            'manufacturer_product_slug' => $this->faker->optional()->slug(),
            'description' => $this->faker->paragraphs(3, true),
            'category_id' => $category->id,
            'manufacturer_id' => $manufacturer->id,
            'breadcrumb' => $this->generateBreadcrumb($category->name, $name),
            'meta_title' => $this->faker->optional()->sentence(6),
            'meta_description' => $this->faker->optional()->sentence(12),
            'is_rohs_compliant' => $this->faker->boolean(70),
            'is_verified' => $this->faker->boolean(85),
            'is_pushed' => $this->faker->boolean(60),
            'total_reviews' => $this->faker->numberBetween(0, 500),
            'average_rating' => $this->faker->optional()->randomFloat(1, 1, 5),
            'video_url' => $this->faker->optional()->url(),
            'status_id' => $this->faker->randomElement([1, 1, 1, 1, 2]), // 80% active, 20% inactive
            'session_insert_id' => $this->faker->optional()->randomNumber(5),
            'session_update_id' => $this->faker->optional()->randomNumber(5),
            'is_updated' => $this->faker->boolean(30),
            'created_by' => $this->faker->optional()->numberBetween(1, 100),
            'updated_by' => $this->faker->optional()->numberBetween(1, 100),
            'category_name' => $category->name,
            'manufacturer_name' => $manufacturer->name,
        ];
    }

    public function handTool(): static
    {
        return $this->state(function () {
            $tools = [
                'Phillips Head Screwdriver', 'Flathead Screwdriver', 'Torx Screwdriver',
                'Combination Wrench', 'Socket Wrench', 'Adjustable Wrench',
                'Claw Hammer', 'Ball Peen Hammer', 'Dead Blow Hammer',
                'Needle Nose Pliers', 'Diagonal Cutters', 'Wire Strippers'
            ];

            $tool = $this->faker->randomElement($tools);
            $brand = $this->faker->randomElement(['Stanley', 'Klein Tools', 'Craftsman', 'Snap-on']);
            $model = $this->faker->bothify('??###');

            return [
                'name' => "{$brand} {$tool} - Model {$model}",
                'description' => $this->generateHandToolDescription($tool),
                'price' => $this->faker->randomFloat(2, 5, 200),
                // attributes will be created separately in ProductAttribute factory
            ];
        });
    }

    public function powerTool(): static
    {
        return $this->state(function () {
            $tools = [
                'Cordless Drill', 'Impact Driver', 'Hammer Drill', 'Circular Saw',
                'Jigsaw', 'Random Orbit Sander', 'Angle Grinder', 'Miter Saw'
            ];

            $tool = $this->faker->randomElement($tools);
            $brand = $this->faker->randomElement(['DeWalt', 'Milwaukee', 'Makita', 'Bosch']);
            $model = $this->faker->bothify('???####');

            return [
                'name' => "{$brand} {$tool} - Model {$model}",
                'description' => $this->generatePowerToolDescription($tool),
                'price' => $this->faker->randomFloat(2, 50, 800),
                // attributes will be created separately in ProductAttribute factory
            ];
        });
    }

    public function electricalFuse(): static
    {
        return $this->state(function () {
            $amperages = ['5A', '10A', '15A', '20A', '25A', '30A', '40A', '50A'];
            $voltages = ['125V', '250V', '600V'];
            $types = ['fast_blow', 'slow_blow', 'time_delay'];
            $mountings = ['panel_mount', 'fuse_block', 'inline'];

            $amperage = $this->faker->randomElement($amperages);
            $voltage = $this->faker->randomElement($voltages);
            $type = $this->faker->randomElement($types);

            return [
                'name' => "Electrical Fuse {$amperage} {$voltage} - {$type}",
                'description' => $this->generateFuseDescription($amperage, $voltage, $type),
                'price' => $this->faker->randomFloat(2, 1, 50),
                // attributes will be created separately in ProductAttribute factory
            ];
        });
    }

    public function safetyEquipment(): static
    {
        return $this->state(function () {
            $equipment = [
                'Safety Glasses', 'Work Gloves', 'Hard Hat', 'Knee Pads',
                'Safety Goggles', 'Cut Resistant Gloves', 'Face Shield', 'Hearing Protection'
            ];

            $item = $this->faker->randomElement($equipment);
            $brand = $this->faker->randomElement(['3M', 'Honeywell', 'MSA', 'Pyramex']);

            return [
                'name' => "{$brand} {$item}",
                'description' => $this->generateSafetyDescription($item),
                'price' => $this->faker->randomFloat(2, 10, 300),
                // attributes will be created separately in ProductAttribute factory
            ];
        });
    }

    private function generateProductName(): string
    {
        $brands = ['ProTool', 'MaxForce', 'TechCraft', 'PowerMax', 'ElectroPlus'];
        $descriptors = ['Professional', 'Heavy Duty', 'Premium', 'Industrial', 'Commercial'];
        $tools = ['Tool', 'Component', 'Equipment', 'Device', 'Instrument'];

        return $this->faker->randomElement($brands) . ' ' .
               $this->faker->randomElement($descriptors) . ' ' .
               $this->faker->randomElement($tools);
    }

    private function generateProductNumber(): string
    {
        return $this->faker->unique()->bothify('PN-########');
    }

    private function generateProductTitle(string $name): string
    {
        return $name . ' - ' . $this->faker->words(2, true);
    }

    private function generateBreadcrumb(string $categoryName, string $productName): string
    {
        return "Home > {$categoryName} > " . Str::limit($productName, 30);
    }

    // Image generation methods moved to ProductImageFactory

    private function generateHandToolAttributes(string $tool): array
    {
        $baseAttributes = [
            'material' => $this->faker->randomElement(['steel', 'chrome_vanadium', 'carbon_steel']),
            'finish' => $this->faker->randomElement(['chrome', 'black_oxide', 'zinc_plated']),
            'warranty' => $this->faker->randomElement(['lifetime', '1_year', '5_years']),
        ];

        if (str_contains(strtolower($tool), 'screwdriver')) {
            return array_merge($baseAttributes, [
                'handle_type' => $this->faker->randomElement(['rubber', 'plastic', 'cushion_grip']),
                'length' => $this->faker->randomElement(['4 inches', '6 inches', '8 inches', '10 inches']),
                'tip_size' => $this->faker->randomElement(['#0', '#1', '#2', '#3', '1/4"', '3/16"']),
                'magnetic_tip' => $this->faker->boolean(),
            ]);
        }

        if (str_contains(strtolower($tool), 'wrench')) {
            return array_merge($baseAttributes, [
                'size' => $this->faker->randomElement(['8mm', '10mm', '12mm', '1/4"', '5/16"', '3/8"']),
                'type' => $this->faker->randomElement(['combination', 'open_end', 'box_end']),
                'length' => $this->faker->randomElement(['6 inches', '8 inches', '10 inches']),
                'weight' => $this->faker->randomFloat(2, 0.2, 2.0) . ' lbs',
            ]);
        }

        return array_merge($baseAttributes, [
            'length' => $this->faker->randomElement(['6 inches', '8 inches', '10 inches', '12 inches']),
            'weight' => $this->faker->randomFloat(2, 0.1, 3.0) . ' lbs',
        ]);
    }

    private function generatePowerToolAttributes(string $tool): array
    {
        $baseAttributes = [
            'voltage' => $this->faker->randomElement(['12V', '18V', '20V', '40V']),
            'battery_type' => $this->faker->randomElement(['Li-ion', 'NiMH', 'NiCad']),
            'warranty' => $this->faker->randomElement(['1_year', '3_years', '5_years']),
            'weight' => $this->faker->randomFloat(2, 2.0, 15.0) . ' lbs',
        ];

        if (str_contains(strtolower($tool), 'drill')) {
            return array_merge($baseAttributes, [
                'chuck_size' => $this->faker->randomElement(['3/8 inch', '1/2 inch', '5/8 inch']),
                'torque' => $this->faker->numberBetween(200, 1000) . ' in-lbs',
                'speed_settings' => $this->faker->randomElement(['2-speed', 'variable', 'single_speed']),
                'led_light' => $this->faker->boolean(80),
                'belt_clip' => $this->faker->boolean(60),
            ]);
        }

        if (str_contains(strtolower($tool), 'saw')) {
            return array_merge($baseAttributes, [
                'blade_diameter' => $this->faker->randomElement(['6.5 inches', '7.25 inches', '10 inches']),
                'cut_capacity' => $this->faker->randomElement(['2x4 at 90°', '2x8 at 45°', '4x4 at 90°']),
                'bevel_capacity' => $this->faker->randomElement(['45°', '50°', '56°']),
                'laser_guide' => $this->faker->boolean(70),
            ]);
        }

        return $baseAttributes;
    }

    private function generateSafetyAttributes(string $equipment): array
    {
        $baseAttributes = [
            'color' => $this->faker->randomElement(['yellow', 'orange', 'white', 'clear', 'blue']),
            'size' => $this->faker->randomElement(['S', 'M', 'L', 'XL', 'Universal']),
            'certification' => $this->faker->randomElement(['ANSI Z87.1', 'CE', 'OSHA Compliant']),
        ];

        if (str_contains(strtolower($equipment), 'glove')) {
            return array_merge($baseAttributes, [
                'material' => $this->faker->randomElement(['leather', 'nitrile', 'latex', 'cut_resistant']),
                'grip_type' => $this->faker->randomElement(['textured', 'smooth', 'dotted']),
                'cut_level' => $this->faker->randomElement(['A1', 'A2', 'A3', 'A4', 'A5']),
                'thickness' => $this->faker->randomElement(['3 mil', '5 mil', '8 mil', '15 mil']),
            ]);
        }

        if (str_contains(strtolower($equipment), 'glasses') || str_contains(strtolower($equipment), 'goggles')) {
            return array_merge($baseAttributes, [
                'lens_type' => $this->faker->randomElement(['clear', 'tinted', 'anti-fog', 'anti-scratch']),
                'frame_material' => $this->faker->randomElement(['polycarbonate', 'nylon', 'metal']),
                'uv_protection' => $this->faker->boolean(90),
                'wrap_around' => $this->faker->boolean(60),
            ]);
        }

        return $baseAttributes;
    }

    private function generateHandToolDescription(string $tool): string
    {
        return "Professional grade {$tool} designed for heavy-duty use. Features ergonomic handle design for comfort during extended use. Made from high-quality materials for durability and long service life. Perfect for professional tradespeople and serious DIY enthusiasts.";
    }

    private function generatePowerToolDescription(string $tool): string
    {
        return "High-performance {$tool} engineered for professional applications. Features brushless motor technology for extended runtime and durability. Includes advanced safety features and ergonomic design for user comfort. Ideal for construction, electrical, and industrial applications.";
    }

    private function generateFuseDescription(string $amperage, string $voltage, string $type): string
    {
        return "High-quality electrical fuse rated at {$amperage} and {$voltage}. {$type} design provides reliable circuit protection. UL listed for safety and compliance. Suitable for industrial, commercial, and residential electrical applications. Ensures proper overcurrent protection.";
    }

    private function generateSafetyDescription(string $equipment): string
    {
        return "Professional {$equipment} designed to meet or exceed safety standards. Provides excellent protection while maintaining comfort and visibility. Durable construction ensures long-lasting performance in demanding work environments. Essential for workplace safety compliance.";
    }

    public function withRelationships($categoryId = null, $manufacturerId = null): static
    {
        return $this->state(function () use ($categoryId, $manufacturerId) {
            $state = [];

            if ($categoryId !== null) {
                $category = Category::find($categoryId);
                $state['category_id'] = $categoryId;
                $state['category_name'] = $category->name;
            }

            if ($manufacturerId !== null) {
                $manufacturer = Manufacturer::find($manufacturerId);
                $state['manufacturer_id'] = $manufacturerId;
                $state['manufacturer_name'] = $manufacturer->name;
            }

            return $state;
        });
    }
}
