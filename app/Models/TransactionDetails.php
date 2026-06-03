<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransactionDetails extends Model
{
    protected $table = 'transaction_details';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $fillable = [
        'transaction_id',
        'product_id',
        'product_name',
        'qty',
        'price',
        'subtotal',
        'notes',
    ];

    protected static function booted()
    {
        parent::booted();
        static::creating(function ($model) {
            $model->id = (string) \Illuminate\Support\Str::uuid();
        });
    }

    public function transaction()
    {
        return $this->belongsTo(Transactions::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function detailToppings()
    {
        return $this->hasMany(TransactionDetailToppings::class, 'transaction_detail_id');
    }
}
