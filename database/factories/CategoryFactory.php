<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CategoryFactory extends Factory
{
    public function definition(): array
    {
        $name = $this->faker->randomElement([
            'Electronics', 'Components', 'Semiconductors', 'Connectors', 'Sensors',
            'Power Supplies', 'Cables & Wires', 'Circuit Protection', 'Displays', 'Motors',
            'Industrial Controls', 'Test & Measurement', 'Tools & Accessories', 'Batteries'
        ]);

        return [
            'name' => $name,
            'slug' => $this->generateUniqueSlug($name),
            'description' => $this->faker->sentence(10),
            'image' => $this->faker->optional()->imageUrl(300, 200, 'business'),
            'parent_category' => null, // Will be set by factory states
            'is_main' => false, // Will be set by factory states
            'pushed' => $this->faker->boolean(70),
            'meta_title' => $this->faker->optional()->sentence(6),
            'meta_description' => $this->faker->optional()->sentence(12),
            'status_id' => $this->faker->randomElement([1, 1, 1, 2]), // 75% active, 25% inactive
            'products_count' => $this->faker->numberBetween(0, 1000),
            'created_by' => $this->faker->optional()->numberBetween(1, 100),
            'updated_by' => $this->faker->optional()->numberBetween(1, 100),
        ];
    }

    public function mainCategory(): static
    {
        return $this->state(function () {
            $name = $this->faker->randomElement([
                'Electronics', 'Components', 'Semiconductors', 'Power Management', 
                'Test Equipment', 'Industrial Controls', 'Sensors', 'Displays'
            ]);
            
            return [
                'name' => $name,
                'slug' => $this->generateUniqueSlug($name),
                'description' => "Professional {$name} for industrial and commercial applications",
                'is_main' => true,
                'parent_category' => null,
                'pushed' => true,
                'products_count' => $this->faker->numberBetween(50, 500),
            ];
        });
    }

    public function subCategory(): static
    {
        return $this->state(function () {
            $name = $this->faker->randomElement([
                'Resistors', 'Capacitors', 'Inductors', 'Diodes', 'Transistors',
                'Integrated Circuits', 'Microcontrollers', 'Memory Devices',
                'Power Modules', 'Voltage Regulators', 'Switches', 'Relays'
            ]);

            return [
                'name' => $name,
                'slug' => $this->generateUniqueSlug($name),
                'description' => "High-quality {$name} for electronic applications",
                'is_main' => false,
                'products_count' => $this->faker->numberBetween(10, 200),
            ];
        });
    }

    public function withParent($parentId): static
    {
        return $this->state([
            'parent_category' => $parentId,
            'is_main' => false,
        ]);
    }

    public function pushed(): static
    {
        return $this->state([
            'pushed' => true,
        ]);
    }

    public function unpushed(): static
    {
        return $this->state([
            'pushed' => false,
        ]);
    }

    private function generateUniqueSlug(string $name): string
    {
        $baseSlug = Str::slug($name);
        $timestamp = now()->getTimestamp();
        $random = $this->faker->numberBetween(1000, 9999);
        return $baseSlug . '-' . $timestamp . '-' . $random;
    }

    // Hierarchy methods restored for recursive structure
}
