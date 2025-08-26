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
                'url' => $this->generateOriginalUrl(),
                'path' => $this->generateImagePath(),
                'original' => $this->generateOriginalUrl(),
                'thumbnail' => $this->generateThumbnailUrl(),
                'is_primary' => $i === 0, // First image is primary
                'is_pushed_to_s3' => $this->faker->boolean(70),
                'reference_url' => $this->faker->optional()->url(),
                'alt_text' => $this->generateAltText(),
                'caption' => $this->faker->optional()->sentence(),
                'dimensions' => [
                    'width' => $this->faker->randomElement([400, 600, 800, 1024]),
                    'height' => $this->faker->randomElement([300, 400, 600, 768]),
                ],
                'file_size' => $this->faker->numberBetween(50000, 500000), // bytes
                'format' => 'jpg',
                'created_at' => now()->toISOString(),
                'source_url' => $this->faker->optional()->url(),
            ];
        }

        // Return as direct array of image objects
        return $images;
    }

    private function generateImagePath(): string
    {
        return 'full/'.$this->faker->sha1().'.jpg';
    }

    private function generateOriginalUrl(): string
    {
        $width = $this->faker->randomElement([800, 1024, 1200]);
        $height = $this->faker->randomElement([600, 768, 900]);

        return "https://picsum.photos/{$width}/{$height}?random=".$this->faker->numberBetween(1, 10000);
    }

    private function generateThumbnailUrl(): string
    {
        $size = $this->faker->randomElement([150, 200, 300]);

        return "https://picsum.photos/{$size}/{$size}?random=".$this->faker->numberBetween(1, 10000);
    }

    private function generateAltText(): string
    {
        $descriptions = [
            'Product main view',
            'Product detail shot',
            'Product in use',
            'Product packaging',
            'Product specifications',
            'Product installation view',
            'Product front view',
            'Product side view',
            'Product back view',
            'Product close-up detail',
        ];

        return $this->faker->randomElement($descriptions);
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
                    [
                        'url' => $this->generateOriginalUrl(),
                        'path' => $this->generateImagePath(),
                        'original' => $this->generateOriginalUrl(),
                        'thumbnail' => $this->generateThumbnailUrl(),
                        'is_primary' => true,
                        'is_pushed_to_s3' => $this->faker->boolean(80),
                        'reference_url' => $this->faker->optional()->url(),
                        'alt_text' => 'Primary product image',
                        'caption' => $this->faker->optional()->sentence(),
                        'dimensions' => [
                            'width' => 800,
                            'height' => 600,
                        ],
                        'file_size' => $this->faker->numberBetween(100000, 300000),
                        'format' => 'jpg',
                        'created_at' => now()->toISOString(),
                        'source_url' => $this->faker->optional()->url(),
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
            foreach ($imagesData as $key => &$image) {
                $image['is_primary'] = $key === 0;
            }

            return [
                'images' => $imagesData,
            ];
        });
    }
}
