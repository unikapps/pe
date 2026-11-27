<?php

namespace App\Casts;

use App\Pricing\Nanodollars;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Stores Nanodollars in a bigint column.
 *
 * @implements CastsAttributes<Nanodollars|null, Nanodollars|null>
 */
final class NanodollarsCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Nanodollars
    {
        return $value === null ? null : new Nanodollars((int) $value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?int
    {
        return $value?->amount;
    }
}
