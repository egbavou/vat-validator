<?php

declare(strict_types=1);

namespace Egbavou\VatValidator\Internal\Countries;

/** @internal Canonical VAT prefixes and cached national rule instances. */
final class CountryRules
{
    /** @var array<string, class-string<CountryRule>> */
    private const array RULES = [
        'AT' => Austria::class,
        'BE' => Belgium::class,
        'BG' => Bulgaria::class,
        'CY' => Cyprus::class,
        'CZ' => Czechia::class,
        'DE' => Germany::class,
        'DK' => Denmark::class,
        'EE' => Estonia::class,
        'EL' => Greece::class,
        'ES' => Spain::class,
        'FI' => Finland::class,
        'FR' => France::class,
        'HR' => Croatia::class,
        'HU' => Hungary::class,
        'IE' => Ireland::class,
        'IT' => Italy::class,
        'LT' => Lithuania::class,
        'LU' => Luxembourg::class,
        'LV' => Latvia::class,
        'MT' => Malta::class,
        'NL' => Netherlands::class,
        'PL' => Poland::class,
        'PT' => Portugal::class,
        'RO' => Romania::class,
        'SE' => Sweden::class,
        'SI' => Slovenia::class,
        'SK' => Slovakia::class,
        'XI' => NorthernIreland::class,
        'GB' => UnitedKingdom::class,
        'CHE' => Switzerland::class,
        'NO' => Norway::class,
    ];

    /** @var array<string, CountryRule> */
    private static array $instances = [];

    public static function forPrefix(string $prefix): ?CountryRule
    {
        $class = self::RULES[$prefix] ?? null;

        return $class === null ? null : (self::$instances[$prefix] ??= new $class);
    }

    public static function prefixForCountry(string $country): ?string
    {
        $prefix = match ($country) {
            'GR' => 'EL', 'CH' => 'CHE', default => $country
        };

        return isset(self::RULES[$prefix]) ? $prefix : null;
    }

    /** @return array{prefix: string, length: int}|null */
    public static function detectPrefix(string $value): ?array
    {
        if (str_starts_with($value, 'CHE')) {
            return ['prefix' => 'CHE', 'length' => 3];
        }
        $prefix = substr($value, 0, 2);
        $canonical = $prefix === 'GR' ? 'EL' : $prefix;

        return isset(self::RULES[$canonical]) ? ['prefix' => $canonical, 'length' => 2] : null;
    }

    /** @return list<string> */
    public static function prefixes(): array
    {
        return array_keys(self::RULES);
    }
}
