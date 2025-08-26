<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ProductImage>
 */
class ProductImageFactory extends Factory
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
            'images' => $this->generateImagesData(),
        ];
    }

    private function generateImagesData(): array
    {
        $imageCount = $this->faker->numberBetween(3, 6);
        $images = [];
        
        for ($i = 0; $i < $imageCount; $i++) {
            $images[] = [
                'path' => $this->generateImagePath(),
                'original' => $this->generateOriginalUrl(),
                'is_primary' => $i === 0, // First image is primary
                'is_pushed_to_s3' => $this->faker->boolean(70),
                'reference_url' => $this->faker->optional()->url(),
            ];
        }

        return [
            'images' => $images,
        ];
    }

    private function generateImagePath(): string
    {
        return 'full/' . $this->faker->sha1() . '.jpg';
    }

    private function generateOriginalUrl(): string
    {
        $width = $this->faker->randomElement([400, 600, 800]);
        $height = $this->faker->randomElement([300, 400, 600]);
        return "https://picsum.photos/{$width}/{$height}?random=" . $this->faker->numberBetween(1, 10000);
    }

    public function forSource(string $sourceName): static
    {
        return $this->state([
            'source_name' => $sourceName,
        ]);
    }

    public function singleImage(): static
    {
        return $this->state(function () {
            return [
                'images' => [
                    'images' => [
                        [
                            'path' => $this->generateImagePath(),
                            'original' => $this->generateOriginalUrl(),
                            'is_primary' => true,
                            'is_pushed_to_s3' => $this->faker->boolean(80),
                            'reference_url' => $this->faker->optional()->url(),
                        ],
                    ],
                ],
            ];
        });
    }

    public function withPrimaryImage(): static
    {
        return $this->state(function () {
            $imagesData = $this->generateImagesData();
            // Ensure only one primary image
            foreach ($imagesData['images'] as $key => &$image) {
                $image['is_primary'] = $key === 0;
            }
            
            return [
                'images' => $imagesData,
            ];
        });
    }
}
