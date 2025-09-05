<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Laravel\Scout\Searchable;

class Product extends Model
{
    use HasFactory;
    use Searchable;

    protected $table = 'ioa_products';

    protected $fillable = [
        'name',
        'title',
        'pnum',
        'mf_pnum',
        'mf_pnum_slug',
        'description',
        'category_id',
        'manufacturer_id',
        'brand_id',
        'breadcrumb',
        'meta_title',
        'meta_description',
        'is_rohs_compliant',
        'pushed',
        'total_reviews',
        'average_rating',
        'video_url',
        'status_id',
        'is_updated',
        'sess_insrt_id',
        'sess_updt_id',
        'category_name',
        'manufacturer_name',
        'brand_name',
    ];

    protected function casts(): array
    {
        return [
            'average_rating' => 'decimal:2',
            'is_rohs_compliant' => 'boolean',
            'pushed' => 'boolean',
            'is_updated' => 'boolean',
            'total_reviews' => 'integer',
            'status_id' => 'integer',
            'sess_insrt_id' => 'integer',
            'sess_updt_id' => 'integer',
        ];
    }

    /**
     * Get the name of the index associated with the model.
     */
    public function searchableAs(): string
    {
        $environment = config('app.env', 'local');

        // Check if custom index name is configured
        $customIndexName = config("typesense.indexes.products.{$environment}");

        if ($customIndexName) {
            return $customIndexName;
        }

        // Fallback to default naming strategy
        $strategy = config('typesense.naming_strategy', 'environment');
        $prefix = config('typesense.prefix', '');
        $suffix = config('typesense.suffix', '');

        switch ($strategy) {
            case 'prefix':
                return $prefix.'products'.$suffix;

            case 'custom':
                return config('scout.prefix', '').'products_'.$environment;

            case 'environment':
            default:
                return "products_{$environment}";
        }
    }

