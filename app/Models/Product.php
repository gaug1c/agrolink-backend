<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use MongoDB\Laravel\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes;

    protected $connection = 'mongodb';
    protected $collection = 'products';

    protected $fillable = [
        'producer_id',
        'category_id',
        'name',
        'local_name',
        'slug',
        'description',
        'price',
        'discount_price',
        'unit',
        'stock',
        'min_order_quantity',
        'images',
        'region',
        'city',
        'type',
        'harvest_date',
        'expiry_date',
        'shipping_cost',
        'available_for_delivery',
        'available_for_pickup',
        'status',
        'is_featured',
        'views',
        'rating',
        'reviews_count',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'discount_price' => 'decimal:2',
        'shipping_cost' => 'decimal:2',
        'stock' => 'integer',
        'views' => 'integer',
        'rating' => 'decimal:1',
        'available_for_delivery' => 'boolean',
        'available_for_pickup' => 'boolean',
        'is_featured' => 'boolean',
        'images' => 'array',
        'harvest_date' => 'datetime',
        'expiry_date' => 'datetime',
    ];

    /* Relations */

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id', '_id');
    }

    public function producer()
    {
        return $this->belongsTo(User::class, 'producer_id', '_id')
                    ->where('role', 'producer');
    }

    /* Scopes */

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeInStock($query)
    {
        return $query->where('stock', '>', 0);
    }

    public function scopeOnSale($query)
    {
        return $query->whereNotNull('discount_price');
    }

    /* Accessors */

    public function getIsAvailableAttribute()
    {
        return $this->status === 'active' && $this->stock > 0;
    }
}
