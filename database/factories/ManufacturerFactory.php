<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ManufacturerFactory extends Factory
{
    private static array $realManufacturers = [
        // Real Major Manufacturers
        'Stanley Black & Decker', 'Techtronic Industries (TTI)', 'Bosch Group', 
        'Makita Corporation', 'Hilti Corporation', 'Snap-on Incorporated',
        'Danaher Corporation', 'Emerson Electric', 'Honeywell International',
        '3M Company', 'Eaton Corporation', 'Schneider Electric', 'ABB Group',
        'Siemens AG', 'General Electric', 'Legrand SA', 'Leviton Manufacturing',
        'Southwire Company', 'Ideal Industries', 'Klein Tools Inc.',
        'Channellock Inc.', 'Proto Industrial Tools', 'Starrett Company',
        'MSA Safety', 'Bullard Company', 'Ansell Limited', 'MCR Safety',
    ];

    private static array $fictionalManufacturers = [
        'Precision Industrial Tools Ltd.', 'TechForce Manufacturing Corp.',
        'ElectroMax Components Inc.', 'ProTools International', 'SafeGuard Industries',
        'PowerCraft Solutions', 'Industrial Dynamics LLC', 'Apex Tool Works',
        'Thunder Bay Manufacturing', 'Stellar Components Co.', 'Titan Tool Corp.',
        'Vertex Manufacturing Group', 'Phoenix Industrial Systems', 'Alpha Tools Ltd.',
        'Summit Manufacturing Inc.', 'Eagle Industrial Products', 'Prime Tool Works',
        'NextGen Components', 'Fusion Industrial Corp.', 'Elite Manufacturing Co.',
    ];

    public function definition(): array
    {
        $isReal = $this->faker->boolean(60); // 60% chance of real manufacturer
        $manufacturers = $isReal ? self::$realManufacturers : self::$fictionalManufacturers;
        $name = $this->faker->randomElement($manufacturers);
        
        return [
            'name' => $name,
            'slug' => $this->generateUniqueSlug($name),
            'description' => $this->generateManufacturerDescription($name, $isReal),
            'logo' => $this->faker->optional()->imageUrl(200, 100, 'business'),
            'banner' => $this->faker->optional()->imageUrl(800, 200, 'business'),
            'meta_title' => $this->faker->optional()->sentence(6),
            'meta_description' => $this->faker->optional()->sentence(12),
            'is_pushed' => $this->faker->boolean(60),
            'website' => $this->generateWebsite($name),
            'total_reviews' => $this->faker->numberBetween(0, 1000),
            'products_count' => $this->faker->numberBetween(10, 500),
            'average_rating' => $this->faker->optional()->randomFloat(1, 3.0, 5.0),
            'status_id' => $this->faker->randomElement([1, 1, 1, 2]), // 75% active, 25% inactive
            'created_by' => $this->faker->optional()->numberBetween(1, 100),
            'updated_by' => $this->faker->optional()->numberBetween(1, 100),
        ];
    }

    public function realManufacturer(): static
    {
        return $this->state(function () {
            $name = $this->faker->randomElement(self::$realManufacturers);
            
            return [
                'name' => $name,
                'slug' => $this->generateUniqueSlug($name),
                'description' => $this->generateManufacturerDescription($name, true),
                'website' => $this->generateWebsite($name),
                'is_pushed' => true, // Real manufacturers are typically pushed
            ];
        });
    }

    public function fictionalManufacturer(): static
    {
        return $this->state(function () {
            $name = $this->faker->randomElement(self::$fictionalManufacturers);
            
            return [
                'name' => $name,
                'slug' => $this->generateUniqueSlug($name),
                'description' => $this->generateManufacturerDescription($name, false),
                'website' => $this->generateWebsite($name),
                'is_pushed' => $this->faker->boolean(30), // Fictional manufacturers less likely pushed
            ];
        });
    }

    private function generateManufacturerDescription(string $name, bool $isReal): string
    {
        $realDescriptions = [
            'Stanley Black & Decker' => 'Global provider of tools and storage, commercial electronic security, and engineered fastening systems.',
            'Techtronic Industries (TTI)' => 'World-class leader in design, manufacturing and marketing of power tools, outdoor power equipment and floor care appliances.',
            'Bosch Group' => 'Leading global supplier of technology and services in mobility solutions, industrial technology, consumer goods, and energy and building technology.',
            '3M Company' => 'Diversified technology company serving customers and communities with innovative products and services.',
            'Honeywell International' => 'Fortune 100 technology company that delivers industry-specific solutions including aerospace products and services.',
        ];

        if ($isReal && isset($realDescriptions[$name])) {
            return $realDescriptions[$name];
        }

        $templates = [
            'Leading manufacturer of professional-grade tools and equipment for industrial applications.',
            'Innovative company specializing in high-quality tools and components for construction and electrical industries.',
            'Premier manufacturer of precision tools and safety equipment for professional tradespeople.',
            'Global supplier of industrial tools, electrical components, and safety solutions.',
            'Established manufacturer known for reliable tools and equipment used by professionals worldwide.',
        ];

        return $this->faker->randomElement($templates);
    }

    private function generateWebsite(string $name): string
    {
        $domain = Str::slug(explode(' ', $name)[0]);
        return "https://www.{$domain}.com";
    }

    private function generateCountry(string $name, bool $isReal): string
    {
        if ($isReal) {
            $realCountries = ['United States', 'Germany', 'Japan', 'Switzerland', 'United Kingdom', 'France', 'Sweden'];
            return $this->faker->randomElement($realCountries);
        }

        return $this->faker->randomElement([
            'United States', 'Canada', 'Germany', 'United Kingdom', 'Japan', 
            'South Korea', 'Taiwan', 'Italy', 'France', 'Netherlands'
        ]);
    }

    private function generateHeadquarters(bool $isReal): string
    {
        if ($isReal) {
            $realHeadquarters = [
                'New Britain, CT', 'Hong Kong', 'Stuttgart, Germany', 'Schaan, Liechtenstein',
                'Maplewood, MN', 'Charlotte, NC', 'Rueil-Malmaison, France', 'Zurich, Switzerland'
            ];
            return $this->faker->randomElement($realHeadquarters);
        }

        $cities = [
            'Chicago, IL', 'Houston, TX', 'Phoenix, AZ', 'Denver, CO', 'Atlanta, GA',
            'Toronto, ON', 'Munich, Germany', 'London, UK', 'Tokyo, Japan', 'Seoul, South Korea'
        ];
        
        return $this->faker->randomElement($cities);
    }

    private function generateUniqueSlug(string $name): string
    {
        $baseSlug = Str::slug($name);
        $timestamp = now()->getTimestamp();
        $random = $this->faker->numberBetween(1000, 9999);
        return $baseSlug . '-' . $timestamp . '-' . $random;
    }

    public function withCountry(string $country): static
    {
        return $this->state(['country' => $country]);
    }

    public function established(int $year): static
    {
        return $this->state(['founded_year' => $year]);
    }
}
