<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'title',
        'code',
        'product_number',
        'manufacturer_product_number',
        'manufacturer_product_slug',
        'description',
        'category_id',
        'manufacturer_id',
        'breadcrumb',
        'meta_title',
        'meta_description',
        'is_rohs_compliant',
        'is_verified',
        'is_pushed',
        'total_reviews',
        'average_rating',
        'video_url',
        'status_id',
        'session_insert_id',
        'session_update_id',
        'is_updated',
        'created_by',
        'updated_by',
        'category_name',
        'manufacturer_name',
    ];

    protected function casts(): array
    {
        return [
            'average_rating' => 'decimal:2',
            'is_rohs_compliant' => 'boolean',
            'is_verified' => 'boolean',
            'is_pushed' => 'boolean',
            'is_updated' => 'boolean',
            'total_reviews' => 'integer',
            'status_id' => 'integer',
            'session_insert_id' => 'integer',
            'session_update_id' => 'integer',
            'created_by' => 'integer',
            'updated_by' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class);
    }

    public function sources(): HasMany
    {
        return $this->hasMany(ProductSource::class);
    }

    public function attributes(): HasMany
    {
        return $this->hasMany(ProductAttribute::class);
    }

    public function prices(): HasMany
    {
        return $this->hasMany(ProductPrice::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class);
    }

    public function quantities(): HasMany
    {
        return $this->hasMany(ProductQuantity::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ProductDocument::class);
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

    public function scopePriceRange(Builder $query, ?float $min = null, ?float $max = null): Builder
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

    public function scopeByProductNumber(Builder $query, string $productNumber): Builder
    {
        return $query->where('product_number', $productNumber);
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
     * Optimized semantic search with typo tolerance using CTE, FTS, and trigram
     */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        $tsquery = implode(' & ', array_map(fn($t) => $t . ':*', explode(' ', $term)));
        $exactMatch = "%$term%";

        return $query->fromSub(function ($subQuery) use ($tsquery, $term, $exactMatch) {
            // Stage 1: Pre-filter with indexable conditions (FTS, trigram similarity, ILIKE)
            $subQuery->select('products.*')
                ->from('products')
                ->where('status', 'active')
                ->where(function ($q) use ($tsquery, $term, $exactMatch) {
                    $q->whereRaw('search_vector @@ to_tsquery(\'english\', ?)', [$tsquery])
                        ->orWhereRaw('name % ?', [$term])  // Trigram similarity for typos (threshold 0.3)
                        ->orWhereRaw('name ILIKE ?', [$exactMatch]);
                })
                ->orderByRaw('
                    CASE WHEN name ILIKE ? THEN 1 ELSE 2 END,
                    ts_rank_cd(search_vector, to_tsquery(\'english\', ?), 32) DESC
                ', [$exactMatch, $tsquery])
                ->limit(2000);  // Limit candidates for performance
        }, 'candidates')
            ->selectRaw('
            candidates.*,
            ts_rank_cd(search_vector, to_tsquery(\'english\', ?)) AS fts_score,
            word_similarity(?, name) AS trigram_score,
            (
                0.5 * ts_rank_cd(search_vector, to_tsquery(\'english\', ?)) +
                0.3 * word_similarity(?, name) +
                0.2 * COALESCE((
                    SELECT MAX(1.0 / (1 + levenshtein(lower(word), lower(?))))
                    FROM unnest(string_to_array(regexp_replace(lower(name), \'[^a-z0-9 ]\', \' \', \'g\'), \' \')) AS word
                    WHERE length(word) > 1
                ), 0)
            ) AS total_score
        ', [$tsquery, $term, $tsquery, $term, $term])
            ->orderByDesc('total_score');
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
