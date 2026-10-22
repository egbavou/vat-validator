<?php

declare(strict_types=1);

namespace Egbavou\VatValidator\Internal\Countries;

use Egbavou\VatValidator\Internal\Checksum;

/** @internal BMF p.22: initial 10 is mandatory even when a different prefix passes the checksum. */
final class Estonia extends AbstractCountryRule
{
    protected const string PREFIX = 'EE';

    protected const array LENGTHS = [9];

    protected function structure(string $national): bool
    {
        return str_starts_with($national, '10');
    }

    protected function checksum(string $national): bool
    {
        return (10 - Checksum::weightedSum($national, [3, 7, 1, 3, 7, 1, 3, 7]) % 10) % 10 === (int) $national[8];
    }
}
