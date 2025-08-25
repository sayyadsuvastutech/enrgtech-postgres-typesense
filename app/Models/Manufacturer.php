<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Manufacturer extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'slug',
        'description',
        'logo',
        'banner',
        'meta_title',
        'meta_description',
        'is_pushed',
        'website',
        'total_reviews',
        'products_count',
        'average_rating',
        'status_id',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_pushed' => 'boolean',
            'total_reviews' => 'integer',
            'products_count' => 'integer',
            'average_rating' => 'decimal:2',
            'status_id' => 'integer',
            'created_by' => 'integer',
            'updated_by' => 'integer',
        ];
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
    
    public function scopePopular($query, int $limit = 10)
    {
        return $query->withCount('products')
            ->orderBy('products_count', 'desc')
            ->limit($limit);
    }
}
