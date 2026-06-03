<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Categories extends Model
{
    use SoftDeletes;

    protected $table = 'categories';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $fillable = [
        'name',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
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

    public function products()
    {
        return $this->hasMany(Product::class, 'category_id');
    }
}
