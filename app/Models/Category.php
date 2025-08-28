<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Category extends Model
{
    use HasFactory;

    protected $table = 'ioa_categories';
    
    protected $fillable = [
        'name',
        'slug',
        'description',
        'image',
        'parent_category',
        'is_main',
        'pushed',
        'meta_title',
        'meta_description',
        'status_id',
        'products_count',
    ];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class);
    }

    protected function casts(): array
    {
        return [
            'parent_category' => 'integer',
            'is_main' => 'boolean',
            'pushed' => 'boolean',
            'status_id' => 'integer',
            'products_count' => 'integer',
        ];
    }

    // Recursive relationships
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_category');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_category');
    }

    public function allChildren(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_category')->with('allChildren');
    }

    public function ancestors()
    {
        $ancestors = collect();
        $parent = $this->parent;

        while ($parent) {
            $ancestors->prepend($parent);
            $parent = $parent->parent;
        }

        return $ancestors;
    }

    public function descendants()
    {
        $descendants = collect();
        
        foreach ($this->children as $child) {
            $descendants->push($child);
            $descendants = $descendants->merge($child->descendants());
        }

        return $descendants;
    }

    // Scopes for common queries
    public function scopeMain($query)
    {
        return $query->where('is_main', true);
    }

    public function scopePushed($query)
    {
        return $query->where('pushed', true);
    }

    public function scopeRoot($query)
    {
        return $query->whereNull('parent_category');
    }

    public function scopeWithChildren($query)
    {
        return $query->with('children');
    }

    public function scopeWithAllChildren($query)
    {
        return $query->with('allChildren');
    }

    public function scopeWithProducts($query)
    {
        return $query->has('products');
    }

    public function scopeByParent($query, $parentId)
    {
        return $query->where('parent_category', $parentId);
    }

    public function scopePopular($query, int $limit = 10)
    {
        return $query->orderBy('products_count', 'desc')->limit($limit);
    }

    // Helper methods
    public function isRoot(): bool
    {
        return is_null($this->parent_category);
    }

    public function hasChildren(): bool
    {
        return $this->children()->count() > 0;
    }

    public function getDepth(): int
    {
        return $this->ancestors()->count();
    }

    public function getBreadcrumb(): string
    {
        $breadcrumb = $this->ancestors()->pluck('name')->implode(' > ');
        return $breadcrumb ? $breadcrumb . ' > ' . $this->name : $this->name;
    }

    // Tree building method
    public static function getTree()
    {
        $allCategories = static::with('children')->get();
        
        return $allCategories->filter(function ($category) {
            return $category->isRoot();
        })->values();
    }
}
