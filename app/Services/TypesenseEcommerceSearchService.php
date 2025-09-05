<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Typesense\Client;

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
                ],
            ],
            'connection_timeout_seconds' => 5,
        ]);
    }

    public function search(array $params): array
    {
        $startTime = microtime(true);
        $searchId = uniqid('search_');

        Log::info('Typesense Search Started', [
            'search_id' => $searchId,
            'params' => $params,
            'ai_search_enabled' => $params['ai_search'] ?? false,
            'query' => $params['q'] ?? '',
            'timestamp' => now()->toISOString()
        ]);

        try {
            // Check if AI search is enabled
            $isAiSearch = $params['ai_search'] ?? false;

            if ($isAiSearch && ! empty($params['q']) && $params['q'] !== '*') {
                Log::info('Performing AI (Hybrid) Search', [
                    'search_id' => $searchId,
                    'query' => $params['q']
                ]);
                $result = $this->hybridSearch($params);

                Log::info('AI Search Completed', [
                    'search_id' => $searchId,
                    'results_count' => count($result['products'] ?? []),
                    'total_found' => $result['pagination']['total'] ?? 0,
                    'search_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                    'facets_available' => array_keys($result['facets'] ?? [])
                ]);

                return $result;
            }

            Log::info('Performing Simple (Keyword) Search', [
                'search_id' => $searchId,
                'query' => $params['q'] ?? ''
            ]);

            $searchParams = $this->buildEcommerceSearchParams($params);

            Log::debug('Simple Search Parameters', [
                'search_id' => $searchId,
                'typesense_params' => $searchParams
            ]);

            $collectionName = $this->getCollectionName();
            $searchResults = $this->typesenseClient->collections[$collectionName]->documents->search($searchParams);

            Log::debug('Raw Typesense Response', [
                'search_id' => $searchId,
                'found' => $searchResults['found'] ?? 0,
                'search_time_ms' => $searchResults['search_time_ms'] ?? 0,
                'hits_count' => count($searchResults['hits'] ?? [])
            ]);

            $formattedResults = $this->formatEcommerceResults($searchResults, $params);

            Log::info('Simple Search Completed', [
                'search_id' => $searchId,
                'results_count' => count($formattedResults['products'] ?? []),
                'total_found' => $formattedResults['pagination']['total'] ?? 0,
                'search_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'facets_available' => array_keys($formattedResults['facets'] ?? [])
            ]);

            return $formattedResults;

        } catch (\Exception $e) {
            Log::error('Typesense Search Error', [
                'search_id' => $searchId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'params' => $params,
                'search_time_ms' => round((microtime(true) - $startTime) * 1000, 2)
            ]);

            return $this->getEmptyResults();
        }
    }

    /**
     * Performs hybrid search combining semantic (AI) and traditional keyword search
     */
    public function hybridSearch(array $params): array
    {
        $hybridStartTime = microtime(true);
        $hybridSearchId = uniqid('hybrid_');

        Log::info('Hybrid Search Process Started', [
            'hybrid_search_id' => $hybridSearchId,
            'query' => $params['q'] ?? ''
        ]);

        try {
            $query = $params['q'] ?? '';
            if (empty($query) || $query === '*') {
                Log::info('Empty query in hybrid search, falling back to regular search', [
                    'hybrid_search_id' => $hybridSearchId
                ]);
                return $this->search($params); // Fall back to regular search
            }

            // Get embedding for the search query
            $embeddingStartTime = microtime(true);
            $queryEmbedding = $this->getQueryEmbedding($query);

            $embeddingTime = round((microtime(true) - $embeddingStartTime) * 1000, 2);

            if (! $queryEmbedding) {
                Log::warning('Failed to get embedding for query, falling back to regular search', [
                    'hybrid_search_id' => $hybridSearchId,
                    'query' => $query,
                    'embedding_time_ms' => $embeddingTime
                ]);

                return $this->search(array_merge($params, ['ai_search' => false]));
            }

            Log::info('Query embedding obtained successfully', [
                'hybrid_search_id' => $hybridSearchId,
                'embedding_time_ms' => $embeddingTime,
                'embedding_dimensions' => count($queryEmbedding)
            ]);

            // Perform vector search with Typesense
            $vectorSearchParams = $this->buildVectorSearchParams($params, $queryEmbedding);
            $keywordSearchParams = $this->buildEcommerceSearchParams($params);

            Log::debug('Hybrid Search Parameters', [
                'hybrid_search_id' => $hybridSearchId,
                'vector_params' => $vectorSearchParams,
                'keyword_params' => $keywordSearchParams
            ]);

            $collectionName = $this->getCollectionName();

            // Execute both searches - use multi_search for vector search to handle large payloads
            $vectorSearchStart = microtime(true);
            $vectorResults = $this->executeVectorSearch($vectorSearchParams, $collectionName);
            $vectorSearchTime = round((microtime(true) - $vectorSearchStart) * 1000, 2);

            $keywordSearchStart = microtime(true);
            $keywordResults = $this->typesenseClient->collections[$collectionName]->documents->search($keywordSearchParams);
            $keywordSearchTime = round((microtime(true) - $keywordSearchStart) * 1000, 2);

//            dd($vectorResults);

            Log::info('Individual Search Results Obtained', [
                'hybrid_search_id' => $hybridSearchId,
                'vector_results' => [
                    'found' => $vectorResults['found'] ?? 0,
                    'hits' => count($vectorResults['hits'] ?? []),
                    'search_time_ms' => $vectorSearchTime
                ],
                'keyword_results' => [
                    'found' => $keywordResults['found'] ?? 0,
                    'hits' => count($keywordResults['hits'] ?? []),
                    'search_time_ms' => $keywordSearchTime
                ]
            ]);

            // Combine and rank results
            $combineStartTime = microtime(true);
            $combinedResults = $this->combineSearchResults($vectorResults, $keywordResults, $params);
            $combineTime = round((microtime(true) - $combineStartTime) * 1000, 2);

            Log::info('Search Results Combined', [
                'hybrid_search_id' => $hybridSearchId,
                'combined_hits' => count($combinedResults['hits'] ?? []),
                'combine_time_ms' => $combineTime
            ]);

            $finalResults = $this->formatEcommerceResults($combinedResults, $params);

            Log::info('Hybrid Search Process Completed', [
                'hybrid_search_id' => $hybridSearchId,
                'total_time_ms' => round((microtime(true) - $hybridStartTime) * 1000, 2),
                'final_results_count' => count($finalResults['products'] ?? []),
                'total_found' => $finalResults['pagination']['total'] ?? 0
            ]);

            return $finalResults;

        } catch (\Exception $e) {
            Log::error('Hybrid Search Error, falling back to regular search', [
                'hybrid_search_id' => $hybridSearchId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'params' => $params,
                'total_time_ms' => round((microtime(true) - $hybridStartTime) * 1000, 2)
            ]);

            // Fall back to regular search on error
            return $this->search(array_merge($params, ['ai_search' => false]));
        }
    }

    /**
     * Execute vector search using multi_search endpoint to handle large payloads
     */
    private function executeVectorSearch(array $searchParams, string $collectionName): array
    {
        try {
            // Use multi_search endpoint for large vector queries
            $multiSearchQuery = [
                'searches' => [
                    [
                        'collection' => $collectionName,
                        'q' => $searchParams['q'],
                        'vector_query' => $searchParams['vector_query'],
                        'facet_by' => $searchParams['facet_by'] ?? '',
                        'max_facet_values' => $searchParams['max_facet_values'] ?? 100,
                        'per_page' => $searchParams['per_page'] ?? 24,
                        'page' => $searchParams['page'] ?? 1,
                        'filter_by' => $searchParams['filter_by'] ?? '',
                    ]
                ]
            ];

            Log::debug('Executing vector search via multi_search endpoint', [
                'collection' => $collectionName,
                'vector_query_length' => strlen($searchParams['vector_query'] ?? ''),
                'search_params_keys' => array_keys($searchParams)
            ]);

            // Execute multi_search
            $multiSearchResults = $this->typesenseClient->multiSearch->perform($multiSearchQuery, []);

            // Extract the first (and only) search result
            if (!empty($multiSearchResults['results']) && !empty($multiSearchResults['results'][0])) {
                $vectorResults = $multiSearchResults['results'][0];

                Log::debug('Vector search via multi_search completed successfully', [
                    'found' => $vectorResults['found'] ?? 0,
                    'hits_count' => count($vectorResults['hits'] ?? []),
                    'search_time_ms' => $vectorResults['search_time_ms'] ?? 0
                ]);

                return $vectorResults;
            } else {
                Log::warning('Multi_search returned empty results', [
                    'multi_search_response' => $multiSearchResults
                ]);
                return ['hits' => [], 'found' => 0, 'facet_counts' => []];
            }

        } catch (\Exception $e) {
            Log::error('Vector search via multi_search failed', [
                'error' => $e->getMessage(),
                'collection' => $collectionName,
                'trace' => $e->getTraceAsString()
            ]);

            // Return empty results on failure
            return ['hits' => [], 'found' => 0, 'facet_counts' => []];
        }
    }

    /**
     * Get embedding for search query using the external embedding API
     */
    private function getQueryEmbedding(string $query): ?array
    {
        try {
            $response = Http::timeout(30)->post('http://10.10.10.24:8000/embed', [
                'text' => $query,
            ]);

            if ($response->successful()) {
                $data = $response->json();

                return $data['embedding'] ?? null;
            }

            Log::warning('Embedding API request failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;

        } catch (\Exception $e) {
            Log::error('Error calling embedding API', [
                'error' => $e->getMessage(),
                'query' => $query,
            ]);

            return null;
        }
    }

    /**
     * Build search parameters for vector search
     */
    private function buildVectorSearchParams(array $params, array $queryEmbedding): array
    {
        $searchParams = [
            'q' => '*',
            'vector_query' => 'embedding_vector:(['.implode(',', $queryEmbedding).'], k:'.($params['per_page'] ?? 24).')',
            'facet_by' => 'category_name,brand_name,manufacturer_name,in_stock,is_rohs_compliant,sources,price_range,attribute_types',
            'max_facet_values' => 100,
            'per_page' => $params['per_page'] ?? 24,
            'page' => $params['page'] ?? 1,
        ];

        // Apply the same filters as regular search
        $filters = $this->buildEcommerceFilters($params);
        if (! empty($filters)) {
            $searchParams['filter_by'] = implode(' && ', $filters);
        }

        return $searchParams;
    }

    /**
     * Combine results from vector and keyword searches with intelligent ranking
     */
    private function combineSearchResults(array $vectorResults, array $keywordResults, array $params): array
    {
        $vectorHits = $vectorResults['hits'] ?? [];
        $keywordHits = $keywordResults['hits'] ?? [];

        Log::debug('Combining Search Results', [
            'vector_hits_count' => count($vectorHits),
            'keyword_hits_count' => count($keywordHits),
            'vector_sample' => array_slice(array_map(fn($hit) => [
                'id' => $hit['document']['id'] ?? 'unknown',
                'name' => $hit['document']['name'] ?? 'unknown'
            ], $vectorHits), 0, 5),
            'keyword_sample' => array_slice(array_map(fn($hit) => [
                'id' => $hit['document']['id'] ?? 'unknown',
                'name' => $hit['document']['name'] ?? 'unknown'
            ], $keywordHits), 0, 5)
        ]);

        // Create a map of document IDs to their scores and data
        $combinedHits = [];
        $seenIds = [];

        // Process vector search results (semantic similarity)
        foreach ($vectorHits as $index => $hit) {
            $docId = $hit['document']['id'];
            $vectorScore = 1.0 / (1 + $index); // Higher score for higher ranking

            $combinedHits[$docId] = [
                'document' => $hit['document'],
                'vector_score' => $vectorScore,
                'keyword_score' => 0,
                'highlights' => [],
                'text_match_info' => $hit['text_match_info'] ?? [],
            ];
            $seenIds[] = $docId;
        }

        // Process keyword search results
        foreach ($keywordHits as $index => $hit) {
            $docId = $hit['document']['id'];
            $keywordScore = 1.0 / (1 + $index);

            if (isset($combinedHits[$docId])) {
                // Document found in both searches
                $combinedHits[$docId]['keyword_score'] = $keywordScore;
                $combinedHits[$docId]['highlights'] = $hit['highlights'] ?? [];
            } else {
                // Document only found in keyword search
                $combinedHits[$docId] = [
                    'document' => $hit['document'],
                    'vector_score' => 0,
                    'keyword_score' => $keywordScore,
                    'highlights' => $hit['highlights'] ?? [],
                    'text_match_info' => $hit['text_match_info'] ?? [],
                ];
            }
        }

        // Calculate combined scores and sort
        foreach ($combinedHits as $docId => &$hit) {
            // Weighted combination: 60% semantic, 40% keyword
            $hit['combined_score'] = (0.6 * $hit['vector_score']) + (0.4 * $hit['keyword_score']);
        }

        // Sort by combined score
        uasort($combinedHits, function ($a, $b) {
            return $b['combined_score'] <=> $a['combined_score'];
        });

        // Rebuild the search results structure
        $combinedResults = $keywordResults; // Use keyword results as base for facets and pagination
        $combinedResults['hits'] = array_values($combinedHits);
        $combinedResults['found'] = count($combinedHits);

        Log::debug('Final Combined Results', [
            'total_combined_hits' => count($combinedHits),
            'unique_documents' => count(array_unique($seenIds)),
            'score_distribution' => [
                'vector_only' => count(array_filter($combinedHits, fn($hit) => $hit['vector_score'] > 0 && $hit['keyword_score'] == 0)),
                'keyword_only' => count(array_filter($combinedHits, fn($hit) => $hit['keyword_score'] > 0 && $hit['vector_score'] == 0)),
                'both_searches' => count(array_filter($combinedHits, fn($hit) => $hit['vector_score'] > 0 && $hit['keyword_score'] > 0))
            ],
            'top_5_results' => array_slice(array_map(fn($hit) => [
                'id' => $hit['document']['id'] ?? 'unknown',
                'name' => $hit['document']['name'] ?? 'unknown',
                'vector_score' => $hit['vector_score'],
                'keyword_score' => $hit['keyword_score'],
                'combined_score' => $hit['combined_score']
            ], array_values($combinedHits)), 0, 5)
        ]);

        return $combinedResults;
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
            'highlight_fields' => 'name,title,description,pnum,mf_pnum',
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
        if (! empty($filters)) {
            $searchParams['filter_by'] = implode(' && ', $filters);
        }

        return $searchParams;
    }

    private function buildEcommerceFilters(array $params): array
    {
        $filters = [];

        // Category filter
        if (! empty($params['category'])) {
            if (is_array($params['category'])) {
                $categories = array_filter($params['category']);
                if (! empty($categories)) {
                    $categoryList = implode(',', array_map(fn ($cat) => "'$cat'", $categories));
                    $filters[] = "category_name:[$categoryList]";
                }
            } else {
                $filters[] = "category_name:='{$params['category']}'";
            }
        }

        // Brand filter
        if (! empty($params['brand'])) {
            if (is_array($params['brand'])) {
                $brands = array_filter($params['brand']);
                if (! empty($brands)) {
                    $brandList = implode(',', array_map(fn ($brand) => "'$brand'", $brands));
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
        if (! empty($params['manufacturer'])) {
            if (is_array($params['manufacturer'])) {
                $manufacturers = array_filter($params['manufacturer']);
                if (! empty($manufacturers)) {
                    $manufacturerList = implode(',', array_map(fn ($mfr) => "'$mfr'", $manufacturers));
                    $filters[] = "manufacturer_name:[$manufacturerList]";
                }
            } else {
                $filters[] = "manufacturer_name:='{$params['manufacturer']}'";
            }
        }

        // Attributes filter (assuming attributes are stored as key-value pairs)
        if (! empty($params['attributes']) && is_array($params['attributes'])) {
            foreach ($params['attributes'] as $key => $value) {
                if (! empty($value) && ! empty($key)) {
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
        if (! empty($searchResults['hits'])) {
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
                    'text_match_info' => $hit['text_match_info'] ?? [],
                ];
            }
        }

        // Extract facets
        if (! empty($searchResults['facet_counts'])) {
            foreach ($searchResults['facet_counts'] as $facet) {
                $facetName = $facet['field_name'];
                $facetCounts = [];

                foreach ($facet['counts'] as $count) {
                    $facetCounts[] = [
                        'value' => $count['value'],
                        'count' => $count['count'],
                        'highlighted' => $count['highlighted'] ?? $count['value'],
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
                'total_pages' => ceil(($searchResults['found'] ?? 0) / ($params['per_page'] ?? 24)),
            ],
            'meta' => [
                'search_time_ms' => $searchResults['search_time_ms'] ?? 0,
                'search_cutoff' => $searchResults['search_cutoff'] ?? false,
                'total_found' => $searchResults['found'] ?? 0,
            ],
        ];
    }

    public function getFacetsOnly(array $params): array
    {
        $cacheKey = 'typesense_ecommerce_facets_'.md5(serialize($params));

        return Cache::remember($cacheKey, self::FACET_CACHE_TTL, function () use ($params) {
            try {
                $searchParams = $this->buildEcommerceSearchParams(array_merge($params, ['per_page' => 0]));

                $collectionName = $this->getCollectionName();
                $searchResults = $this->typesenseClient->collections[$collectionName]->documents->search($searchParams);

                $facets = [];
                if (! empty($searchResults['facet_counts'])) {
                    foreach ($searchResults['facet_counts'] as $facet) {
                        $facetName = $facet['field_name'];
                        $facetCounts = [];

                        foreach ($facet['counts'] as $count) {
                            $facetCounts[] = [
                                'value' => $count['value'],
                                'count' => $count['count'],
                                'highlighted' => $count['highlighted'] ?? $count['value'],
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
                'max_facet_values' => 5,
            ];

            $collectionName = $this->getCollectionName();
            $searchResults = $this->typesenseClient->collections[$collectionName]->documents->search($searchParams);

            $suggestions = [];
            if (! empty($searchResults['hits'])) {
                foreach ($searchResults['hits'] as $hit) {
                    $document = $hit['document'];
                    $suggestions[] = [
                        'id' => $document['id'],
                        'text' => $document['name'],
                        'category' => $document['category_name'] ?? '',
                        'brand' => $document['brand_name'] ?? '',
                        'image' => $document['image_url'] ?? '/images/place_holder.svg',
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
                'total_pages' => 0,
            ],
            'meta' => [
                'search_time_ms' => 0,
                'search_cutoff' => false,
                'total_found' => 0,
            ],
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
                'error' => $e->getMessage(),
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
