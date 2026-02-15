<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use MongoDB\Laravel\Eloquent\SoftDeletes;

class Cart extends Model
{
    use SoftDeletes;

    protected $connection = 'mongodb';
    protected $collection = 'carts';

    protected $fillable = [
        'user_id',
        'converted_to_order_id',
        'converted_at'
    ];

    protected $appends = ['total_items', 'subtotal'];

    protected $casts = [
        'converted_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', '_id');
    }

    public function items()
    {
        return $this->hasMany(CartItem::class, 'cart_id', '_id');
    }

    public function getTotalItemsAttribute()
    {
        return $this->items->sum('quantity');
    }

    public function getSubtotalAttribute()
    {
        return $this->items->sum(function ($item) {
            $price = $item->product->discount_price ?? $item->product->price;
            // ✅ Convertir Decimal128
            if ($price instanceof \MongoDB\BSON\Decimal128) {
                $price = (float) $price->__toString();
            }
            return $item->quantity * (float) $price;
        });
    }

    public function clear()
    {
        $this->items()->delete();
    }
}