<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Embedding extends Model
{
    protected $table = 'ioa_product_embeddings';

    protected $fillable = [
      'product_id',
      'product_text',
      'embedding',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

}
