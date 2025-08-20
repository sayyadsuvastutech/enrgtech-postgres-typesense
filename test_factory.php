<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';

$product = \App\Models\Product::factory()->create();

echo "Product created successfully!" . PHP_EOL;
echo "ID: " . $product->id . PHP_EOL;
echo "Name: " . $product->name . PHP_EOL;
echo "Category ID: " . $product->category_id . " | Category Name: " . $product->category_name . PHP_EOL;
echo "Brand ID: " . $product->brand_id . " | Brand Name: " . $product->brand_name . PHP_EOL;
echo "Manufacturer ID: " . $product->manufacturer_id . " | Manufacturer Name: " . $product->manufacturer_name . PHP_EOL;

$allFieldsPopulated = $product->category_name && $product->brand_name && $product->manufacturer_name;
echo "All denormalized fields populated: " . ($allFieldsPopulated ? "YES" : "NO") . PHP_EOL;