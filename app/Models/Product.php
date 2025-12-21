<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

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
        'reviews_count'
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'discount_price' => 'decimal:2',
        'shipping_cost' => 'decimal:2',
        'stock' => 'integer',
        'min_order_quantity' => 'integer',
        'views' => 'integer',
        'reviews_count' => 'integer',
        'rating' => 'decimal:1',
        'available_for_delivery' => 'boolean',
        'available_for_pickup' => 'boolean',
        'is_featured' => 'boolean',
        'harvest_date' => 'date',
        'expiry_date' => 'date',
        'images' => 'array'
    ];

    protected $appends = [
        'final_price',
        'discount_percentage',
        'is_in_stock',
        'is_fresh'
    ];

    /**
     * Relations
     */
    public function producer()
    {
        return $this->belongsTo(User::class, 'producer_id');
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function cartItems()
    {
        return $this->hasMany(CartItem::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function favorites()
    {
        return $this->hasMany(Favorite::class);
    }

    /**
     * Accesseurs
     */
    public function getFinalPriceAttribute()
    {
        return $this->discount_price ?? $this->price;
    }

    public function getDiscountPercentageAttribute()
    {
        if ($this->discount_price && $this->price > 0) {
            return round((($this->price - $this->discount_price) / $this->price) * 100);
        }
        return 0;
    }

    public function getIsInStockAttribute()
    {
        return $this->stock > 0;
    }

    public function getIsFreshAttribute()
    {
        if (!$this->harvest_date) {
            return false;
        }
        return $this->harvest_date->diffInDays(now()) <= 3;
    }

    public function getFirstImageAttribute()
    {
        if (!empty($this->images) && is_array($this->images)) {
            return asset('storage/' . $this->images[0]);
        }
        return asset('images/default-product.png');
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeInStock($query)
    {
        return $query->where('stock', '>', 0);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopeByRegion($query, $region)
    {
        return $query->where('region', $region);
    }

    public function scopeByCity($query, $city)
    {
        return $query->where('city', $city);
    }

    public function scopeOnSale($query)
    {
        return $query->whereNotNull('discount_price');
    }

    public function scopeFresh($query)
    {
        return $query->whereDate('harvest_date', '>=', now()->subDays(3));
    }

    /**
     * Méthodes utilitaires
     */
    public function updateRating()
    {
        $this->rating = $this->reviews()->avg('rating');
        $this->reviews_count = $this->reviews()->count();
        $this->save();
    }

    public function isAvailable()
    {
        return $this->status === 'active' && $this->stock > 0;
    }

    public function canOrder($quantity)
    {
        return $this->stock >= $quantity && $quantity >= ($this->min_order_quantity ?? 1);
    }
}