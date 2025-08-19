<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Manufacturer extends Model
{
    use HasFactory;

    protected $fillable = [
        'mf_id',
        'name',
        'slug',
        'description',
        'website',
        'logo_url',
        'contact_info',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'mf_id' => 'integer',
            'contact_info' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function activeProducts(): HasMany
    {
        return $this->products()->where('products.is_active', true);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeSearch(Builder $query, string $term): Builder
    {
        return $query->where(function ($query) use ($term) {
            $query->where('name', 'LIKE', "%{$term}%")
                ->orWhere('description', 'LIKE', "%{$term}%");
        });
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($manufacturer) {
            if (empty($manufacturer->slug)) {
                $manufacturer->slug = Str::slug($manufacturer->name);
            }
        });

        static::updating(function ($manufacturer) {
            if ($manufacturer->isDirty('name') && empty($manufacturer->slug)) {
                $manufacturer->slug = Str::slug($manufacturer->name);
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function getProductCountAttribute(): int
    {
        return $this->activeProducts()->count();
    }
}
