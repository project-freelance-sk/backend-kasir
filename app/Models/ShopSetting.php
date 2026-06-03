<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopSetting extends Model
{
    protected $fillable = [
        'shop_name',
        'address',
        'receipt_footer',
        'tax_percent',
        'service_charge_percent',
    ];

    protected function casts(): array
    {
        return [
            'tax_percent' => 'decimal:2',
            'service_charge_percent' => 'decimal:2',
        ];
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'shop_name' => 'Coffee Shop',
            'tax_percent' => 0,
            'service_charge_percent' => 0,
        ]);
    }
}
