<?php

declare(strict_types=1);

namespace Egbavou\VatValidator\Internal\Countries;

use Egbavou\VatValidator\Internal\Checksum;

/** @internal Finlex decree 288/2001 §3; BMF p.6. */
final class Finland extends AbstractCountryRule
{
    protected const string PREFIX = 'FI';

    protected const array LENGTHS = [8];

    protected function checksum(string $national): bool
    {
        $check = 11 - Checksum::weightedSum($national, [7, 9, 10, 5, 8, 4, 2]) % 11;

        return $check !== 10 && $check % 11 === (int) $national[7];
    }
}
