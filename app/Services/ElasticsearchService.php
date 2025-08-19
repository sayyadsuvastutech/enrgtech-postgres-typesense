<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ElasticsearchService
{
    protected string $baseUrl;

    protected string $index;

    protected int $timeout;

    public function __construct()
    {
        $this->baseUrl = config('elasticsearch.base_url', 'http://10.10.10.227:9200');
        $this->index = config('elasticsearch.index', 'pro-prod');
        $this->timeout = config('elasticsearch.timeout', 30);
    }

    public function search(array $query = [], int $size = 10, int $from = 0): array
    {
        try {
            $searchParams = [
                'size' => $size,
                'from' => $from,
            ];

            // If query is empty, use match_all
            if (empty($query)) {
                $searchParams['q'] = '*:*';
            } else {
                $searchParams = array_merge($searchParams, $query);
            }

            $response = Http::timeout($this->timeout)
                ->get("{$this->baseUrl}/{$this->index}/_search", $searchParams);

            if ($response->failed()) {
                Log::error('Elasticsearch request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                throw new Exception("Elasticsearch request failed with status: {$response->status()}");
            }

            return $response->json();
        } catch (Exception $e) {
            Log::error('Elasticsearch service error', [
                'message' => $e->getMessage(),
                'query' => $query,
            ]);
            throw $e;
        }
    }

    public function searchWithFilters(string $term = '', string $category = '', string $brand = '', int $size = 25, int $from = 0): array
    {
        $queryParts = [];
        
        // Build the query string
        if (!empty($term)) {
            $queryParts[] = "name:{$term}* OR description:{$term}* OR mf_pnum:{$term}* OR pnum:{$term}*";
        }
        
        if (!empty($category)) {
            $queryParts[] = "ct_name:\"{$category}\"";
        }
        
        if (!empty($brand)) {
            $queryParts[] = "mf_name:\"{$brand}\"";
        }
        
        $queryString = !empty($queryParts) ? implode(' AND ', $queryParts) : '*:*';
        
        return $this->search(['q' => $queryString], $size, $from);
    }

    public function getSampleData(int $size = 50): array
    {
        return $this->search([], $size);
    }

    public function analyzeFieldStructure(array $data): array
    {
        $analysis = [];

        if (! isset($data['hits']['hits'])) {
            return $analysis;
        }

        foreach ($data['hits']['hits'] as $hit) {
            $source = $hit['_source'] ?? [];
            $this->analyzeFields($source, $analysis);
        }

        return $this->formatAnalysis($analysis);
    }

    protected function analyzeFields(array $data, array &$analysis, string $prefix = ''): void
    {
        foreach ($data as $key => $value) {
            $fieldName = $prefix ? "{$prefix}.{$key}" : $key;

            if (! isset($analysis[$fieldName])) {
                $analysis[$fieldName] = [
                    'types' => [],
                    'sample_values' => [],
                    'is_array' => false,
                    'is_nested' => false,
                    'max_length' => 0,
                    'count' => 0,
                ];
            }

            $analysis[$fieldName]['count']++;

            if (is_array($value)) {
                $analysis[$fieldName]['is_array'] = true;
                if (! empty($value) && isset($value[0]) && is_array($value[0])) {
                    $analysis[$fieldName]['is_nested'] = true;
                    foreach ($value as $nestedItem) {
                        if (is_array($nestedItem)) {
                            $this->analyzeFields($nestedItem, $analysis, $fieldName);
                        }
                    }
                } else {
                    foreach ($value as $item) {
                        $this->recordFieldValue($analysis[$fieldName], $item);
                    }
                }
            } else {
                $this->recordFieldValue($analysis[$fieldName], $value);
            }
        }
    }

    protected function recordFieldValue(array &$fieldAnalysis, $value): void
    {
        $type = $this->getValueType($value);

        if (! in_array($type, $fieldAnalysis['types'])) {
            $fieldAnalysis['types'][] = $type;
        }

        if (count($fieldAnalysis['sample_values']) < 5) {
            $fieldAnalysis['sample_values'][] = $value;
        }

        if (is_string($value)) {
            $fieldAnalysis['max_length'] = max($fieldAnalysis['max_length'], strlen($value));
        }
    }

    protected function getValueType($value): string
    {
        if (is_null($value)) {
            return 'null';
        }
        if (is_bool($value)) {
            return 'boolean';
        }
        if (is_int($value)) {
            return 'integer';
        }
        if (is_float($value)) {
            return 'float';
        }
        if (is_string($value)) {
            if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
                return 'email';
            }
            if (filter_var($value, FILTER_VALIDATE_URL)) {
                return 'url';
            }
            if (preg_match('/^\d{4}-\d{2}-\d{2}/', $value)) {
                return 'datetime';
            }

            return 'string';
        }
        if (is_array($value)) {
            return 'array';
        }

        return 'unknown';
    }

    protected function formatAnalysis(array $analysis): array
    {
        $formatted = [];

        foreach ($analysis as $fieldName => $fieldData) {
            $formatted[$fieldName] = [
                'name' => $fieldName,
                'types' => $fieldData['types'],
                'primary_type' => $this->determinePrimaryType($fieldData['types']),
                'is_array' => $fieldData['is_array'],
                'is_nested' => $fieldData['is_nested'],
                'max_length' => $fieldData['max_length'],
                'sample_values' => array_slice($fieldData['sample_values'], 0, 3),
                'occurrence_count' => $fieldData['count'],
                'suggested_db_type' => $this->suggestDatabaseType($fieldData),
            ];
        }

        return $formatted;
    }

    protected function determinePrimaryType(array $types): string
    {
        if (in_array('string', $types)) {
            return 'string';
        }
        if (in_array('integer', $types)) {
            return 'integer';
        }
        if (in_array('float', $types)) {
            return 'float';
        }
        if (in_array('boolean', $types)) {
            return 'boolean';
        }
        if (in_array('datetime', $types)) {
            return 'datetime';
        }

        return $types[0] ?? 'unknown';
    }

    protected function suggestDatabaseType(array $fieldData): string
    {
        $primaryType = $this->determinePrimaryType($fieldData['types']);

        switch ($primaryType) {
            case 'integer':
                return 'bigInteger';
            case 'float':
                return 'decimal';
            case 'boolean':
                return 'boolean';
            case 'datetime':
                return 'timestamp';
            case 'email':
                return 'string';
            case 'url':
                return 'text';
            case 'string':
                if ($fieldData['max_length'] > 255) {
                    return 'text';
                }

                return $fieldData['max_length'] > 0 ? "string({$fieldData['max_length']})" : 'string';
            case 'array':
                return 'json';
            default:
                return 'text';
        }
    }

    public function getMapping(): array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->get("{$this->baseUrl}/{$this->index}/_mapping");

            if ($response->failed()) {
                Log::error('Failed to get Elasticsearch mapping', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return [];
            }

            return $response->json();
        } catch (Exception $e) {
            Log::error('Error getting Elasticsearch mapping', [
                'message' => $e->getMessage(),
            ]);

            return [];
        }
    }
}
