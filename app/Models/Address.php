<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model; // ✅ Nouveau package MongoDB
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Address extends Model
{
    use HasFactory;

    // Connexion MongoDB
    protected $connection = 'mongodb';
    protected $collection = 'addresses';

    protected $fillable = [
        'user_id',
        'label',
        'address',
        'city',
        'postal_code',
        'country',
        'phone',
        'is_default'
    ];

    protected $casts = [
        'is_default' => 'boolean'
    ];

    // Relation vers l'utilisateur
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', '_id');
    }

    // Scope pour récupérer l'adresse par défaut
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    // Scope pour récupérer les adresses d'un utilisateur
    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }
}