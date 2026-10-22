<?php

declare(strict_types=1);

namespace Egbavou\VatValidator\Internal\Countries;

use Egbavou\VatValidator\Internal\Checksum;

/** @internal BMF p.23; Céginformációs Szolgálat 2018. */
final class Hungary extends AbstractCountryRule
{
    protected const string PREFIX = 'HU';

    protected const array LENGTHS = [8];

    protected function checksum(string $national): bool
    {
        return (10 - Checksum::weightedSum($national, [9, 7, 3, 1, 9, 7, 3]) % 10) % 10 === (int) $national[7];
    }
}
