<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductQuantity extends Model
{
    protected $fillable = [
        'product_id',
        'source_name',
        'quantity_data',
    ];

    protected function casts(): array
    {
        return [
            'quantity_data' => 'array',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
