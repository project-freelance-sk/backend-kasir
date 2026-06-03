<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransactionDetailToppings extends Model
{
    protected $table = 'transaction_detail_toppings';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $fillable = [
        'transaction_detail_id',
        'topping_id',
        'price'
    ];

    protected static function booted()
    {
        parent::booted();
        static::creating(function ($model) {
            $model->id = (string) \Illuminate\Support\Str::uuid();
        });
    }

    public function topping()
    {
        return $this->belongsTo(Toppings::class, 'topping_id');
    }
}
