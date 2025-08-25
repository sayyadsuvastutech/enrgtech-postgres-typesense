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
            'pricing_data' => $this->generatePricingData(),
        ];
    }

    private function generatePricingData(): array
    {
        $currency = $this->faker->randomElement(self::$currencies);
        $basePrice = $this->faker->randomFloat(2, 5, 500);
        
        $ranges = [
            [
                'from' => 1,
                'to' => 99,
                'price' => round($basePrice, 2),
            ],
            [
                'from' => 100,
                'to' => 999,
                'price' => round($basePrice * 0.85, 2),
            ],
            [
                'from' => 1000,
                'to' => null,
                'price' => round($basePrice * 0.70, 2),
            ],
        ];

        return [
            'currency' => $currency,
            'ranges' => $ranges,
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
        return $this->state(function () use ($currency) {
            $pricingData = $this->generatePricingData();
            $pricingData['currency'] = $currency;
            
            return [
                'pricing_data' => $pricingData,
            ];
        });
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
                ],
                [
                    'from' => 10,
                    'to' => 99,
                    'price' => round($basePrice * 0.90, 2),
                ],
                [
                    'from' => 100,
                    'to' => null,
                    'price' => round($basePrice * 0.75, 2),
                ],
            ];

            return [
                'pricing_data' => [
                    'currency' => 'USD',
                    'ranges' => $ranges,
                ],
            ];
        });
    }
}
