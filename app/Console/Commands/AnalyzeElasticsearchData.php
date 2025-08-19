<?php

namespace App\Console\Commands;

use App\Services\ElasticsearchService;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class AnalyzeElasticsearchData extends Command
{
    protected $signature = 'elasticsearch:analyze {--size=100 : Number of records to analyze} {--output=storage : Output location for report}';

    protected $description = 'Analyze Elasticsearch data structure and generate a mapping report';

    public function __construct(protected ElasticsearchService $elasticsearchService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $size = (int) $this->option('size');
        $output = $this->option('output');

        $this->info('Analyzing Elasticsearch data structure...');
        $this->info("Fetching {$size} records from the API endpoint");

        try {
            $sampleData = $this->elasticsearchService->getSampleData($size);

            if (empty($sampleData['hits']['hits'])) {
                $this->error('No data found in Elasticsearch index');

                return Command::FAILURE;
            }

            $totalRecords = $sampleData['hits']['total']['value'] ?? 0;
            $actualFetched = count($sampleData['hits']['hits']);

            $this->info("Found {$totalRecords} total records in index");
            $this->info("Analyzing {$actualFetched} records...");

            $analysis = $this->elasticsearchService->analyzeFieldStructure($sampleData);

            if (empty($analysis)) {
                $this->error('No field analysis could be performed');

                return Command::FAILURE;
            }

            $this->displayAnalysis($analysis, $actualFetched, $totalRecords);

            if ($output === 'storage') {
                $this->saveAnalysisReport($analysis, $actualFetched, $totalRecords);
            }

            $mapping = $this->elasticsearchService->getMapping();
            if (! empty($mapping)) {
                $this->info("\n📋 Elasticsearch Index Mapping Retrieved");
                if ($output === 'storage') {
                    $this->saveMappingReport($mapping);
                }
            }

            return Command::SUCCESS;

        } catch (Exception $e) {
            $this->error("Failed to analyze Elasticsearch data: {$e->getMessage()}");

            return Command::FAILURE;
        }
    }

    protected function displayAnalysis(array $analysis, int $analyzedCount, int $totalCount): void
    {
        $this->info("\n🔍 Elasticsearch Data Structure Analysis");
        $this->info('='.str_repeat('=', 60));
        $this->info("Records Analyzed: {$analyzedCount} / {$totalCount}");
        $this->info('Total Fields Found: '.count($analysis));

        $table = $this->table(
            ['Field Name', 'Primary Type', 'DB Type', 'Array', 'Max Length', 'Sample Value'],
            collect($analysis)->map(function ($field) {
                return [
                    $field['name'],
                    $field['primary_type'],
                    $field['suggested_db_type'],
                    $field['is_array'] ? '✓' : '✗',
                    $field['max_length'] ?: 'N/A',
                    is_array($field['sample_values']) && ! empty($field['sample_values'])
                        ? $this->formatSampleValue($field['sample_values'][0])
                        : 'N/A',
                ];
            })->toArray()
        );

        $this->info("\n📊 Field Type Summary:");
        $typeCounts = [];
        foreach ($analysis as $field) {
            $type = $field['primary_type'];
            $typeCounts[$type] = ($typeCounts[$type] ?? 0) + 1;
        }

        foreach ($typeCounts as $type => $count) {
            $this->line("  {$type}: {$count} fields");
        }

        $searchableFields = collect($analysis)->filter(function ($field) {
            return in_array($field['primary_type'], ['string', 'text']) && $field['max_length'] > 10;
        });

        if ($searchableFields->isNotEmpty()) {
            $this->info("\n🔎 Recommended Search Fields:");
            foreach ($searchableFields as $field) {
                $this->line("  • {$field['name']} ({$field['suggested_db_type']})");
            }
        }

        $filterableFields = collect($analysis)->filter(function ($field) {
            return in_array($field['primary_type'], ['integer', 'boolean', 'datetime', 'float']) ||
                   ($field['primary_type'] === 'string' && $field['max_length'] < 100);
        });

        if ($filterableFields->isNotEmpty()) {
            $this->info("\n🎛️  Recommended Filter Fields:");
            foreach ($filterableFields as $field) {
                $this->line("  • {$field['name']} ({$field['primary_type']})");
            }
        }
    }

    protected function saveAnalysisReport(array $analysis, int $analyzedCount, int $totalCount): void
    {
        $report = [
            'metadata' => [
                'generated_at' => now()->toISOString(),
                'records_analyzed' => $analyzedCount,
                'total_records' => $totalCount,
                'total_fields' => count($analysis),
                'elasticsearch_url' => config('elasticsearch.base_url'),
                'elasticsearch_index' => config('elasticsearch.index'),
            ],
            'field_analysis' => $analysis,
            'recommendations' => [
                'searchable_fields' => collect($analysis)->filter(function ($field) {
                    return in_array($field['primary_type'], ['string', 'text']) && $field['max_length'] > 10;
                })->keys()->toArray(),
                'filterable_fields' => collect($analysis)->filter(function ($field) {
                    return in_array($field['primary_type'], ['integer', 'boolean', 'datetime', 'float']) ||
                           ($field['primary_type'] === 'string' && $field['max_length'] < 100);
                })->keys()->toArray(),
                'indexable_fields' => collect($analysis)->filter(function ($field) use ($analyzedCount) {
                    return in_array($field['primary_type'], ['string', 'integer', 'datetime']) &&
                           $field['occurrence_count'] > ($analyzedCount * 0.8);
                })->keys()->toArray(),
            ],
        ];

        $filename = 'elasticsearch_analysis_'.now()->format('Y_m_d_H_i_s').'.json';
        Storage::disk('local')->put($filename, json_encode($report, JSON_PRETTY_PRINT));

        $this->info("\n💾 Analysis report saved to: storage/app/{$filename}");
    }

    protected function formatSampleValue($value): string
    {
        if (is_array($value)) {
            return substr(json_encode($value), 0, 100).(strlen(json_encode($value)) > 100 ? '...' : '');
        }

        $stringValue = (string) $value;

        return substr($stringValue, 0, 100).(strlen($stringValue) > 100 ? '...' : '');
    }
}
