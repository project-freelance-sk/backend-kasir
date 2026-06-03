<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transactions extends Model
{
    protected $table = 'transactions';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $fillable = [
        'cashier_id',
        'invoice_number',
        'idempotency_key',
        'payment_method',
        'order_type',
        'subtotal',
        'discount_amount',
        'tax_amount',
        'service_charge_amount',
        'total',
        'paid_amount',
        'change_amount',
        'payment_status',
        'voided_at',
        'voided_by',
        'void_reason',
    ];

    protected static function booted()
    {
        parent::booted();
        static::creating(function ($model) {
            $model->id = (string) \Illuminate\Support\Str::uuid();
        });
    }

    public function cashier()
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function voidedBy()
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function details()
    {
        return $this->hasMany(TransactionDetails::class, 'transaction_id');
    }

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'service_charge_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'change_amount' => 'decimal:2',
            'voided_at' => 'datetime',
        ];
    }
}
