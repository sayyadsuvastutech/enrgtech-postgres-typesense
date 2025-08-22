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


    public function scopeSearch2($query, string $term)
    {
        // Convert to tsquery with prefix search
        $tsquery = implode(' & ', array_map(fn($t) => $t . ':*', explode(' ', $term)));

        return $query->selectRaw('products.*,
            ts_rank_cd(search_vector, to_tsquery(\'english\', ?)) AS fts_score,
            similarity(name, ?) AS trigram_score,
            1.0 / (1 + levenshtein(lower(name), lower(?))) AS levenshtein_score,
            (
              0.6 * ts_rank_cd(search_vector, to_tsquery(\'english\', ?)) +
              0.3 * similarity(name, ?) +
              0.1 * (1.0 / (1 + levenshtein(lower(name), lower(?))))
            ) AS total_score',
            [$tsquery, $term, $term, $tsquery, $term, $term]
        )
            ->where('status', 'active')
            ->where(function ($q) use ($tsquery, $term) {
                $q->whereRaw('ts_rank_cd(search_vector, to_tsquery(\'english\', ?)) > 0', [$tsquery])
                    ->orWhereRaw('similarity(name, ?) > 0.1', [$term]) // relaxed threshold
                    ->orWhereRaw('levenshtein(lower(name), lower(?)) <= 3', [$term])
                    ->orWhereRaw('name ILIKE ?', ["%$term%"]); // extra safety net
            })
            ->orderByDesc('total_score');
    }


    public function scopeSearch($query, string $term)
    {
        // Convert to tsquery with prefix search
        $tsquery = implode(' & ', array_map(fn($t) => $t . ':*', explode(' ', $term)));

        return $query->selectRaw('products.*,
        ts_rank_cd(search_vector, to_tsquery(\'english\', ?)) AS fts_score,
        similarity(name, ?) AS trigram_score,

        -- Word-level Levenshtein: find the best matching word in the title
        (
            SELECT MAX(1.0 / (1 + levenshtein(lower(word), lower(?))))
            FROM unnest(string_to_array(regexp_replace(lower(name), \'[^a-z0-9 ]\', \' \', \'g\'), \' \')) AS word
            WHERE length(word) > 1
        ) AS word_levenshtein_score,

        (
          0.5 * ts_rank_cd(search_vector, to_tsquery(\'english\', ?)) +
          0.3 * similarity(name, ?) +
          0.2 * COALESCE((
              SELECT MAX(1.0 / (1 + levenshtein(lower(word), lower(?))))
              FROM unnest(string_to_array(regexp_replace(lower(name), \'[^a-z0-9 ]\', \' \', \'g\'), \' \')) AS word
              WHERE length(word) > 1
          ), 0)
        ) AS total_score',
            [$tsquery, $term, $term, $tsquery, $term, $term]
        )
            ->where('status', 'active')
            ->where(function ($q) use ($tsquery, $term) {
                $q->whereRaw('ts_rank_cd(search_vector, to_tsquery(\'english\', ?)) > 0', [$tsquery])
                    ->orWhereRaw('similarity(name, ?) > 0.1', [$term])

//                -- Word-level Levenshtein filter: check if ANY word is close enough
                    ->orWhereRaw('
              EXISTS (
                  SELECT 1
                  FROM unnest(string_to_array(regexp_replace(lower(name), \'[^a-z0-9 ]\', \' \', \'g\'), \' \')) AS word
                  WHERE length(word) > 1 AND levenshtein(lower(word), lower(?)) <= 2
              )
          ', [$term])

                    ->orWhereRaw('name ILIKE ?', ["%$term%"]);
    })
            ->orderByDesc('total_score');
    }

    public function scopeSearchnew($query, string $term, int $limit = 50)
    {
        // Sanitize and prepare the search term
        $term = trim($term);
        if (empty($term)) {
            return $query->where('status', 'active')->orderBy('name')->limit($limit);
        }

        // Convert to tsquery with prefix search (use 'simple' dictionary for misspellings)
        $tsquery = implode(' & ', array_map(fn($t) => $t . ':*', array_filter(explode(' ', $term))));

        // Compute Levenshtein score once
        $levenshteinSubquery = "
        COALESCE((
            SELECT MAX(1.0 / (1 + levenshtein(lower(word), lower(?))))
            FROM unnest(string_to_array(regexp_replace(lower(name), '[^a-z0-9 ]', ' ', 'g'), ' ')) AS word
            WHERE length(word) > 2
        ), 0)";

        return $query->selectRaw("
        products.*,
        ts_rank_cd(search_vector, to_tsquery('simple', ?)) AS fts_score,
        similarity(name, ?) AS trigram_score,
        {$levenshteinSubquery} AS word_levenshtein_score,
        (
            0.4 * ts_rank_cd(search_vector, to_tsquery('simple', ?)) +
            0.3 * similarity(name, ?) +
            0.3 * {$levenshteinSubquery}
        ) AS total_score",
            [$tsquery, $term, $term, $tsquery, $term, $term]
        )
            ->where('status', 'active')
            ->where(function ($q) use ($tsquery, $term) {
                $q->whereRaw("ts_rank_cd(search_vector, to_tsquery('simple', ?)) > 0", [$tsquery])
                    ->orWhereRaw("similarity(name, ?) > 0.2", [$term])
                    ->orWhereRaw("
                    EXISTS (
                        SELECT 1
                        FROM unnest(string_to_array(regexp_replace(lower(name), '[^a-z0-9 ]', ' ', 'g'), ' ')) AS word
                        WHERE length(word) > 2 AND levenshtein(lower(word), lower(?)) <= 2
                    )", [$term]);
            })
            ->orderByDesc('total_score')
            ->limit($limit);
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
