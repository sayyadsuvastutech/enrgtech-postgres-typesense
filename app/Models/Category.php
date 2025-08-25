<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Category extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'category_id',
        'name',
        'slug',
        'image',
        'image_url',
        'description',
        'meta_title',
        'meta_description',
        'popular_items',
        'new_items',
        'status_id',
        'domain_id',
        'created_by',
        'updated_by',
    ];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    protected function casts(): array
    {
        return [
            'category_id' => 'integer',
            'status_id' => 'integer',
            'domain_id' => 'integer',
            'created_by' => 'integer',
            'updated_by' => 'integer',
        ];
    }

    public function scopeWithProducts($query)
    {
        return $query->has('products');
    }
}
