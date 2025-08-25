<?php

namespace Database\Factories;

use App\Models\Manufacturer;
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
            'manufacturer_id' => Manufacturer::factory(),
            'name' => $name,
            'slug' => $this->generateUniqueSlug($name),
            'logo' => $this->faker->optional()->imageUrl(200, 100, 'business'),
            'banner' => $this->faker->optional()->imageUrl(800, 200, 'business'),
            'description' => $this->generateBrandDescription($name),
            'website' => $this->faker->optional()->url(),
            'type' => $this->faker->optional()->randomElement(['Tools', 'Electronics', 'Safety', 'Industrial']),
            'size' => $this->faker->optional()->randomElement(['Small', 'Medium', 'Large', 'Enterprise']),
            'location' => $this->faker->optional()->city(),
            'founded' => $this->faker->optional()->year(),
            'specialties' => $this->faker->optional()->sentence(4),
            'meta_title' => $this->faker->optional()->sentence(6),
            'meta_description' => $this->faker->optional()->sentence(12),
            'popular_items' => $this->faker->optional()->sentence(8),
            'new_items' => $this->faker->optional()->sentence(8),
            'domain_id' => $this->faker->optional()->numberBetween(1, 10),
            'status_id' => $this->faker->randomElement([1, 1, 1, 2]), // 75% active, 25% inactive
            'created_by' => $this->faker->optional()->numberBetween(1, 100),
            'updated_by' => $this->faker->optional()->numberBetween(1, 100),
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
                'slug' => $this->generateUniqueSlug($name),
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
                'slug' => $this->generateUniqueSlug($name),
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
                'slug' => $this->generateUniqueSlug($name),
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
                'slug' => $this->generateUniqueSlug($name),
                'description' => "Industry leader in personal protective equipment and workplace safety solutions.",
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
