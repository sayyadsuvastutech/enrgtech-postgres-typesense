<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CategoryFactory extends Factory
{
    public function definition(): array
    {
        $name = $this->faker->randomElement([
            'Screwdrivers', 'Wrenches', 'Hammers', 'Pliers', 'Drill Bits',
            'Drills', 'Circular Saws', 'Jigsaws', 'Sanders', 'Grinders',
            'Fuses', 'Switches', 'Connectors', 'Outlets', 'Wire Nuts',
            'Safety Gloves', 'Hard Hats', 'Safety Glasses', 'Knee Pads', 'Tool Belts'
        ]);

        return [
            'name' => $name,
            'slug' => $this->generateUniqueSlug($name),
            'description' => $this->faker->sentence(10),
            'parent_id' => null,
        ];
    }

    public function rootCategory(): static
    {
        return $this->state(function () {
            $name = $this->faker->randomElement([
                'Hand Tools', 'Power Tools', 'Electrical Components', 'Safety Equipment'
            ]);
            
            return [
                'name' => $name,
                'slug' => $this->generateUniqueSlug($name),
                'description' => "Professional {$name} for construction and electrical work",
                'parent_id' => null,
            ];
        });
    }

    public function handTool(): static
    {
        return $this->state(function () {
            $name = $this->faker->randomElement([
                'Flathead Screwdrivers', 'Phillips Screwdrivers', 'Robertson Screwdrivers',
                'Combination Wrenches', 'Socket Wrenches', 'Adjustable Wrenches',
                'Claw Hammers', 'Ball Peen Hammers', 'Sledge Hammers',
                'Needle Nose Pliers', 'Wire Strippers', 'Diagonal Cutters'
            ]);

            return [
                'name' => $name,
                'slug' => $this->generateUniqueSlug($name),
                'description' => "High-quality {$name} for professional use",
            ];
        });
    }

    public function powerTool(): static
    {
        return $this->state(function () {
            $name = $this->faker->randomElement([
                'Cordless Drills', 'Impact Drivers', 'Hammer Drills',
                'Circular Saws', 'Miter Saws', 'Table Saws',
                'Random Orbit Sanders', 'Belt Sanders', 'Palm Sanders',
                'Angle Grinders', 'Die Grinders', 'Bench Grinders'
            ]);

            return [
                'name' => $name,
                'slug' => $this->generateUniqueSlug($name),
                'description' => "Professional {$name} for heavy-duty applications",
            ];
        });
    }

    public function electricalComponent(): static
    {
        return $this->state(function () {
            $name = $this->faker->randomElement([
                'Circuit Breaker Fuses', 'Cartridge Fuses', 'Blade Fuses',
                'Toggle Switches', 'Rocker Switches', 'Push Button Switches',
                'Wire Connectors', 'Terminal Blocks', 'Junction Boxes',
                'GFCI Outlets', 'Standard Outlets', 'USB Outlets'
            ]);

            return [
                'name' => $name,
                'slug' => $this->generateUniqueSlug($name),
                'description' => "Reliable {$name} for electrical installations",
            ];
        });
    }

    public function safetyEquipment(): static
    {
        return $this->state(function () {
            $name = $this->faker->randomElement([
                'Work Gloves', 'Cut Resistant Gloves', 'Electrical Gloves',
                'Hard Hats', 'Bump Caps', 'Safety Helmets',
                'Safety Glasses', 'Goggles', 'Face Shields',
                'Knee Pads', 'Elbow Pads', 'Back Support Belts'
            ]);

            return [
                'name' => $name,
                'slug' => $this->generateUniqueSlug($name),
                'description' => "Essential {$name} for workplace safety",
            ];
        });
    }

    private function generateUniqueSlug(string $name): string
    {
        $baseSlug = Str::slug($name);
        $timestamp = now()->getTimestamp();
        $random = $this->faker->numberBetween(1000, 9999);
        return $baseSlug . '-' . $timestamp . '-' . $random;
    }

    public function withParent($parentId): static
    {
        return $this->state([
            'parent_id' => $parentId,
        ]);
    }
}
