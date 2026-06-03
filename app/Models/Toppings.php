<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Toppings extends Model
{
    use SoftDeletes;

    protected $table = 'toppings';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $fillable = [
        'name',
        'price',
        'is_active'
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted()
    {
        parent::booted();
        static::creating(function ($model) {
            $model->id = (string) \Illuminate\Support\Str::uuid();
        });
    }
}
