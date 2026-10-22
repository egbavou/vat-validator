<?php

declare(strict_types=1);

namespace Egbavou\VatValidator\Internal\Countries;

/** @internal BMF p.13, modulo 89. */
final class Luxembourg extends AbstractCountryRule
{
    protected const string PREFIX = 'LU';

    protected const array LENGTHS = [8];

    protected function checksum(string $national): bool
    {
        return (int) substr($national, 0, 6) % 89 === (int) substr($national, -2);
    }
}
