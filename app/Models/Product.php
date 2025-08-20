<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class Product extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'slug',
        'description',
        'sku',
        'price',
        'stock_quantity',
        'status',
        'category_id',
        'brand_id',
        'manufacturer_id',
        'category_name',
        'brand_name',
        'manufacturer_name',
        'images',
        'thumbnails',
        'attributes',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'stock_quantity' => 'integer',
            'images' => 'array',
            'thumbnails' => 'array',
            'attributes' => 'array',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function manufacturer(): BelongsTo
    {
        return $this->belongsTo(Manufacturer::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeInStock(Builder $query): Builder
    {
        return $query->where('stock_quantity', '>', 0);
    }

    public function scopePriceRange(Builder $query, float $min = null, float $max = null): Builder
    {
        if ($min !== null) {
            $query->where('price', '>=', $min);
        }
        if ($max !== null) {
            $query->where('price', '<=', $max);
        }
        return $query;
    }

    public function scopeByCategory(Builder $query, $categoryId): Builder
    {
        return $query->where('category_id', $categoryId);
    }

    public function scopeByBrand(Builder $query, $brandId): Builder
    {
        return $query->where('brand_id', $brandId);
    }

    public function scopeByManufacturer(Builder $query, $manufacturerId): Builder
    {
        return $query->where('manufacturer_id', $manufacturerId);
    }

    public function scopeFullTextSearch(Builder $query, string $searchTerm): Builder
    {
        return $query->whereRaw(
            "search_vector @@ plainto_tsquery('english', ?)",
            [$searchTerm]
        );
    }

    public function scopeFuzzySearch(Builder $query, string $searchTerm): Builder
    {
        return $query->whereRaw(
            "name % ? OR sku % ?",
            [$searchTerm, $searchTerm]
        );
    }

    public function scopeWithJsonAttribute(Builder $query, string $key, $value): Builder
    {
        return $query->whereJsonContains("attributes->{$key}", $value);
    }

    public function scopePopular(Builder $query, int $limit = 10): Builder
    {
        return $query->orderBy('created_at', 'desc')->limit($limit);
    }

    public function updateSearchVector(): void
    {
        DB::statement("
            UPDATE products 
            SET search_vector = to_tsvector('english', 
                coalesce(name, '') || ' ' || 
                coalesce(description, '') || ' ' || 
                coalesce(sku, '') || ' ' ||
                coalesce(category_name, '') || ' ' ||
                coalesce(brand_name, '') || ' ' ||
                coalesce(manufacturer_name, '')
            )
            WHERE id = ?
        ", [$this->id]);
    }

    protected static function booted(): void
    {
        static::created(function (Product $product) {
            $product->updateSearchVector();
        });

        static::updated(function (Product $product) {
            $product->updateSearchVector();
        });
    }

    public function getMainImageAttribute(): ?string
    {
        return $this->images[0] ?? null;
    }

    public function getMainThumbnailAttribute(): ?string
    {
        return $this->thumbnails[0] ?? null;
    }

    public function isInStock(): bool
    {
        return $this->stock_quantity > 0;
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
