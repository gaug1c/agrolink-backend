<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model; // ✅ Nouveau package MongoDB
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Review extends Model
{
    use HasFactory;

    // Connexion MongoDB et collection
    protected $connection = 'mongodb';
    protected $collection = 'reviews';

    protected $fillable = [
        'user_id',
        'product_id',
        'order_id',
        'rating',
        'comment',
        'is_verified_purchase',
        'helpful_count',
        'images'
    ];

    protected $casts = [
        'rating' => 'integer',
        'is_verified_purchase' => 'boolean',
        'helpful_count' => 'integer',
        'images' => 'array'
    ];

    // Relations
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', '_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id', '_id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id', '_id');
    }

    // Scopes
    public function scopeVerified($query)
    {
        return $query->where('is_verified_purchase', true);
    }

    public function scopeByProduct($query, $productId)
    {
        return $query->where('product_id', $productId);
    }

    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByRating($query, $rating)
    {
        return $query->where('rating', $rating);
    }

    public function scopeRecent($query)
    {
        return $query->orderBy('created_at', 'desc');
    }

    // Méthode helper pour calculer la moyenne des notes d'un produit
    public static function averageRatingForProduct($productId)
    {
        return self::where('product_id', $productId)->avg('rating') ?? 0;
    }

    // Méthode helper pour compter les avis d'un produit
    public static function countForProduct($productId)
    {
        return self::where('product_id', $productId)->count();
    }
}