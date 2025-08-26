<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductPrice extends Model
{
    use HasFactory;
    protected $fillable = [
        'product_id',
        'source_name',
        'pricing_ranges',
        'currency',
        'unit',
    ];

    protected function casts(): array
    {
        return [
            'pricing_ranges' => 'array',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
