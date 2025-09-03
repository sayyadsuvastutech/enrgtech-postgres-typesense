<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Laravel\Scout\Builder as ScoutBuilder;
use Typesense\Client;
use Typesense\Collection;

class TypesenseEcommerceSearchService
{
    private Client $typesenseClient;

    private const CACHE_TTL = 300; // 5 minutes
    private const FACET_CACHE_TTL = 900; // 15 minutes

    public function __construct()
    {
        $this->typesenseClient = new Client([
            'api_key' => config('scout.typesense.client-settings.api_key'),
            'nodes' => [
                [
                    'host' => config('scout.typesense.client-settings.nodes.0.host'),
                    'port' => config('scout.typesense.client-settings.nodes.0.port'),
                    'protocol' => config('scout.typesense.client-settings.nodes.0.protocol'),
                ]
            ],
            'connection_timeout_seconds' => 5,
        ]);
    }

    public function search(array $params): array
    {
        try {
            $searchParams = $this->buildEcommerceSearchParams($params);

            $collectionName = $this->getCollectionName();
            $searchResults = $this->typesenseClient->collections[$collectionName]->documents->search($searchParams);

            return $this->formatEcommerceResults($searchResults, $params);

        } catch (\Exception $e) {
            Log::error('Typesense ecommerce search error', [
                'error' => $e->getMessage(),
                'params' => $params
            ]);

            return $this->getEmptyResults();
        }
    }

    private function buildEcommerceSearchParams(array $params): array
    {
        $searchTerm = $params['q'] ?? '*';

        $searchParams = [
            'q' => $searchTerm,
            'query_by' => 'name,title,pnum,mf_pnum,description,category_name,brand_name,manufacturer_name,searchable_attributes,breadcrumb',
            'query_by_weights' => '10,10,8,8,5,4,4,4,3,2',
            'sort_by' => $this->getEcommerceSortBy($params),
            'facet_by' => 'category_name,brand_name,manufacturer_name,in_stock,is_rohs_compliant,sources',
            'max_facet_values' => 100,
            'per_page' => $params['per_page'] ?? 24,
            'page' => $params['page'] ?? 1,
            'highlight_fields' => 'name,title,description',
            'highlight_start_tag' => '<mark class="bg-yellow-200">',
            'highlight_end_tag' => '</mark>',
            'snippet_threshold' => 30,
            'num_typos' => '2,2,1,1,0,0,0,0,0,0',
            'prefix' => 'true,true,true,true,false,false,false,false,false,false',
            'infix' => 'off,off,fallback,fallback,fallback,fallback,fallback,fallback,fallback,fallback',
            'drop_tokens_threshold' => 1,
            'typo_tokens_threshold' => 1,
        ];

        // Apply filters
        $filters = $this->buildEcommerceFilters($params);
        if (!empty($filters)) {
            $searchParams['filter_by'] = implode(' && ', $filters);
        }

        return $searchParams;
    }

    private function buildEcommerceFilters(array $params): array
    {
        $filters = [];

        // Category filter
        if (!empty($params['category'])) {
            if (is_array($params['category'])) {
                $categories = array_filter($params['category']);
                if (!empty($categories)) {
                    $categoryList = implode(',', array_map(fn($cat) => "'$cat'", $categories));
                    $filters[] = "category_name:[$categoryList]";
                }
            } else {
                $filters[] = "category_name:='{$params['category']}'";
            }
        }

        // Brand filter
        if (!empty($params['brand'])) {
            if (is_array($params['brand'])) {
                $brands = array_filter($params['brand']);
                if (!empty($brands)) {
                    $brandList = implode(',', array_map(fn($brand) => "'$brand'", $brands));
                    $filters[] = "brand_name:[$brandList]";
                }
            } else {
                $filters[] = "brand_name:='{$params['brand']}'";
            }
        }

        // Skip price filters for now

        // Stock filter
        if (isset($params['in_stock']) && $params['in_stock'] !== '') {
            $stockValue = $params['in_stock'] === 'true' || $params['in_stock'] === true ? 'true' : 'false';
            $filters[] = "in_stock:={$stockValue}";
        }

        // Manufacturer filter
        if (!empty($params['manufacturer'])) {
            if (is_array($params['manufacturer'])) {
                $manufacturers = array_filter($params['manufacturer']);
                if (!empty($manufacturers)) {
                    $manufacturerList = implode(',', array_map(fn($mfr) => "'$mfr'", $manufacturers));
                    $filters[] = "manufacturer_name:[$manufacturerList]";
                }
            } else {
                $filters[] = "manufacturer_name:='{$params['manufacturer']}'";
            }
        }

        // Attributes filter (assuming attributes are stored as key-value pairs)
        if (!empty($params['attributes']) && is_array($params['attributes'])) {
            foreach ($params['attributes'] as $key => $value) {
                if (!empty($value) && !empty($key)) {
                    $filters[] = "attributes:='{$key}:{$value}'";
                }
            }
        }

        return $filters;
    }

