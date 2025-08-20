<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class BrandFactory extends Factory
{
    private static array $toolBrands = [
        // Power Tool Brands
        'DeWalt', 'Milwaukee', 'Makita', 'Bosch', 'Ryobi', 'BLACK+DECKER',
        'Porter-Cable', 'Ridgid', 'Craftsman', 'Kobalt', 'Metabo', 'Festool',
        'Hilti', 'Snap-on', 'Ingersoll Rand', 'Stanley', 'Husky', 'Irwin',

        // Electrical Tool Brands
        'Klein Tools', 'Fluke', 'Greenlee', 'Ideal', 'Southwire', 'Wiha',
        'Wago', 'Leviton', 'Pass & Seymour', 'Legrand', 'Eaton', 'Square D',
        'Siemens', 'GE', 'Cutler Hammer', 'ABB', 'Schneider Electric',

        // Hand Tool Brands
        'Channellock', 'Knipex', 'Proto', 'Williams', 'Westward', 'Starrett',
        'Mitutoyo', 'Teng Tools', 'Bahco', 'Facom', 'Gedore', 'Hazet',

        // Safety Equipment Brands
        '3M', 'Honeywell', 'MSA', 'Bullard', 'Ansell', 'MCR Safety',
        'PIP', 'Pyramex', 'Uvex', 'Jackson Safety', 'North Safety'
    ];

    public function definition(): array
    {
        $name = $this->faker->randomElement(self::$toolBrands);

        return [
            'name' => $name,
            'slug' => Str::slug($name) . '-' . $this->faker->numberBetween(1000, 9999),
            'description' => $this->generateBrandDescription($name),
            'logo_url' => "https://picsum.photos/200/100?random=" . $this->faker->numberBetween(1, 1000),
        ];
    }

    public function powerToolBrand(): static
    {
        return $this->state(function () {
            $powerToolBrands = [
                'DeWalt', 'Milwaukee', 'Makita', 'Bosch', 'Ryobi', 'BLACK+DECKER',
                'Porter-Cable', 'Ridgid', 'Craftsman', 'Kobalt', 'Festool', 'Hilti'
            ];

            $name = $this->faker->randomElement($powerToolBrands);

            return [
                'name' => $name,
                'slug' => Str::slug($name) . '-' . $this->faker->numberBetween(1000, 9999),
                'description' => "Leading manufacturer of professional power tools and accessories for construction, electrical, and industrial applications.",
            ];
        });
    }

    public function electricalBrand(): static
    {
        return $this->state(function () {
            $electricalBrands = [
                'Klein Tools', 'Fluke', 'Greenlee', 'Ideal', 'Southwire', 'Wiha',
                'Wago', 'Leviton', 'Pass & Seymour', 'Legrand', 'Eaton', 'Square D'
            ];

            $name = $this->faker->randomElement($electricalBrands);

            return [
                'name' => $name,
                'slug' => Str::slug($name) . '-' . $this->faker->numberBetween(1000, 9999),
                'description' => "Trusted brand for electrical tools, components, and testing equipment used by electricians and electrical contractors.",
            ];
        });
    }

    public function handToolBrand(): static
    {
        return $this->state(function () {
            $handToolBrands = [
                'Channellock', 'Knipex', 'Proto', 'Williams', 'Westward', 'Starrett',
                'Stanley', 'Irwin', 'Craftsman', 'Snap-on', 'Bahco', 'Gedore'
            ];

            $name = $this->faker->randomElement($handToolBrands);

            return [
                'name' => $name,
                'slug' => Str::slug($name) . '-' . $this->faker->numberBetween(1000, 9999),
                'description' => "Premium hand tools designed for professional tradespeople and serious DIY enthusiasts.",
            ];
        });
    }

    public function safetyBrand(): static
    {
        return $this->state(function () {
            $safetyBrands = [
                '3M', 'Honeywell', 'MSA', 'Bullard', 'Ansell', 'MCR Safety',
                'PIP', 'Pyramex', 'Uvex', 'Jackson Safety'
            ];

            $name = $this->faker->randomElement($safetyBrands);

            return [
                'name' => $name,
                'slug' => Str::slug($name) . '-' . $this->faker->numberBetween(1000, 9999),
                'description' => "Industry leader in personal protective equipment and workplace safety solutions.",
            ];
        });
    }

    private function generateBrandDescription(string $brandName): string
    {
        $descriptions = [
            'DeWalt' => 'Professional power tools and accessories for construction and industrial applications.',
            'Milwaukee' => 'Heavy-duty power tools designed for the toughest jobsites.',
            'Klein Tools' => 'Professional electrician tools and equipment since 1857.',
            'Fluke' => 'World leader in electronic test tools and software.',
            '3M' => 'Innovation in safety and personal protective equipment.',
            'Stanley' => 'Trusted hand tools and storage solutions for professionals.',
        ];

        return $descriptions[$brandName] ?? "Leading manufacturer of professional tools and equipment for construction, electrical, and industrial applications.";
    }
}
