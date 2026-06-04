<?php

namespace App\Domains\Qdvs\Engine\Support;

use Carbon\CarbonImmutable;
use Throwable;

/**
 * Wertsemantik-Helfer, die die XPath-Auswertung der DAS-Asserts nachbilden.
 */
final class Xpath
{
    // entspricht: not(exists(X/@value)) or string-length(xs:string(X/@value)) = 0 or xs:string(X/@value) = ''
    public static function isEmpty(mixed $v): bool
    {
        if ($v === null) {
            return true;
        }
        if (is_array($v)) {
            return $v === [];
        }

        return trim((string) $v) === '';
    }

    public static function present(mixed $v): bool
    {
        return ! self::isEmpty($v);
    }

    /**
     * Parst eine XPath-Wertmenge wie ('0','1','2') oder (1,2,3) zu einer String-Liste.
     *
     * @return array<int, string>
     */
    public static function valueSet(string $raw): array
    {
        preg_match_all("/'([^']*)'|(-?\d+(?:\.\d+)?)/", $raw, $m, PREG_SET_ORDER);

        return array_map(fn ($g) => $g[1] !== '' ? $g[1] : $g[2], $m);
    }

    public static function date(mixed $v): ?CarbonImmutable
    {
        if (self::isEmpty($v)) {
            return null;
        }
        try {
            return CarbonImmutable::parse((string) $v)->startOfDay();
        } catch (Throwable) {
            return null;
        }
    }

    public static function number(mixed $v): ?float
    {
        if (self::isEmpty($v) || ! is_numeric($v)) {
            return null;
        }

        return (float) $v;
    }

    public static function castableAsInt(mixed $v): bool
    {
        return is_numeric($v) && (string) (int) $v === ltrim((string) $v, '+');
    }

    public static function castableAsDecimal(mixed $v): bool
    {
        return is_numeric($v);
    }

    public static function castableAsDate(mixed $v): bool
    {
        if (self::isEmpty($v)) {
            return false;
        }
        // strikt: kein Carbon-Overflow (2026-02-30 → März) als „gültig" durchwinken
        try {
            $d = CarbonImmutable::createFromFormat('!Y-m-d', (string) $v);
        } catch (Throwable) {
            return false;
        }

        return $d !== false && $d->format('Y-m-d') === (string) $v;
    }
}
