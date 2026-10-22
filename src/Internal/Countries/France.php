<?php

declare(strict_types=1);

namespace Egbavou\VatValidator\Internal\Countries;

use Egbavou\VatValidator\Internal\Checksum;

/** @internal Research §3 FR: INSEE SIREN Luhn; secondary key formulas and permissive Monaco exception. */
final class France extends AbstractCountryRule
{
    protected const string PREFIX = 'FR';

    protected const array LENGTHS = [11];

    protected function characters(string $national): bool
    {
        return preg_match('/\A[0-9A-HJ-NP-Z]{0,2}[0-9]*\z/', $national) === 1;
    }

    protected function checksum(string $national): bool
    {
        $siren = substr($national, 2);
        if (! str_starts_with($siren, '000') && ! Checksum::luhn($siren)) {
            return false;
        }
        $key = substr($national, 0, 2);
        if (ctype_digit($key)) {
            return (int) $key === (12 + 3 * ((int) $siren % 97)) % 97;
        }
        $alphabet = '0123456789ABCDEFGHJKLMNPQRSTUVWXYZ';
        $first = (int) strpos($alphabet, $key[0]);
        $second = (int) strpos($alphabet, $key[1]);
        $sum = ctype_digit($key[0]) ? $first * 24 + $second - 10 : $first * 34 + $second - 100;

        return ((int) $siren + 1 + intdiv($sum, 11)) % 11 === $sum % 11;
    }
}
