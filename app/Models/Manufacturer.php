<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Manufacturer extends Model
{
    use HasFactory;

    protected $table = 'ioa_manufacturers';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'logo',
        'banner',
        'meta_title',
        'meta_description',
        'pushed',
        'website',
        'total_reviews',
        'products_count',
        'average_rating',
        'status_id',
    ];

    protected function casts(): array
    {
        return [
            'pushed' => 'boolean',
            'total_reviews' => 'integer',
            'products_count' => 'integer',
            'average_rating' => 'decimal:2',
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

    public function brands(): HasMany
    {
        return $this->hasMany(Brand::class);
    }
    
    public function scopePopular($query, int $limit = 10)
    {
        return $query->withCount('products')
            ->orderBy('products_count', 'desc')
            ->limit($limit);
    }
}
