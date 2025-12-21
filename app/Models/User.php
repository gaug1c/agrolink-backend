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

    protected $fillable = [
        'name','email','password','phone','avatar','role','status',
        'address','city','postal_code','country','region','bio',
        'business_name','business_registration','tax_id','bank_account',
        'mobile_money_number','is_verified','email_verified_at',
        'phone_verified_at','last_login_at'
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'phone_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'is_verified' => 'boolean',
        // 'password' => 'hashed',
    ];

     public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }

    // Exemple d’accesseurs et méthodes déjà présentes
    public function getFullNameAttribute() { return $this->name; }
    public function isProducer() { return $this->role === 'producer'; }
    public function isConsumer() { return $this->role === 'consumer'; }
    public function isAdmin() { return $this->role === 'admin'; }

    // Relations
    public function products() { return $this->hasMany(Product::class, 'producer_id'); }
    public function orders() { return $this->hasMany(Order::class); }
    public function cart() { return $this->hasOne(Cart::class); }
    public function favorites() { return $this->hasMany(Favorite::class); }


}
