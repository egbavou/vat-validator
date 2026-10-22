<?php

declare(strict_types=1);

namespace Egbavou\VatValidator\Internal\Countries;

use Egbavou\VatValidator\Internal\Checksum;

/** @internal BMF p.28; FURS modulus 11. */
final class Slovenia extends AbstractCountryRule
{
    protected const string PREFIX = 'SI';

    protected const array LENGTHS = [8];

    protected function structure(string $national): bool
    {
        return $national[0] !== '0';
    }

    protected function checksum(string $national): bool
    {
        $check = 11 - Checksum::weightedSum($national, [8, 7, 6, 5, 4, 3, 2]) % 11;

        return $check !== 11 && ($check === 10 ? 0 : $check) === (int) $national[7];
    }
}
