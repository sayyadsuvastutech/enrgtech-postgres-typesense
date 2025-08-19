<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'pnum',
        'oth_id',
        'mf_pnum',
        'mf_pnum_norm',
        'mf_pnum_list',
        'name',
        'description',
        'description_ext',
        'mf_keys',
        'manufacturer_id',
        'category_id',
        'pricing_all',
        'quantity_all',
        'rohs_all',
        'attributes_list_all',
        'attributes_list_all_filter',
        'images_all',
        'document_list_all',
        'sources_all',
        'sitemap',
        'categories_all',
        'categories_all_filters',
        'related_links',
        'prod_redirect_to',
        'status',
        'oth_source',
        'uom_message',
        'country_of_origin',
        'is_active',
        'sess_ins_id',
        'sess_upd_id',
        'last_updated_by',
        'Last_updated',
        'api_last_update',
        'api_last_response_status',
    ];

    protected function casts(): array
    {
        return [
            'oth_id' => 'integer',
            'manufacturer_id' => 'integer',
            'category_id' => 'integer',
            'mf_pnum_list' => 'array',
            'mf_keys' => 'array',
            'pricing_all' => 'array',
            'quantity_all' => 'array',
            'rohs_all' => 'boolean',
            'attributes_list_all' => 'array',
            'attributes_list_all_filter' => 'array',
            'images_all' => 'array',
            'document_list_all' => 'array',
            'sources_all' => 'array',
            'categories_all' => 'array',
            'categories_all_filters' => 'array',
            'related_links' => 'array',
            'prod_redirect_to' => 'array',
            'status' => 'integer',
            'is_active' => 'boolean',
            'sess_ins_id' => 'integer',
            'sess_upd_id' => 'integer',
            'api_last_update' => 'integer',
            'api_last_response_status' => 'boolean',
        ];
    }

    public function manufacturer(): BelongsTo
    {
        return $this->belongsTo(Manufacturer::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeInStock(Builder $query): Builder
    {
        return $query->whereNotNull('quantity_all')
            ->where(function ($query) {
                $query->whereRaw('quantity_all::jsonb != \'{}\'::jsonb');
            });
    }

    public function scopeWithPricing(Builder $query): Builder
    {
        return $query->whereNotNull('pricing_all')
            ->where(function ($query) {
                $query->whereRaw('pricing_all::jsonb != \'{}\'::jsonb');
            });
    }

    public function scopeSearch(Builder $query, string $term): Builder
    {
        return $query->where(function ($query) use ($term) {
            $query->where('name', 'LIKE', "%{$term}%")
                ->orWhere('description', 'LIKE', "%{$term}%")
                ->orWhere('mf_pnum', 'LIKE', "%{$term}%")
                ->orWhere('mf_pnum_norm', 'LIKE', "%{$term}%")
                ->orWhere('pnum', 'LIKE', "%{$term}%");
        });
    }

    public function scopeByCategory(Builder $query, $categoryId): Builder
    {
        return $query->where('category_id', $categoryId);
    }

    public function scopeByManufacturer(Builder $query, $manufacturerId): Builder
    {
        return $query->where('manufacturer_id', $manufacturerId);
    }

    public function scopeBySource(Builder $query, string $source): Builder
    {
        return $query->where('oth_source', $source);
    }

    public function scopeRohsCompliant(Builder $query): Builder
    {
        return $query->where('rohs_all', true);
    }

    public function getPrimaryImageAttribute(): ?string
    {
        if (!$this->images_all || empty($this->images_all)) {
            return null;
        }

        if (is_array($this->images_all)) {
            // Handle nested structure with source keys (e.g., "rs", "element14", etc.)
            foreach ($this->images_all as $sourceImages) {
                if (is_array($sourceImages) && !empty($sourceImages)) {
                    $firstImage = is_array($sourceImages) ? $sourceImages[0] : $sourceImages;
                    
                    if (is_array($firstImage)) {
                        // Try different possible image URL keys
                        $imageUrl = $firstImage['original'] ?? $firstImage['url'] ?? $firstImage['path'] ?? null;
                        
                        // Handle relative paths by making them absolute URLs where possible
                        if ($imageUrl && !str_starts_with($imageUrl, 'http')) {
                            // For paths like "full/xyz.jpg", we'd need the base URL from the source
                            // For now, return as-is, but could be enhanced later
                            return $imageUrl;
                        }
                        
                        return $imageUrl;
                    } elseif (is_string($firstImage)) {
                        return $firstImage;
                    }
                }
            }
        }

        return null;
    }

    public function getLowestPriceAttribute(): ?float
    {
        if (!$this->pricing_all || empty($this->pricing_all)) {
            return null;
        }

        $pricing = $this->pricing_all;

        if (isset($pricing['ranges']) && is_array($pricing['ranges'])) {
            $prices = collect($pricing['ranges'])->pluck('price')->filter();

            return $prices->min();
        }

        return null;
    }

    public function getAttributesAttribute(): array
    {
        return $this->attributes_list_all ?? [];
    }

    public function getDocumentsAttribute(): array
    {
        return $this->document_list_all ?? [];
    }

    public function getImagesAttribute(): array
    {
        return $this->images_all ?? [];
    }

    public function getCategoriesAttribute(): array
    {
        return $this->categories_all ?? [];
    }

    public function hasStock(): bool
    {
        return !empty($this->quantity_all);
    }

    public function hasPricing(): bool
    {
        return !empty($this->pricing_all);
    }

    public function isRohsCompliant(): bool
    {
        return $this->rohs_all === true;
    }

    public function getRouteKeyName(): string
    {
        return 'pnum';
    }
}
