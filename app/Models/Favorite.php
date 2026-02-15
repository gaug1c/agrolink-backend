<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model; // ✅ Nouveau package MongoDB
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Favorite extends Model
{
    use HasFactory;

    // Connexion MongoDB et collection
    protected $connection = 'mongodb';
    protected $collection = 'favorites';

    protected $fillable = [
        'user_id',
        'product_id'
    ];

    // Relation vers l'utilisateur
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', '_id');
    }

    // Relation vers le produit
    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id', '_id');
    }

    // Scope pour vérifier si un produit est favori
    public function scopeByUserAndProduct($query, $userId, $productId)
    {
        return $query->where('user_id', $userId)
                     ->where('product_id', $productId);
    }

    // Scope pour les favoris d'un utilisateur
    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }
}