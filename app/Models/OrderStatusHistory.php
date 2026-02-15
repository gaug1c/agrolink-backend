<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use MongoDB\Laravel\Eloquent\SoftDeletes;

class OrderStatusHistory extends Model
{
    use SoftDeletes;

    protected $connection = 'mongodb';
    protected $collection = 'order_status_histories';

    protected $fillable = [
        'order_id',
        'old_status',
        'new_status',
        'reason',
        'changed_by'
    ];

    /* ---------- RELATIONS ---------- */
    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id', '_id');
    }

    public function changedBy()
    {
        return $this->belongsTo(User::class, 'changed_by', '_id');
    }
}
