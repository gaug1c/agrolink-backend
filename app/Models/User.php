<?php

namespace App\Models;

use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Tymon\JWTAuth\Contracts\JWTSubject;
use MongoDB\Laravel\Eloquent\Model as Eloquent;
use Illuminate\Auth\Authenticatable as BaseAuthenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;

class User extends Eloquent implements JWTSubject, AuthenticatableContract
{
    use HasApiTokens, Notifiable, BaseAuthenticatable;

    protected $connection = 'mongodb';
    protected $collection = 'users';

    /**
     * Champs autorisés à l’écriture
     */
    protected $fillable = [

        // Identité
        'first_name',
        'last_name',
        'email',
        'password',
        'phone',
        'avatar',

        // Rôle & statut
        'role',           // consumer | producer | admin
        'status',         // active | suspended
        'is_verified',

        // Localisation
        'address',
        'city',
        'postal_code',
        'country',
        'region',

        // Producteur
        'business_name',
        'province',
        'production_city',
        'production_village',
        'production_types',      // array/json
        'identity_document',     // file path
        'mobile_money_number',
        'bank_account',
        'tax_id',

        // Métadonnées
        'email_verified_at',
        'phone_verified_at',
        'last_login_at',
    ];

    /**
     * Champs cachés
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Casts
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'phone_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'is_verified' => 'boolean',
        'production_types' => 'array',
    ];

    /* -----------------------------------------------------------------
     | JWT
     |-----------------------------------------------------------------*/
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }

    /* -----------------------------------------------------------------
     | Accessors
     |-----------------------------------------------------------------*/

    /**
     * Nom complet (compatibilité)
     */
    public function getNameAttribute()
    {
        return trim(($this->first_name ?? '') . ' ' . ($this->last_name ?? ''));
    }

    /**
     * Alias explicite
     */
    public function getFullNameAttribute()
    {
        return $this->name;
    }

    /* -----------------------------------------------------------------
     | Helpers rôle
     |-----------------------------------------------------------------*/
    public function isProducer()
    {
        return $this->role === 'producer';
    }

    public function isConsumer()
    {
        return $this->role === 'consumer';
    }

    public function isAdmin()
    {
        return $this->role === 'admin';
    }

    /* -----------------------------------------------------------------
     | Relations
     |-----------------------------------------------------------------*/
    public function products()
    {
        return $this->hasMany(Product::class, 'producer_id');
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function cart()
    {
        return $this->hasOne(Cart::class);
    }

    public function favorites()
    {
        return $this->hasMany(Favorite::class);
    }
}
