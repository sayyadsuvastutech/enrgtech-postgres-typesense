<?php

return [
    'base_url' => env('ELASTICSEARCH_BASE_URL', 'http://10.10.10.227:9200'),
    'index' => env('ELASTICSEARCH_INDEX', 'pro-prod'),
    'timeout' => env('ELASTICSEARCH_TIMEOUT', 30),
    'batch_size' => env('ELASTICSEARCH_BATCH_SIZE', 100),
    'max_retries' => env('ELASTICSEARCH_MAX_RETRIES', 3),
    'retry_delay' => env('ELASTICSEARCH_RETRY_DELAY', 1000), // milliseconds
];
