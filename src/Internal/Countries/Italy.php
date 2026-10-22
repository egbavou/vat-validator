<?php

declare(strict_types=1);

namespace Egbavou\VatValidator\Internal\Countries;

use Egbavou\VatValidator\Internal\Checksum;

/** @internal DM 23 December 1976 arts.8–9; BMF p.12 office codes. */
final class Italy extends AbstractCountryRule
{
    protected const string PREFIX = 'IT';

    protected const array LENGTHS = [11];

    protected function structure(string $national): bool
    {
        $office = (int) substr($national, 7, 3);

        return substr($national, 0, 7) !== '0000000' && (($office >= 1 && $office <= 100) || in_array($office, [120, 121, 888, 999], true));
    }

    protected function checksum(string $national): bool
    {
        return Checksum::luhn($national);
    }
}
