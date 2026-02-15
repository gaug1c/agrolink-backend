<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use Illuminate\Support\Carbon;

class PasswordReset extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'password_resets';
    public $timestamps = false; // on gère created_at manuellement

    protected $fillable = [
        'email',
        'token',
        'created_at',
    ];
}
