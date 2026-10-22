<?php

declare(strict_types=1);

namespace Egbavou\VatValidator\Internal\Countries;

use Egbavou\VatValidator\Internal\Checksum;

/** @internal BMF p.4; Erhvervsstyrelsen modulus 11. */
final class Denmark extends AbstractCountryRule
{
    protected const string PREFIX = 'DK';

    protected const array LENGTHS = [8];

    protected function structure(string $national): bool
    {
        return $national[0] !== '0';
    }

    protected function checksum(string $national): bool
    {
        return Checksum::weightedSum($national, [2, 7, 6, 5, 4, 3, 2, 1]) % 11 === 0;
    }
}
