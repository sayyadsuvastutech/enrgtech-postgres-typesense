<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ProductPrice>
 */
class ProductPriceFactory extends Factory
{
    private static array $sources = ['dk', 'rs', 'ct', 'vp', 'et'];
    private static array $currencies = ['USD', 'GBP', 'EUR', 'CAD'];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'source_name' => $this->faker->randomElement(self::$sources),
            'pricing_ranges' => $this->generatePricingRanges(),
            'currency' => $this->faker->randomElement(self::$currencies),
            'unit' => 'each',
        ];
    }

    private function generatePricingRanges(): array
    {
        $basePrice = $this->faker->randomFloat(2, 5, 500);
        
        return [
            [
                'min_quantity' => 1,
                'price' => round($basePrice, 2),
            ],
            [
                'min_quantity' => 100,
                'price' => round($basePrice * 0.85, 2),
            ],
            [
                'min_quantity' => 1000,
                'price' => round($basePrice * 0.70, 2),
            ],
        ];
    }

    public function forSource(string $sourceName): static
    {
        return $this->state([
            'source_name' => $sourceName,
        ]);
    }

    public function withCurrency(string $currency): static
    {
        return $this->state([
            'currency' => $currency,
        ]);
    }

    public function highValue(): static
    {
        return $this->state(function () {
            $basePrice = $this->faker->randomFloat(2, 500, 2000);
            
            $ranges = [
                [
                    'min_quantity' => 1,
                    'price' => round($basePrice, 2),
                ],
                [
                    'min_quantity' => 10,
                    'price' => round($basePrice * 0.90, 2),
                ],
                [
                    'min_quantity' => 100,
                    'price' => round($basePrice * 0.75, 2),
                ],
            ];

            return [
                'pricing_ranges' => $ranges,
                'currency' => 'USD',
                'unit' => 'each',
            ];
        });
    }
}
