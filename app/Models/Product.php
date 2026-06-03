<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Product extends Model
{
    use SoftDeletes;

    protected $table = 'products';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $fillable = [
        'category_id',
        'name',
        'description',
        'price',
        'image',
    ];

    protected static function booted(): void
    {
        static::creating(function ($model) {
            $model->id = (string) \Illuminate\Support\Str::uuid();
        });
    }

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function category()
    {
        return $this->belongsTo(Categories::class, 'category_id');
    }

    public function toppings()
    {
        return $this->belongsToMany(Toppings::class, 'product_toppings', 'product_id', 'topping_id');
    }

    protected function imageUrl(): Attribute
    {
        return Attribute::make(get: fn(?string $value) => $value ? Storage::disk('public')->url($value) : null,);
    }
    protected $appends = ['imageUrl'];
    protected $hidden = ['imageUrl'];
}
