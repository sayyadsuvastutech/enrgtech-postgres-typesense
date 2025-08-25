<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ProductDocument>
 */
class ProductDocumentFactory extends Factory
{
    private static array $sources = ['dk', 'rs', 'ct', 'vp', 'et'];
    private static array $documentTypes = [
        'Datasheet',
        'User Manual',
        'Installation Guide',
        'Technical Specification',
        'Product Guide',
        'Safety Information',
        'Compliance Certificate',
        'Application Note',
    ];
    private static array $folders = [
        'Datasheets',
        'Manuals',
        'Guides',
        'Specifications',
        'Certificates',
        'Application Notes',
    ];

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
            'documents_data' => $this->generateDocumentsData(),
        ];
    }

    private function generateDocumentsData(): array
    {
        $documentCount = $this->faker->numberBetween(2, 5);
        $documents = [];
        
        for ($i = 0; $i < $documentCount; $i++) {
            $docType = $this->faker->randomElement(self::$documentTypes);
            $documents[] = [
                'name' => $this->generateDocumentName($docType),
                'folder' => $this->faker->randomElement(self::$folders),
                'url' => $this->generateDocumentUrl($docType),
                'key' => $this->generateDocumentKey($docType),
                'is_primary' => $i === 0 && $docType === 'Datasheet', // Datasheet as primary if first
            ];
        }

        return [
            'documents' => $documents,
        ];
    }

    private function generateDocumentName(string $docType): string
    {
        $productCode = $this->faker->bothify('??####');
        return "{$productCode} {$docType}";
    }

    private function generateDocumentUrl(string $docType): string
    {
        $domain = $this->faker->randomElement([
            'www.sitime.com',
            'www.analog.com',
            'www.ti.com',
            'www.microchip.com',
            'www.nxp.com',
        ]);
        
        $filename = Str::slug($docType) . '-' . $this->faker->bothify('########') . '.pdf';
        return "https://{$domain}/documents/{$filename}";
    }

    private function generateDocumentKey(string $docType): string
    {
        return Str::slug($docType) . '-' . $this->faker->bothify('########');
    }

    public function forSource(string $sourceName): static
    {
        return $this->state([
            'source_name' => $sourceName,
        ]);
    }

    public function withDatasheet(): static
    {
        return $this->state(function () {
            $documents = [
                [
                    'name' => $this->generateDocumentName('Datasheet'),
                    'folder' => 'Datasheets',
                    'url' => $this->generateDocumentUrl('Datasheet'),
                    'key' => $this->generateDocumentKey('Datasheet'),
                    'is_primary' => true,
                ],
            ];
            
            // Add additional documents
            $additionalDocs = $this->faker->numberBetween(1, 3);
            for ($i = 0; $i < $additionalDocs; $i++) {
                $docType = $this->faker->randomElement(array_diff(self::$documentTypes, ['Datasheet']));
                $documents[] = [
                    'name' => $this->generateDocumentName($docType),
                    'folder' => $this->faker->randomElement(self::$folders),
                    'url' => $this->generateDocumentUrl($docType),
                    'key' => $this->generateDocumentKey($docType),
                    'is_primary' => false,
                ];
            }
            
            return [
                'documents_data' => [
                    'documents' => $documents,
                ],
            ];
        });
    }
}
