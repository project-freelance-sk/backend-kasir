<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ExistsModel implements ValidationRule
{
    public function __construct(private string $modelClass) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $this->modelClass::query()->whereKey($value)->exists()) {
            $fail('Data yang dipilih tidak valid.');
        }
    }
}
