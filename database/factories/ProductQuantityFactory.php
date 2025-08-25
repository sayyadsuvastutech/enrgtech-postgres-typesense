<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ProductQuantity>
 */
class ProductQuantityFactory extends Factory
{
    private static array $sources = ['dk', 'rs', 'ct', 'vp', 'et'];
    private static array $units = ['pieces', 'units', 'each', 'pcs', 'items'];
    private static array $availabilityStatuses = ['in_stock', 'limited_stock', 'out_of_stock', 'backorder', 'discontinued'];

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
            'quantity_data' => $this->generateQuantityData(),
        ];
    }

    private function generateQuantityData(): array
    {
        $quantity = $this->faker->numberBetween(0, 10000);
        $availabilityStatus = $quantity > 0 
            ? $this->faker->randomElement(['in_stock', 'limited_stock'])
            : $this->faker->randomElement(['out_of_stock', 'backorder']);

        return [
            'quantity' => $quantity,
            'unit' => $this->faker->randomElement(self::$units),
            'last_updated' => $this->faker->dateTimeBetween('-30 days', 'now')->format('Y-m-d'),
            'availability_status' => $availabilityStatus,
        ];
    }

    public function forSource(string $sourceName): static
    {
        return $this->state([
            'source_name' => $sourceName,
        ]);
    }

    public function inStock(): static
    {
        return $this->state(function () {
            return [
                'quantity_data' => [
                    'quantity' => $this->faker->numberBetween(100, 5000),
                    'unit' => $this->faker->randomElement(self::$units),
                    'last_updated' => $this->faker->dateTimeBetween('-7 days', 'now')->format('Y-m-d'),
                    'availability_status' => 'in_stock',
                ],
            ];
        });
    }

    public function outOfStock(): static
    {
        return $this->state(function () {
            return [
                'quantity_data' => [
                    'quantity' => 0,
                    'unit' => $this->faker->randomElement(self::$units),
                    'last_updated' => $this->faker->dateTimeBetween('-3 days', 'now')->format('Y-m-d'),
                    'availability_status' => 'out_of_stock',
                ],
            ];
        });
    }

    public function limitedStock(): static
    {
        return $this->state(function () {
            return [
                'quantity_data' => [
                    'quantity' => $this->faker->numberBetween(1, 50),
                    'unit' => $this->faker->randomElement(self::$units),
                    'last_updated' => $this->faker->dateTimeBetween('-2 days', 'now')->format('Y-m-d'),
                    'availability_status' => 'limited_stock',
                ],
            ];
        });
    }
}
