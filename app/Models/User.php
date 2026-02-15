<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use MongoDB\Laravel\Auth\User as MongoAuthenticatable;
use Tymon\JWTAuth\Contracts\JWTSubject;
use App\Notifications\ResetPasswordNotification;

class User extends MongoAuthenticatable implements JWTSubject
{
    use HasFactory, Notifiable;

    protected $connection = 'mongodb';
    protected $collection = 'users';

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'password',
        'phone',
        'avatar',

        'role',
        'status',
        'is_verified',

        'address',
        'city',
        'postal_code',
        'country',
        'region',

        'business_name',
        'province',
        'production_city',
        'production_village',
        'production_types',
        'other_production',
        'cultivated_area',
        'area_unit',
        'available_quantity',
        'is_whatsapp',
        'delivery_available',
        'identity_document',

        'email_verified_at',
        'phone_verified_at',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'phone_verified_at' => 'datetime',
        'last_login_at'     => 'datetime',
        'is_verified'       => 'boolean',
        'is_whatsapp'       => 'boolean',
        'production_types'  => 'array',
    ];

    /**
     * 🔐 JWT IDENTIFIER
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * 🔐 JWT CUSTOM CLAIMS
     */
    public function getJWTCustomClaims(): array
    {
        return [
            'role' => $this->role,
            'email' => $this->email,
        ];
    }

    public function isProducer(): bool
    {
        return $this->role === 'producer';
    }

    public function isConsumer(): bool
    {
        return $this->role === 'consumer';
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }
    public function sendPasswordResetNotification($token)
    {
        $this->notify(new ResetPasswordNotification($token));
    }
}
