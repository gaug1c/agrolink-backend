<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use MongoDB\Laravel\Eloquent\SoftDeletes;

class OrderItem extends Model
{
    use SoftDeletes;

    protected $connection = 'mongodb';
    protected $collection = 'order_items';

    protected $fillable = [
        'order_id',
        'product_id',
        'product_name',
        'product_sku',
        'product_image',
        'quantity',
        'price',
        'subtotal'
    ];

    // ✅ UTILISER FLOAT AU LIEU DE DECIMAL
    protected $casts = [
        'quantity' => 'integer',
        'price' => 'float',
        'subtotal' => 'float'
    ];

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id', '_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id', '_id');
    }

    // ✅ GÉRER DECIMAL128
    public function getPriceAttribute($value)
    {
        return $this->convertToFloat($value);
    }

    public function getSubtotalAttribute($value)
    {
        if ($value !== null) {
            return $this->convertToFloat($value);
        }

        $price = $this->price ?? ($this->product->discount_price ?? $this->product->price);
        return $this->quantity * $this->convertToFloat($price);
    }

    private function convertToFloat($value)
    {
        if ($value instanceof \MongoDB\BSON\Decimal128) {
            return (float) $value->__toString();
        }
        
        return $value ? (float) $value : 0;
    }
}