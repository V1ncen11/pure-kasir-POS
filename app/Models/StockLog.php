<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id', 'user_id', 'transaction_id',
        'type', 'qty_before', 'qty_change', 'qty_after', 'notes',
    ];

    protected $casts = [
        'qty_before' => 'integer',
        'qty_change' => 'integer',
        'qty_after'  => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}