    private function getEcommerceSortBy(array $params): string
    {
        $sortBy = $params['sort_by'] ?? 'relevance';
        $sortOrder = $params['sort_order'] ?? 'desc';

        switch ($sortBy) {
            case 'price_asc':
                return 'price:asc';
            case 'price_desc':
                return 'price:desc';
            case 'name_asc':
                return 'name:asc';
            case 'name_desc':
                return 'name:desc';
            case 'newest':
                return 'created_at:desc';
            case 'rating':
                return 'average_rating:desc';
            case 'popularity':
                return 'view_count:desc,created_at:desc';
            case 'relevance':
            default:
                return '_text_match:desc,created_at:desc';
        }
    }

    private function formatEcommerceResults(array $searchResults, array $params): array
    {
        $products = [];
        $facets = [];

        // Extract product data
        if (!empty($searchResults['hits'])) {
            foreach ($searchResults['hits'] as $hit) {
                $document = $hit['document'];
                $highlights = $hit['highlights'] ?? [];

                $products[] = [
                    'id' => $document['id'],
                    'name' => $document['name'] ?? '',
                    'title' => $document['title'] ?? '',
                    'description' => $document['description'] ?? '',
                    'price' => $document['price'] ?? 0,
                    'category_name' => $document['category_name'] ?? '',
                    'brand_name' => $document['brand_name'] ?? '',
                    'manufacturer_name' => $document['manufacturer_name'] ?? '',
                    'pnum' => $document['pnum'] ?? '',
                    'image_url' => $document['image_url'] ?? '/images/place_holder.svg',
                    'in_stock' => $document['in_stock'] ?? false,
                    'stock_quantity' => $document['stock_quantity'] ?? 0,
                    'highlights' => $highlights,
                    'text_match_info' => $hit['text_match_info'] ?? []
                ];
            }
        }

        // Extract facets
        if (!empty($searchResults['facet_counts'])) {
            foreach ($searchResults['facet_counts'] as $facet) {
                $facetName = $facet['field_name'];
                $facetCounts = [];

                foreach ($facet['counts'] as $count) {
                    $facetCounts[] = [
                        'value' => $count['value'],
                        'count' => $count['count'],
                        'highlighted' => $count['highlighted'] ?? $count['value']
                    ];
                }

                $facets[$facetName] = $facetCounts;
            }
        }

        return [
            'products' => $products,
            'facets' => $facets,
            'pagination' => [
                'current_page' => $params['page'] ?? 1,
                'per_page' => $params['per_page'] ?? 24,
                'total' => $searchResults['found'] ?? 0,
                'total_pages' => ceil(($searchResults['found'] ?? 0) / ($params['per_page'] ?? 24))
            ],
            'meta' => [
                'search_time_ms' => $searchResults['search_time_ms'] ?? 0,
                'search_cutoff' => $searchResults['search_cutoff'] ?? false,
                'total_found' => $searchResults['found'] ?? 0
            ]
        ];
    }