    /**
     * Get the indexable data array for the model.
     *
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        // Ensure relationships are loaded to avoid N+1 queries
        $this->loadMissing([
            'category', 'manufacturer', 'brand', 'attributes',
            'prices', 'quantities', 'images', 'embedding',
        ]);

        // Extract attributes for searching and faceting
        $attributesList = [];
        $searchableAttributes = [];
        $attributeFacets = [];

        foreach ($this->attributes as $attr) {
            if (isset($attr->attributes) && is_array($attr->attributes)) {
                foreach ($attr->attributes as $key => $value) {
                    if (! empty($key) && ! empty($value)) {
                        $attributesList[] = "{$key}:{$value}";
                        $searchableAttributes[] = "{$key} {$value}";
                        $attributeFacets[] = $key; // For faceting by attribute types
                    }
                }
            }
        }

        // Get sources from prices
        $sources = $this->prices->pluck('source_name')->unique()->filter()->values()->toArray();

        // Calculate stock and pricing data
        $price = (float) $this->price;
        $stockQuantity = (int) $this->stock_quantity;
        $inStock = $this->isInStock();

        // Prepare searchable text content for AI embeddings
        $searchableContent = collect([
            $this->name,
            $this->title,
            $this->description,
            $this->category_name ?? $this->category?->name,
            $this->brand_name ?? $this->brand?->name,
            $this->manufacturer_name ?? $this->manufacturer?->name,
            implode(' ', $searchableAttributes),
        ])->filter()->implode(' ');

        // Get embedding vector if available
        $embeddingVector = null;
        if ($this->embedding && ! empty($this->embedding->embedding)) {
            $embeddingVector = is_string($this->embedding->embedding)
                ? json_decode($this->embedding->embedding, true)
                : $this->embedding->embedding;
        }

        return [
            'id' => (string) $this->id,
            'title' => (string) ($this->title ?? ''),
            'name' => (string) ($this->name ?? ''),
            'pnum' => (string) ($this->pnum ?? ''),
            'mf_pnum' => (string) ($this->mf_pnum ?? ''),
            'description' => (string) ($this->description ?? ''),
            'category_id' => (int) ($this->category_id ?? 0),
            'category_name' => (string) ($this->category_name ?? $this->category?->name ?? ''),
            'manufacturer_id' => (int) ($this->manufacturer_id ?? 0),
            'manufacturer_name' => (string) ($this->manufacturer_name ?? $this->manufacturer?->name ?? ''),
            'brand_id' => (int) ($this->brand_id ?? 0),
            'brand_name' => (string) ($this->brand_name ?? $this->brand?->name ?? ''),
            'price' => $price,
            'price_range' => $this->calculatePriceRange($price),
            'in_stock' => $inStock,
            'stock_quantity' => $stockQuantity,
            'is_rohs_compliant' => (bool) ($this->is_rohs_compliant ?? false),
            'average_rating' => (float) ($this->average_rating ?? 0.0),
            'total_reviews' => (int) ($this->total_reviews ?? 0),
            'breadcrumb' => (string) ($this->breadcrumb ?? ''),
            'attributes' => $attributesList,
            'attribute_types' => array_unique($attributeFacets),
            'searchable_attributes' => implode(' ', $searchableAttributes),
            'searchable_content' => $searchableContent,
            'sources' => $sources,
            'image_url' => (string) ($this->primary_image ?? '/images/place_holder.svg'),
            'created_at' => $this->created_at->timestamp ?? 0,
            'updated_at' => $this->updated_at->timestamp ?? 0,
            // AI embedding vector for semantic search
            'embedding_vector' => $embeddingVector,
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

    public function embedding(): HasOne
    {
        return $this->hasOne(Embedding::class);
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

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status_id', 2); // Assuming status_id 1 is active
    }

    public function scopeInStock(Builder $query): Builder
    {
        return $query->whereHas('quantities', function ($q) {
            $q->where('quantity', '>', 0)
                ->orWhere('availability_status', '!=', 'out_of_stock');
        });
    }

    public function scopePriceRange(Builder $query, ?float $min = null, ?float $max = null): Builder
    {
        if ($min !== null || $max !== null) {
            $query->whereHas('prices', function ($q) use ($min, $max) {
                if ($min !== null) {
                    $q->whereRaw("(pricing_ranges->0->>'price')::numeric >= ?", [$min]);
                }
                if ($max !== null) {
                    $q->whereRaw("(pricing_ranges->0->>'price')::numeric <= ?", [$max]);
                }
            });
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

    public function scopeByProductNumber(Builder $query, string $pnum): Builder
    {
        return $query->where('pnum', $pnum);
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
    public function scopeSearch2(Builder $query, string $term): Builder
    {
        $tsquery = implode(' & ', array_map(fn ($t) => $t.':*', explode(' ', $term)));
        $exactMatch = "%$term%";

        return $query->fromSub(function ($subQuery) use ($tsquery, $term, $exactMatch) {
            // Stage 1: Pre-filter with indexable conditions (FTS, trigram similarity, ILIKE)
            $subQuery->select('ioa_products.*')
                ->from('ioa_products')
                ->where('status_id', 2)
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

    public function isInStock(): bool
    {
        $totalStock = 0;
        $hasAvailableStock = false;

        foreach ($this->quantities as $quantity) {
            $quantityAmount = (int) $quantity->quantity;
            $totalStock += $quantityAmount;

            // Check if this quantity record indicates availability
            if ($quantityAmount > 0 && $quantity->availability_status !== 'out_of_stock') {
                $hasAvailableStock = true;
            }
        }

        // Product is in stock if it has both quantity > 0 AND at least one available quantity record
        return $totalStock > 0 && $hasAvailableStock;
    }

    public function isActive(): bool
    {
        return $this->status_id === 2; // Assuming status_id 1 is active
    }

    public function getStockQuantityAttribute(): int
    {
        $totalStock = 0;
        foreach ($this->quantities as $quantity) {
            $totalStock += (int) $quantity->quantity;
        }

        return $totalStock;
    }

    public function getPriceAttribute(): float
    {
        // Get the lowest price from all sources
        $lowestPrice = null;
        foreach ($this->prices as $price) {
            $priceRanges = $price->pricing_ranges;
            $currentPrice = null;

            // Handle pricing ranges structure
            if (is_array($priceRanges) && ! empty($priceRanges)) {
                // Get the first (lowest quantity) price range
                $firstRange = $priceRanges[0] ?? null;
                if ($firstRange && isset($firstRange['price'])) {
                    $currentPrice = (float) $firstRange['price'];
                }
            }

            if ($currentPrice !== null && ($lowestPrice === null || $currentPrice < $lowestPrice)) {
                $lowestPrice = $currentPrice;
            }
        }

        return $lowestPrice ?? 0.00;
    }

    public function getSkuAttribute(): string
    {
        return $this->pnum ?? $this->mf_pnum ?? '';
    }

    public function getProductNumberAttribute(): string
    {
        return $this->pnum ?? '';
    }

    public function getManufacturerProductNumberAttribute(): string
    {
        return $this->mf_pnum ?? '';
    }

    public function getBrandNameAttribute(): ?string
    {
        return $this->brand_name ?? $this->brand?->name;
    }

    public function getManufacturerNameAttribute(): ?string
    {
        return $this->manufacturer_name ?? $this->manufacturer?->name;
    }

    public function getCategoryNameAttribute(): ?string
    {
        return $this->category_name ?? $this->category?->name;
    }

    public function getAllAttributesAttribute(): array
    {
        $allAttributes = [];
        foreach ($this->attributes as $attribute) {
            if (isset($attribute->attributes) && is_array($attribute->attributes)) {
                $allAttributes = array_merge($allAttributes, $attribute->attributes);
            }
        }

        return $allAttributes;
    }

    public function getPrimaryImageAttribute(): ?string
    {
        $firstImage = $this->images->first();
        if ($firstImage && isset($firstImage->images[0]['path'])) {
            return $firstImage->images[0]['path'];
        }

        return null;
    }

    private function calculatePriceRange(float $price): string
    {
        $priceRanges = [
            ['min' => 0, 'max' => 10, 'value' => '0-10'],
            ['min' => 10, 'max' => 50, 'value' => '10-50'],
            ['min' => 50, 'max' => 100, 'value' => '50-100'],
            ['min' => 100, 'max' => 250, 'value' => '100-250'],
            ['min' => 250, 'max' => 500, 'value' => '250-500'],
            ['min' => 500, 'max' => 1000, 'value' => '500-1000'],
            ['min' => 1000, 'max' => null, 'value' => '1000+'],
        ];

        foreach ($priceRanges as $range) {
            if ($price >= $range['min'] && ($range['max'] === null || $price < $range['max'])) {
                return $range['value'];
            }
        }

        return '1000+'; // Fallback for very high prices
    }
}
