<?php

namespace App\Http\Controllers;

use App\Services\TypesenseEcommerceSearchService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class EcommerceSearchController extends Controller
{
    public function __construct(
        private TypesenseEcommerceSearchService $searchService
    ) {}

    public function search(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'q' => 'nullable|string|max:500',
            'category' => 'nullable|array',
            'category.*' => 'string|max:100',
            'brand' => 'nullable|array',
            'brand.*' => 'string|max:100',
            'manufacturer' => 'nullable|array',
            'manufacturer.*' => 'string|max:100',
            'price_range' => 'nullable|array',
            'price_range.*' => 'string|max:50',
            'min_price' => 'nullable|numeric|min:0',
            'max_price' => 'nullable|numeric|min:0',
            'in_stock' => 'nullable|boolean',
            'sort_by' => 'nullable|in:relevance,price_asc,price_desc,name_asc,name_desc,newest,rating,popularity',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid search parameters',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $params = $validator->validated();
            $results = $this->searchService->search($params);

            return response()->json([
                'success' => true,
                'data' => $results
            ]);

        } catch (\Exception $e) {
            Log::error('Ecommerce search API error', [
                'error' => $e->getMessage(),
                'params' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Search temporarily unavailable. Please try again.',
                'error_code' => 'SEARCH_ERROR'
            ], 500);
        }
    }

    public function facets(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'q' => 'nullable|string|max:500',
            'category' => 'nullable|array',
            'brand' => 'nullable|array',
            'manufacturer' => 'nullable|array',
            'price_range' => 'nullable|array',
            'min_price' => 'nullable|numeric|min:0',
            'max_price' => 'nullable|numeric|min:0',
            'in_stock' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid facet parameters',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $params = $validator->validated();
            $facets = $this->searchService->getFacetsOnly($params);

            // Add price ranges metadata
            $facets['_price_ranges'] = $this->searchService->getPriceRanges();

            return response()->json([
                'success' => true,
                'data' => [
                    'facets' => $facets
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Ecommerce facets API error', [
                'error' => $e->getMessage(),
                'params' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Facets temporarily unavailable. Please try again.',
                'error_code' => 'FACETS_ERROR'
            ], 500);
        }
    }

    public function autocomplete(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'q' => 'required|string|max:100|min:2',
            'limit' => 'nullable|integer|min:1|max:20'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid autocomplete parameters',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $query = $validator->validated()['q'];
            $limit = $validator->validated()['limit'] ?? 10;

            $suggestions = $this->searchService->autocomplete($query, $limit);

            return response()->json([
                'success' => true,
                'data' => [
                    'suggestions' => $suggestions
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Ecommerce autocomplete API error', [
                'error' => $e->getMessage(),
                'query' => $request->input('q')
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Autocomplete temporarily unavailable. Please try again.',
                'error_code' => 'AUTOCOMPLETE_ERROR'
            ], 500);
        }
    }

    public function priceRanges(): JsonResponse
    {
        try {
            $priceRanges = $this->searchService->getPriceRanges();

            return response()->json([
                'success' => true,
                'data' => [
                    'price_ranges' => $priceRanges
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Price ranges API error', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Price ranges temporarily unavailable.',
                'error_code' => 'PRICE_RANGES_ERROR'
            ], 500);
        }
    }
}
