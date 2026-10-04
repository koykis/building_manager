<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

class Decimal
{
    public static function parse(mixed $value, int $scale = 4, string $locale = 'canonical'): string
    {
        if (! is_string($value) && ! is_int($value)) {
            throw ValidationException::withMessages(['amount' => ['Use decimal strings.']]);
        }
        $s = trim((string) $value);
        if ($locale === 'el') {
            if (! preg_match('/^-?(?:\d+|\d{1,3}(?:\.\d{3})+)(?:,\d+)?$/D', $s)) {
                throw ValidationException::withMessages(['amount' => ['Invalid Greek decimal.']]);
            }$s = str_replace(['.', ','], ['', '.'], $s);
        }
        if (! preg_match('/^-?\d{1,9}(?:\.\d{1,'.$scale.'})?$/D', $s)) {
            throw ValidationException::withMessages(['amount' => ['Invalid decimal precision.']]);
        }

        return bcadd($s, '0', $scale);
    }

    public static function sum(iterable $values, int $scale = 4): string
    {
        $s = '0';
        foreach ($values as $v) {
            $s = bcadd($s, (string) ($v ?? '0'), $scale);
        }

        return $s;
    }
}
