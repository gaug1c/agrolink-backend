<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use MongoDB\Laravel\Eloquent\SoftDeletes;

class Order extends Model
{
    use SoftDeletes;

    protected $connection = 'mongodb';
    protected $collection = 'orders';

    protected $fillable = [
        'user_id',
        'order_number',
        'subtotal',
        'shipping_cost',
        'tax',
        'total_amount',
        'status',
        'payment_status',
        'payment_method',
        'shipping_address',
        'shipping_city',
        'shipping_postal_code',
        'shipping_country',
        'phone',
        'delivery_instructions',
        'tracking_number',
        'estimated_delivery_date',
        'confirmed_at',
        'processing_at',
        'shipped_at',
        'delivered_at',
        'cancelled_at',
        'cancellation_reason',
        'ip_address',
        'user_agent',
        'converted_to_order_id',
        'converted_at'
    ];

    // ❌ SUPPRIMEZ CES CASTS DECIMAL QUI CAUSENT L'ERREUR
    // protected $casts = [
    //     'subtotal' => 'decimal:2',
    //     'shipping_cost' => 'decimal:2',
    //     'tax' => 'decimal:2',
    //     'total_amount' => 'decimal:2',
    // ];

    // ✅ UTILISEZ FLOAT À LA PLACE
    protected $casts = [
        'subtotal' => 'float',
        'shipping_cost' => 'float',
        'tax' => 'float',
        'total_amount' => 'float',
        'confirmed_at' => 'datetime',
        'processing_at' => 'datetime',
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'estimated_delivery_date' => 'datetime',
        'converted_at' => 'datetime',
    ];

    protected $appends = [
        'status_label',
        'payment_status_label',
        'can_be_cancelled'
    ];

    // ✅ AJOUTEZ CES ACCESSEURS POUR GÉRER DECIMAL128
    public function getSubtotalAttribute($value)
    {
        return $this->convertToFloat($value);
    }

    public function getShippingCostAttribute($value)
    {
        return $this->convertToFloat($value);
    }

    public function getTaxAttribute($value)
    {
        return $this->convertToFloat($value);
    }

    public function getTotalAmountAttribute($value)
    {
        return $this->convertToFloat($value);
    }

    // Helper pour convertir Decimal128
    private function convertToFloat($value)
    {
        if ($value instanceof \MongoDB\BSON\Decimal128) {
            return (float) $value->__toString();
        }
        
        return $value ? (float) $value : 0;
    }

    // ... reste du code inchangé
    
    const STATUS_PENDING = 'pending';
    const STATUS_CONFIRMED = 'confirmed';
    const STATUS_PROCESSING = 'processing';
    const STATUS_SHIPPED = 'shipped';
    const STATUS_DELIVERED = 'delivered';
    const STATUS_CANCELLED = 'cancelled';

    const PAYMENT_PENDING = 'pending';
    const PAYMENT_PAID = 'paid';
    const PAYMENT_FAILED = 'failed';
    const PAYMENT_REFUNDED = 'refunded';

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', '_id');
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class, 'order_id', '_id');
    }

    public function payment()
    {
        return $this->hasOne(Payment::class, 'order_id', '_id');
    }

    public function statusHistories()
    {
        return $this->hasMany(OrderStatusHistory::class, 'order_id', '_id');
    }

    public function getStatusLabelAttribute()
    {
        $labels = [
            'pending' => 'En attente',
            'confirmed' => 'Confirmée',
            'processing' => 'En préparation',
            'shipped' => 'Expédiée',
            'delivered' => 'Livrée',
            'cancelled' => 'Annulée'
        ];

        return $labels[$this->status] ?? $this->status;
    }

    public function getPaymentStatusLabelAttribute()
    {
        $labels = [
            'pending' => 'En attente',
            'paid' => 'Payée',
            'failed' => 'Échouée',
            'refunded' => 'Remboursée'
        ];

        return $labels[$this->payment_status] ?? $this->payment_status;
    }

    public function getCanBeCancelledAttribute()
    {
        return in_array($this->status, [
            self::STATUS_PENDING, 
            self::STATUS_CONFIRMED, 
            self::STATUS_PROCESSING
        ]);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeConfirmed($query)
    {
        return $query->where('status', self::STATUS_CONFIRMED);
    }

    public function scopeProcessing($query)
    {
        return $query->where('status', self::STATUS_PROCESSING);
    }

    public function scopeShipped($query)
    {
        return $query->where('status', self::STATUS_SHIPPED);
    }

    public function scopeDelivered($query)
    {
        return $query->where('status', self::STATUS_DELIVERED);
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', self::STATUS_CANCELLED);
    }

    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeRecent($query)
    {
        return $query->orderBy('created_at', 'desc');
    }

    public function updateStatus($newStatus, $reason = null)
    {
        $oldStatus = $this->status;
        $this->status = $newStatus;

        switch ($newStatus) {
            case self::STATUS_CONFIRMED:
                $this->confirmed_at = now();
                break;
            case self::STATUS_PROCESSING:
                $this->processing_at = now();
                break;
            case self::STATUS_SHIPPED:
                $this->shipped_at = now();
                break;
            case self::STATUS_DELIVERED:
                $this->delivered_at = now();
                break;
            case self::STATUS_CANCELLED:
                $this->cancelled_at = now();
                $this->cancellation_reason = $reason;
                break;
        }

        $this->save();

        $this->statusHistories()->create([
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'reason' => $reason
        ]);

        return $this;
    }

    public function markAsPaid()
    {
        $this->payment_status = self::PAYMENT_PAID;
        $this->save();
        return $this;
    }

    public function canBeCancelled()
    {
        return $this->can_be_cancelled;
    }

    public function getTotalItems()
    {
        return $this->items->sum('quantity');
    }

    public function getProducers()
    {
        return $this->items->map(function($item) {
            return $item->product->producer;
        })->unique('_id');
    }
}