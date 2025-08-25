<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ProductAttribute>
 */
class ProductAttributeFactory extends Factory
{
    private static array $sources = ['dk', 'rs', 'ct', 'vp', 'et'];

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
            'attributes_data' => $this->generateAttributesData(),
        ];
    }

    private function generateAttributesData(): array
    {
        $attributes = $this->generateAttributes();
        $filterAttributes = array_slice($attributes, 0, $this->faker->numberBetween(1, 3));
        
        return [
            'attributes' => $attributes,
            'filter_attributes' => $filterAttributes,
        ];
    }

    private function generateAttributes(): array
    {
        $commonAttributes = [
            ['name' => 'Material', 'value' => $this->faker->randomElement(['Steel', 'Aluminum', 'Plastic', 'Ceramic'])],
            ['name' => 'Color', 'value' => $this->faker->colorName()],
            ['name' => 'Weight', 'value' => $this->faker->randomFloat(2, 0.1, 50) . ' lbs'],
            ['name' => 'Dimensions', 'value' => $this->faker->randomFloat(1, 1, 20) . '" x ' . $this->faker->randomFloat(1, 1, 20) . '"'],
            ['name' => 'Voltage', 'value' => $this->faker->randomElement(['12V', '18V', '20V', '120V', '240V'])],
            ['name' => 'Power', 'value' => $this->faker->numberBetween(100, 2000) . 'W'],
            ['name' => 'Operating Temperature', 'value' => '-20°C to +60°C'],
            ['name' => 'Certification', 'value' => $this->faker->randomElement(['UL Listed', 'CE Marked', 'RoHS Compliant'])],
        ];

        return $this->faker->randomElements($commonAttributes, $this->faker->numberBetween(3, 6));
    }

    public function forSource(string $sourceName): static
    {
        return $this->state([
            'source_name' => $sourceName,
        ]);
    }

    public function electricalComponent(): static
    {
        return $this->state(function () {
            $attributes = [
                ['name' => 'Voltage Rating', 'value' => $this->faker->randomElement(['125V', '250V', '600V'])],
                ['name' => 'Current Rating', 'value' => $this->faker->randomElement(['5A', '10A', '15A', '20A'])],
                ['name' => 'Material', 'value' => 'Ceramic'],
                ['name' => 'Type', 'value' => $this->faker->randomElement(['fast_blow', 'slow_blow', 'time_delay'])],
                ['name' => 'Mounting', 'value' => $this->faker->randomElement(['panel_mount', 'fuse_block'])],
            ];
            
            return [
                'attributes_data' => [
                    'attributes' => $attributes,
                    'filter_attributes' => array_slice($attributes, 0, 3),
                ],
            ];
        });
    }
}
