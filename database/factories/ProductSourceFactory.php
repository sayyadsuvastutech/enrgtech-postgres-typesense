<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ProductSource>
 */
class ProductSourceFactory extends Factory
{
    private static array $sources = ['dk', 'rs', 'ct', 'vp', 'et'];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $source = $this->faker->randomElement(self::$sources);
        $sourceProductId = $this->faker->numberBetween(100000, 999999);
        
        return [
            'product_id' => Product::factory(),
            'source_name' => $source,
            'source_product_id' => $sourceProductId,
            'source_url' => $this->generateSourceUrl($source, $sourceProductId),
        ];
    }

    private function generateSourceUrl(string $source, int $productId): string
    {
        $baseUrls = [
            'dk' => 'https://www.digikey.com/en/products/detail/',
            'rs' => 'https://www.rs-online.com/web/p/',
            'ct' => 'https://www.conrad.com/p/',
            'vp' => 'https://www.verical.com/pd/',
            'et' => 'https://www.electronicstalk.com/product/',
        ];
        
        return ($baseUrls[$source] ?? 'https://example.com/product/') . $productId;
    }

    public function forSource(string $sourceName): static
    {
        return $this->state([
            'source_name' => $sourceName,
        ]);
    }
}
