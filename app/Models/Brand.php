<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Brand extends Model
{
    use HasFactory;

    protected $table = 'ioa_brands';

    protected $fillable = [
        'manufacturer_id',
        'name',
        'slug',
        'logo',
        'banner',
        'description',
        'website',
        'type',
        'size',
        'location',
        'founded',
        'specialties',
        'meta_title',
        'meta_description',
        'popular_items',
        'new_items',
        'domain_id',
        'status_id',
    ];

    protected function casts(): array
    {
        return [
            'manufacturer_id' => 'integer',
            'domain_id' => 'integer',
            'status_id' => 'integer',
        ];
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class);
    }

    public function manufacturer(): BelongsTo
    {
        return $this->belongsTo(Manufacturer::class);
    }

    public function scopeWithProducts($query)
    {
        return $query->has('products');
    }

    public function scopePopular($query, int $limit = 10)
    {
        return $query->withCount('products')
            ->orderBy('products_count', 'desc')
            ->limit($limit);
    }
}
