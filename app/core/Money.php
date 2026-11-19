<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Calculs monetaires en centimes (entiers) pour eviter les erreurs de flottants.
 */
final class Money
{
    public static function toCents(mixed $amount): int
    {
        if ($amount === null || $amount === '') {
            return 0;
        }

        return (int) round((float) $amount * 100);
    }

    public static function fromCents(int $cents): string
    {
        $sign = $cents < 0 ? '-' : '';
        $absolute = abs($cents);

        return $sign . intdiv($absolute, 100) . '.' . str_pad((string) ($absolute % 100), 2, '0', STR_PAD_LEFT);
    }

    public static function format(mixed $amount, bool $withCurrency = true): string
    {
        $cents = self::toCents($amount);
        $formatted = number_format($cents / 100, 0, ',', ' ');

        return $withCurrency ? $formatted . ' ' . config('devise', 'XAF') : $formatted;
    }

    public static function ensureNonNegative(int $cents): int
    {
        return max(0, $cents);
    }
}
