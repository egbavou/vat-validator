<?php

declare(strict_types=1);

namespace Egbavou\VatValidator\Internal;

/** @internal Pure checksum arithmetic shared by national VAT rules. */
final class Checksum
{
    /** Luhn modulo 10, with the rightmost digit serving as the check digit. */
    public static function luhn(string $digits): bool
    {
        $sum = 0;
        $double = false;
        for ($i = strlen($digits) - 1; $i >= 0; $i--) {
            $digit = (int) $digits[$i];
            $sum += $double ? self::digitSum((string) (2 * $digit)) : $digit;
            $double = ! $double;
        }

        return $sum % 10 === 0;
    }

    /** ISO/IEC 7064 MOD 11,10, including the final check digit (DE and HR). */
    public static function iso7064Mod11_10(string $digits): bool
    {
        $product = 10;
        for ($i = 0, $length = strlen($digits) - 1; $i < $length; $i++) {
            $sum = ((int) $digits[$i] + $product) % 10;
            $product = 2 * ($sum === 0 ? 10 : $sum) % 11;
        }

        return (11 - $product) % 10 === (int) substr($digits, -1);
    }

    /** ISO 7064 MOD 97-10: A=10…Z=35, +=36, *=37; stream digits to avoid overflow. */
    public static function iso7064Mod97_10(string $alphanumeric): bool
    {
        $remainder = 0;
        foreach (str_split($alphanumeric) as $character) {
            $expanded = match ($character) {
                '+' => '36',
                '*' => '37',
                default => ctype_digit($character) ? $character : (string) (ord($character) - 55),
            };
            foreach (str_split($expanded) as $digit) {
                $remainder = ($remainder * 10 + (int) $digit) % 97;
            }
        }

        return $remainder === 1;
    }

    /** Sum the leading decimal digits multiplied by the corresponding weights.
     * @param  list<int>  $weights
     */
    public static function weightedSum(string $digits, array $weights): int
    {
        $sum = 0;
        foreach ($weights as $index => $weight) {
            $sum += (int) $digits[$index] * $weight;
        }

        return $sum;
    }

    /** Sum the decimal digits (including the two digits of a doubled Luhn digit). */
    public static function digitSum(string $digits): int
    {
        $sum = 0;
        foreach (str_split($digits) as $digit) {
            $sum += (int) $digit;
        }

        return $sum;
    }
}
