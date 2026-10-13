<?php

namespace App\Casts;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/** @implements CastsAttributes<CarbonImmutable|null, CarbonInterface|string|null> */
class UtcInstant implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?CarbonImmutable
    {
        return $value === null ? null : CarbonImmutable::parse($value, 'UTC');
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }
        // Eloquent normalizes created_at/updated_at to database strings before casting.
        if (is_string($value)) {
            $instant = CarbonImmutable::createFromFormat('!Y-m-d H:i:s', $value, 'UTC');
            if (! $instant || $instant->format('Y-m-d H:i:s') !== $value) {
                throw new \InvalidArgumentException('Invalid UTC database timestamp.');
            }

            return $value;
        }

        return $value->toImmutable()->utc()->format('Y-m-d H:i:s');
    }
}
