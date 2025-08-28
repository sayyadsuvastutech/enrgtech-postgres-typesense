<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductQuantity extends Model
{
    use HasFactory;

    protected $table = 'ioa_product_quantities';

    protected $fillable = [
        'product_id',
        'source_name',
        'unit',
        'quantity',
        'availability_status',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
