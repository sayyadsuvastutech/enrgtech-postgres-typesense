<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Brand extends Model
{
    use HasFactory;
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
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'manufacturer_id' => 'integer',
            'domain_id' => 'integer',
            'created_by' => 'integer',
            'updated_by' => 'integer',
        ];
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
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
