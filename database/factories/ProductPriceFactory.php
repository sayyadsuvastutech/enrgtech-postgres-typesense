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
                'from' => 1,
                'to' => 99,
                'price' => round($basePrice, 2),
                'currency' => 'USD',
                'effective_date' => now()->toDateString(),
                'expires_date' => now()->addMonths(6)->toDateString(),
            ],
            [
                'from' => 100,
                'to' => 999,
                'price' => round($basePrice * 0.85, 2),
                'currency' => 'USD',
                'effective_date' => now()->toDateString(),
                'expires_date' => now()->addMonths(6)->toDateString(),
            ],
            [
                'from' => 1000,
                'to' => null, // No upper limit
                'price' => round($basePrice * 0.70, 2),
                'currency' => 'USD',
                'effective_date' => now()->toDateString(),
                'expires_date' => now()->addMonths(6)->toDateString(),
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
                    'from' => 1,
                    'to' => 9,
                    'price' => round($basePrice, 2),
                    'currency' => 'USD',
                    'effective_date' => now()->toDateString(),
                    'expires_date' => now()->addMonths(6)->toDateString(),
                ],
                [
                    'from' => 10,
                    'to' => 99,
                    'price' => round($basePrice * 0.90, 2),
                    'currency' => 'USD',
                    'effective_date' => now()->toDateString(),
                    'expires_date' => now()->addMonths(6)->toDateString(),
                ],
                [
                    'from' => 100,
                    'to' => null,
                    'price' => round($basePrice * 0.75, 2),
                    'currency' => 'USD',
                    'effective_date' => now()->toDateString(),
                    'expires_date' => now()->addMonths(6)->toDateString(),
                ],
            ];

            return [
                'pricing_ranges' => $ranges,
                'currency' => 'USD',
                'unit' => 'each',
            ];
        });
    }

    public function energyTechnology(): static
    {
        return $this->state(function () {
            $basePrice = $this->faker->randomFloat(2, 100, 5000);
            $unit = $this->faker->randomElement(['each', 'kW', 'kWh', 'MW', 'panel', 'system']);

            $ranges = [
                [
                    'from' => 1,
                    'to' => 5,
                    'price' => round($basePrice, 2),
                    'currency' => 'USD',
                    'effective_date' => now()->toDateString(),
                    'expires_date' => now()->addYear()->toDateString(),
                ],
                [
                    'from' => 6,
                    'to' => 20,
                    'price' => round($basePrice * 0.92, 2),
                    'currency' => 'USD',
                    'effective_date' => now()->toDateString(),
                    'expires_date' => now()->addYear()->toDateString(),
                ],
                [
                    'from' => 21,
                    'to' => null,
                    'price' => round($basePrice * 0.85, 2),
                    'currency' => 'USD',
                    'effective_date' => now()->toDateString(),
                    'expires_date' => now()->addYear()->toDateString(),
                ],
            ];

            return [
                'pricing_ranges' => $ranges,
                'currency' => 'USD',
                'unit' => $unit,
            ];
        });
    }
}
