<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model; // ✅ Nouveau package MongoDB
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Payment extends Model
{
    use HasFactory;

    // Connexion MongoDB et collection
    protected $connection = 'mongodb';
    protected $collection = 'payments';

    protected $fillable = [
        'order_id',
        'transaction_id',
        'payment_method',
        'amount',
        'status',
        'payment_details',
        'paid_at'
    ];

    // ✅ IMPORTANT: Utiliser 'float' au lieu de 'decimal:2' pour MongoDB
    protected $casts = [
        'amount' => 'float',
        'payment_details' => 'array',
        'paid_at' => 'datetime'
    ];

    // Constantes de statut
    const STATUS_PENDING = 'pending';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
    const STATUS_REFUNDED = 'refunded';
    const STATUS_CANCELLED = 'cancelled';

    // Relation vers la commande
    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id', '_id');
    }

    // ✅ Gérer Decimal128 si nécessaire
    public function getAmountAttribute($value)
    {
        if ($value instanceof \MongoDB\BSON\Decimal128) {
            return (float) $value->__toString();
        }
        
        return $value ? (float) $value : 0;
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    public function scopeByOrder($query, $orderId)
    {
        return $query->where('order_id', $orderId);
    }
}