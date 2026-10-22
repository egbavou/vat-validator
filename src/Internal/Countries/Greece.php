<?php

declare(strict_types=1);

namespace Egbavou\VatValidator\Internal\Countries;

use Egbavou\VatValidator\Internal\Checksum;

/** @internal Research §3 EL: dragonbe, stdnum and braemoor agree on the key; no eight-digit padding per design §9. */
final class Greece extends AbstractCountryRule
{
    protected const string PREFIX = 'EL';

    protected const array LENGTHS = [9];

    protected const string COUNTRY = 'GR';

    protected function checksum(string $national): bool
    {
        return Checksum::weightedSum($national, [256, 128, 64, 32, 16, 8, 4, 2]) % 11 % 10 === (int) $national[8];
    }
}
