<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Typesense Index Names
    |--------------------------------------------------------------------------
    |
    | Here you may specify custom index names for your Typesense collections.
    | This allows you to have different index names for different environments
    | or customize them according to your naming conventions.
    |
    */

    'indexes' => [
        'products' => [
            'production' => env('TYPESENSE_PRODUCTS_INDEX_PROD', 'enrgtech_products_v1'),
            'staging' => env('TYPESENSE_PRODUCTS_INDEX_STAGING', 'enrgtech_products_staging_v1'),
            'testing' => env('TYPESENSE_PRODUCTS_INDEX_TEST', 'enrgtech_products_test_v1'),
            'local' => env('TYPESENSE_PRODUCTS_INDEX', 'enrgtech_products_local_v1'),
        ],

        // You can add more models here
        // 'categories' => [
        //     'production' => env('TYPESENSE_CATEGORIES_INDEX_PROD', 'enrgtech_categories_v1'),
        //     'local' => env('TYPESENSE_CATEGORIES_INDEX', 'enrgtech_categories_local_v1'),
        // ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Naming Strategy
    |--------------------------------------------------------------------------
    |
    | Define how index names should be generated if not explicitly set above.
    | Options: 'environment', 'prefix', 'custom'
    |
    */

    'naming_strategy' => 'environment',

    'prefix' => env('TYPESENSE_INDEX_PREFIX', 'enrgtech_'),

    'suffix' => env('TYPESENSE_INDEX_SUFFIX', '_v1'),
];
