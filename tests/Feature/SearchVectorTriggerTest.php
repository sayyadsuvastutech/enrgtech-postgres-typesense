<?php

declare(strict_types=1);

use App\Models\Product;
use App\Models\ProductAttribute;
use Illuminate\Support\Facades\DB;

describe('Search Vector Trigger', function () {
    
    beforeEach(function () {
        // Create required status record
        DB::table('ioa_statuses')->insertOrIgnore([
            'id' => 1,
            'name' => 'Active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('ioa_statuses')->insertOrIgnore([
            'id' => 2, 
            'name' => 'Inactive',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    });
    
    it('builds complete search vector when product is created', function () {
        // Create a product with minimal required fields
        $productId = DB::table('ioa_products')->insertGetId([
            'name' => 'Test Product Name',
            'title' => 'Test Product Title',
            'pnum' => 'TEST123',
            'mf_pnum' => 'MF-TEST-123',
            'description' => 'Test product description',
            'status_id' => 1,
            'is_rohs_compliant' => false,
            'pushed' => false,
            'total_reviews' => 0,
            'is_updated' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Get the created product with search vector
        $product = DB::selectOne("SELECT * FROM ioa_products WHERE id = ?", [$productId]);

        // Verify search vector was created and contains product data
        expect($product->search_vector)->not->toBeNull();
        
        // Test that search vector contains the product data
        $searchResult = DB::select("
            SELECT * FROM ioa_products 
            WHERE search_vector @@ to_tsquery('english', 'Test')
            AND id = ?
        ", [$productId]);

        expect($searchResult)->toHaveCount(1);
    });

    it('updates search vector when product is updated', function () {
        // Create a product
        $product = Product::factory()->create([
            'name' => 'Original Name',
        ]);

        $originalVector = DB::selectOne("SELECT search_vector FROM ioa_products WHERE id = ?", [$product->id])->search_vector;

        // Update the product
        $product->update(['name' => 'Updated Name']);

        $updatedVector = DB::selectOne("SELECT search_vector FROM ioa_products WHERE id = ?", [$product->id])->search_vector;

        // Verify the search vector was updated
        expect($updatedVector)->not->toBe($originalVector);

        // Test that search works with the new name
        $searchResult = DB::select("
            SELECT * FROM ioa_products 
            WHERE search_vector @@ to_tsquery('english', 'Updated')
            AND id = ?
        ", [$product->id]);

        expect($searchResult)->toHaveCount(1);
    });

    it('updates product search vector when attributes are added', function () {
        // Create a product
        $product = Product::factory()->create([
            'name' => 'Test Product',
        ]);

        $originalVector = DB::selectOne("SELECT search_vector FROM ioa_products WHERE id = ?", [$product->id])->search_vector;

        // Add product attributes
        DB::table('ioa_product_attributes')->insert([
            'product_id' => $product->id,
            'source_name' => 'test_source',
            'attributes' => json_encode([
                'color' => 'red',
                'size' => 'large',
                'material' => 'plastic'
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $updatedVector = DB::selectOne("SELECT search_vector FROM ioa_products WHERE id = ?", [$product->id])->search_vector;

        // Verify the search vector was updated
        expect($updatedVector)->not->toBe($originalVector);

        // Test that search works with attribute data
        $colorSearchResult = DB::select("
            SELECT * FROM ioa_products 
            WHERE search_vector @@ to_tsquery('english', 'red')
            AND id = ?
        ", [$product->id]);

        $materialSearchResult = DB::select("
            SELECT * FROM ioa_products 
            WHERE search_vector @@ to_tsquery('english', 'plastic')
            AND id = ?
        ", [$product->id]);

        expect($colorSearchResult)->toHaveCount(1);
        expect($materialSearchResult)->toHaveCount(1);
    });

    it('updates product search vector when attributes are modified', function () {
        // Create a product with initial attributes
        $product = Product::factory()->create([
            'name' => 'Test Product',
        ]);

        // Add initial attributes
        $attributeId = DB::table('ioa_product_attributes')->insertGetId([
            'product_id' => $product->id,
            'source_name' => 'test_source',
            'attributes' => json_encode([
                'color' => 'blue',
                'size' => 'small'
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Update the attributes
        DB::table('ioa_product_attributes')
            ->where('id', $attributeId)
            ->update([
                'attributes' => json_encode([
                    'color' => 'green',
                    'size' => 'large',
                    'material' => 'metal'
                ]),
                'updated_at' => now(),
            ]);

        // Test that search works with new attribute values
        $greenSearchResult = DB::select("
            SELECT * FROM ioa_products 
            WHERE search_vector @@ to_tsquery('english', 'green')
            AND id = ?
        ", [$product->id]);

        $metalSearchResult = DB::select("
            SELECT * FROM ioa_products 
            WHERE search_vector @@ to_tsquery('english', 'metal')
            AND id = ?
        ", [$product->id]);

        // Should NOT find the old color
        $blueSearchResult = DB::select("
            SELECT * FROM ioa_products 
            WHERE search_vector @@ to_tsquery('english', 'blue')
            AND id = ?
        ", [$product->id]);

        expect($greenSearchResult)->toHaveCount(1);
        expect($metalSearchResult)->toHaveCount(1);
        expect($blueSearchResult)->toHaveCount(0);
    });

    it('updates product search vector when attributes are deleted', function () {
        // Create a product with attributes
        $product = Product::factory()->create([
            'name' => 'Test Product',
        ]);

        // Add attributes
        $attributeId = DB::table('ioa_product_attributes')->insertGetId([
            'product_id' => $product->id,
            'source_name' => 'test_source',
            'attributes' => json_encode([
                'color' => 'yellow',
                'temporary' => 'delete_me'
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Verify attribute data is searchable before deletion
        $beforeDeleteResult = DB::select("
            SELECT * FROM ioa_products 
            WHERE search_vector @@ to_tsquery('english', 'delete_me')
            AND id = ?
        ", [$product->id]);

        expect($beforeDeleteResult)->toHaveCount(1);

        // Delete the attributes
        DB::table('ioa_product_attributes')->where('id', $attributeId)->delete();

        // Verify attribute data is no longer searchable after deletion
        $afterDeleteResult = DB::select("
            SELECT * FROM ioa_products 
            WHERE search_vector @@ to_tsquery('english', 'delete_me')
            AND id = ?
        ", [$product->id]);

        expect($afterDeleteResult)->toHaveCount(0);

        // But the product should still be searchable by its name
        $productNameResult = DB::select("
            SELECT * FROM ioa_products 
            WHERE search_vector @@ to_tsquery('english', 'Test')
            AND id = ?
        ", [$product->id]);

        expect($productNameResult)->toHaveCount(1);
    });

    it('maintains product data in search vector when only attributes change', function () {
        // Create a product
        $product = Product::factory()->create([
            'name' => 'Product Name Should Stay',
            'pnum' => 'STAY123'
        ]);

        // Verify product data is searchable initially
        $initialProductSearch = DB::select("
            SELECT * FROM ioa_products 
            WHERE search_vector @@ to_tsquery('english', 'STAY123')
            AND id = ?
        ", [$product->id]);

        expect($initialProductSearch)->toHaveCount(1);

        // Add attributes
        DB::table('ioa_product_attributes')->insert([
            'product_id' => $product->id,
            'source_name' => 'test_source',
            'attributes' => json_encode([
                'new_attribute' => 'new_value'
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Verify product data is STILL searchable after attribute addition
        $afterAttributeSearch = DB::select("
            SELECT * FROM ioa_products 
            WHERE search_vector @@ to_tsquery('english', 'STAY123')
            AND id = ?
        ", [$product->id]);

        $nameSearch = DB::select("
            SELECT * FROM ioa_products 
            WHERE search_vector @@ to_tsquery('english', 'Product & Name & Should & Stay')
            AND id = ?
        ", [$product->id]);

        // AND the new attribute is also searchable
        $attributeSearch = DB::select("
            SELECT * FROM ioa_products 
            WHERE search_vector @@ to_tsquery('english', 'new_value')
            AND id = ?
        ", [$product->id]);

        expect($afterAttributeSearch)->toHaveCount(1);
        expect($nameSearch)->toHaveCount(1);
        expect($attributeSearch)->toHaveCount(1);
    });
});