    public function getFacetsOnly(array $params): array
    {
        $cacheKey = 'typesense_ecommerce_facets_' . md5(serialize($params));

        return Cache::remember($cacheKey, self::FACET_CACHE_TTL, function () use ($params) {
            try {
                $searchParams = $this->buildEcommerceSearchParams(array_merge($params, ['per_page' => 0]));

                $collectionName = $this->getCollectionName();
                $searchResults = $this->typesenseClient->collections[$collectionName]->documents->search($searchParams);

                $facets = [];
                if (!empty($searchResults['facet_counts'])) {
                    foreach ($searchResults['facet_counts'] as $facet) {
                        $facetName = $facet['field_name'];
                        $facetCounts = [];

                        foreach ($facet['counts'] as $count) {
                            $facetCounts[] = [
                                'value' => $count['value'],
                                'count' => $count['count'],
                                'highlighted' => $count['highlighted'] ?? $count['value']
                            ];
                        }

                        $facets[$facetName] = $facetCounts;
                    }
                }

                return $facets;

            } catch (\Exception $e) {
                Log::error('Typesense facets error', ['error' => $e->getMessage()]);
                return [];
            }
        });
    }

    public function autocomplete(string $query, int $limit = 10): array
    {
        try {
            $searchParams = [
                'q' => $query,
                'query_by' => 'name,title,pnum,brand_name',
                'query_by_weights' => '10,8,6,3',
                'prefix' => 'true',
                'per_page' => $limit,
                'facet_by' => 'category_name,brand_name',
                'max_facet_values' => 5
            ];

            $collectionName = $this->getCollectionName();
            $searchResults = $this->typesenseClient->collections[$collectionName]->documents->search($searchParams);

            $suggestions = [];
            if (!empty($searchResults['hits'])) {
                foreach ($searchResults['hits'] as $hit) {
                    $document = $hit['document'];
                    $suggestions[] = [
                        'id' => $document['id'],
                        'text' => $document['name'],
                        'category' => $document['category_name'] ?? '',
                        'brand' => $document['brand_name'] ?? '',
                        'image' => $document['image_url'] ?? '/images/place_holder.svg'
                    ];
                }
            }

            return $suggestions;

        } catch (\Exception $e) {
            Log::error('Typesense autocomplete error', ['error' => $e->getMessage()]);
            return [];
        }
    }

    private function getCollectionName(): string
    {
        $environment = config('app.env', 'local');
        $indexes = config('typesense.indexes.products', []);

        return $indexes[$environment] ?? 'enrgtech_products_local_v1';
    }

    private function getEmptyResults(): array
    {
        return [
            'products' => [],
            'facets' => [],
            'pagination' => [
                'current_page' => 1,
                'per_page' => 24,
                'total' => 0,
                'total_pages' => 0
            ],
            'meta' => [
                'search_time_ms' => 0,
                'search_cutoff' => false,
                'total_found' => 0
            ]
        ];
    }

    public function getPriceRanges(): array
    {
        return [
            ['min' => 0, 'max' => 10, 'label' => '$0 - $10', 'value' => '0-10'],
            ['min' => 10, 'max' => 50, 'label' => '$10 - $50', 'value' => '10-50'],
            ['min' => 50, 'max' => 100, 'label' => '$50 - $100', 'value' => '50-100'],
            ['min' => 100, 'max' => 250, 'label' => '$100 - $250', 'value' => '100-250'],
            ['min' => 250, 'max' => 500, 'label' => '$250 - $500', 'value' => '250-500'],
            ['min' => 500, 'max' => 1000, 'label' => '$500 - $1,000', 'value' => '500-1000'],
            ['min' => 1000, 'max' => null, 'label' => '$1,000+', 'value' => '1000+'],
        ];
    }

    public function addPriceRangeToDocument(array $document): array
    {
        $price = $document['price'] ?? 0;
        $priceRanges = $this->getPriceRanges();

        foreach ($priceRanges as $range) {
            if ($price >= $range['min'] && ($range['max'] === null || $price < $range['max'])) {
                $document['price_range'] = $range['value'];
                break;
            }
        }

        return $document;
    }

    public function updateProductWithPriceRange(Product $product): void
    {
        try {
            $collectionName = $this->getCollectionName();
            $document = $product->toSearchableArray();
            $document = $this->addPriceRangeToDocument($document);

            $this->typesenseClient->collections[$collectionName]->documents->upsert($document);
        } catch (\Exception $e) {
            Log::error('Failed to update product with price range', [
                'product_id' => $product->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function clearCache(): void
    {
        $pattern = 'typesense_ecommerce_*';

        // Note: This is a simple cache clear - in production you might want to use cache tags
        Cache::flush();
    }
}
