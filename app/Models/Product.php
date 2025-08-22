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


    /**
     * Semantic search with multi-factor scoring and Google-like operators
     */
    public function scopeSemanticSearch(Builder $query, string $searchTerm): Builder
    {
        $normalizedTerm = trim($searchTerm);

        if (empty($normalizedTerm)) {
            return $query->where('id', '<', 0); // No results for empty search
        }

        return $query->selectRaw("
            products.*,
            semantic_search_score(?, name, sku, description, category_name, brand_name, search_vector) as total_score
        ", [$normalizedTerm])
        ->whereRaw("semantic_search_match(?, name, sku, description, search_vector)", [$normalizedTerm])
        ->orderBy('total_score', 'desc');
    }

    /**
     * Full-text search using websearch_to_tsquery for Google-like operators
     */
    public function scopeWebSearch(Builder $query, string $searchTerm): Builder
    {
        return $query->selectRaw(
            "*, ts_rank_cd(search_vector, websearch_to_tsquery('english', ?), 32) as search_rank",
            [$searchTerm]
        )->whereRaw(
            "search_vector @@ websearch_to_tsquery('english', ?)",
            [$searchTerm]
        )->orderBy('search_rank', 'desc');
    }

    /**
     * Phrase search for exact matches
     */
    public function scopePhraseSearch(Builder $query, string $phrase): Builder
    {
        return $query->selectRaw(
            "*, ts_rank_cd(search_vector, phraseto_tsquery('english', ?), 32) as search_rank",
            [$phrase]
        )->whereRaw(
            "search_vector @@ phraseto_tsquery('english', ?)",
            [$phrase]
        )->orderBy('search_rank', 'desc');
    }

    /**
     * Fuzzy search using trigram similarity
     */
    public function scopeFuzzySearch(Builder $query, string $searchTerm, float $threshold = 0.3): Builder
    {
        return $query->selectRaw("
            *,
            GREATEST(
                similarity(name, ?),
                similarity(sku, ?),
                COALESCE(similarity(description, ?), 0)
            ) as fuzzy_score
        ", [$searchTerm, $searchTerm, $searchTerm])
        ->whereRaw("
            similarity(name, ?) > ? OR
            similarity(sku, ?) > ? OR
            similarity(description, ?) > ?
        ", [$searchTerm, $threshold, $searchTerm, $threshold, $searchTerm, $threshold])
        ->orderBy('fuzzy_score', 'desc');
    }

    /**
     * Levenshtein distance search for edit distance matching
     */
    public function scopeLevenshteinSearch(Builder $query, string $searchTerm, int $maxDistance = 3): Builder
    {
        return $query->selectRaw("
            *,
            LEAST(
                levenshtein(name, ?),
                levenshtein(sku, ?),
                COALESCE(levenshtein(description, ?), 999)
            ) as edit_distance
        ", [$searchTerm, $searchTerm, $searchTerm])
        ->whereRaw("
            levenshtein(name, ?) <= ? OR
            levenshtein(sku, ?) <= ? OR
            levenshtein(description, ?) <= ?
        ", [$searchTerm, $maxDistance, $searchTerm, $maxDistance, $searchTerm, $maxDistance])
        ->orderBy('edit_distance', 'asc');
    }

    /**
     * Combined search with multiple strategies
     */
    public function scopeSearchWithRank($query, $searchTerm)
    {
        return $this->scopeSemanticSearch($query, $searchTerm);
    }

    /**
     * Legacy support - simple full-text search
     */
    public function scopeSearch($query, $searchTerm)
    {
        return $query->whereRaw(
            "search_vector @@ plainto_tsquery('english', ?)",
            [$searchTerm]
        )->orderByRaw(
            "ts_rank(search_vector, plainto_tsquery('english', ?)) DESC",
            [$searchTerm]
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
