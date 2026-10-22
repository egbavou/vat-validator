<?php

declare(strict_types=1);

namespace Egbavou\VatValidator\Internal\Countries;

use Egbavou\VatValidator\Internal\Checksum;

/** @internal BMF pp.14–17: mod 11 first (remainder 10 rejected), then ISO 7064 MOD 97-10; full alphanumeric/symbol alphabet for the latter. */
final class Netherlands extends AbstractCountryRule
{
    protected const string PREFIX = 'NL';

    protected const array LENGTHS = [12];

    protected const string CHARACTERS = '/\A[0-9A-Z+*]*\z/';

    protected function structure(string $national): bool
    {
        return ctype_digit(substr($national, -2)) && (int) substr($national, -2) > 0;
    }

    protected function checksum(string $national): bool
    {
        if (preg_match('/\A[0-9]{9}B[0-9]{2}\z/', $national) === 1) {
            $check = Checksum::weightedSum($national, [9, 8, 7, 6, 5, 4, 3, 2]) % 11;
            if ($check !== 10 && $check === (int) $national[8]) {
                return true;
            }
        }
        $suffix = (int) substr($national, -2);

        return $suffix >= 2 && $suffix <= 98 && Checksum::iso7064Mod97_10('NL'.$national);
    }
}
