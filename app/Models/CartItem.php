<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use MongoDB\Laravel\Eloquent\SoftDeletes;

class CartItem extends Model
{
    use SoftDeletes;

    protected $connection = 'mongodb';
    protected $collection = 'cart_items';

    protected $fillable = [
        'cart_id',
        'product_id',
        'quantity'
    ];

    protected $casts = [
        'quantity' => 'integer'
    ];

    protected $appends = ['subtotal'];

    /* -------------------
     | Relations
     ------------------- */
    public function cart()
    {
        return $this->belongsTo(Cart::class, 'cart_id', '_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id', '_id');
    }

    /* -------------------
     | Accessors
     ------------------- */
    public function getSubtotalAttribute()
    {
        $price = $this->product->discount_price ?? $this->product->price;
        return $this->quantity * $price;
    }
}
