<?php

declare(strict_types=1);

namespace Egbavou\VatValidator\Internal\Countries;

use Egbavou\VatValidator\Internal\Checksum;

/** @internal BMF p.27, weighted modulus 11. */
final class Poland extends AbstractCountryRule
{
    protected const string PREFIX = 'PL';

    protected const array LENGTHS = [10];

    protected function checksum(string $national): bool
    {
        return Checksum::weightedSum($national, [6, 5, 7, 2, 3, 4, 5, 6, 7]) % 11 === (int) $national[9];
    }
}
