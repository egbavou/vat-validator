<?php

declare(strict_types=1);

namespace Egbavou\VatValidator\Internal\Countries;

use Egbavou\VatValidator\Internal\Checksum;

/**
 * @internal Research §3 LV: secondary corporate modulus 11; old personal keys lack independent confirmation.
 * Fizisko personu reģistra likums art. 6(2), in force 28/06/2021: 3 + random digit 2–9 + nine random digits, without a checksum.
 */
final class Latvia extends AbstractCountryRule
{
    protected const string PREFIX = 'LV';

    protected const array LENGTHS = [11];

    protected function structure(string $national): bool
    {
        if ((int) $national[0] > 3 || preg_match('/\A3[2-9]/', $national) === 1) {
            return true;
        }
        $century = (int) $national[6];

        return $century <= 2 && checkdate((int) substr($national, 2, 2), (int) substr($national, 0, 2), 1800 + $century * 100 + (int) substr($national, 4, 2));
    }

    protected function checksum(string $national): bool
    {
        return (int) $national[0] <= 3 || Checksum::weightedSum($national, [9, 1, 4, 8, 3, 10, 2, 5, 7, 6, 1]) % 11 === 3;
    }
}
