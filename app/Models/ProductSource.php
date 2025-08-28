<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductSource extends Model
{
    use HasFactory;

    protected $table = 'ioa_product_sources';

    protected $fillable = [
        'product_id',
        'source_name',
        'source_product_id',
        'source_url',
        'source_data',
    ];

    protected function casts(): array
    {
        return [
            'source_product_id' => 'integer',
            'source_data' => 'array',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
