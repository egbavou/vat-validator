<?php

declare(strict_types=1);

namespace Egbavou\VatValidator\Internal\Countries;

use Egbavou\VatValidator\Internal\Checksum;

/** @internal BMF p.19: suffix 01–94 takes precedence over Skatteverket 01-only; Luhn on the first ten digits. */
final class Sweden extends AbstractCountryRule
{
    protected const string PREFIX = 'SE';

    protected const array LENGTHS = [12];

    protected function structure(string $national): bool
    {
        $suffix = (int) substr($national, -2);

        return $suffix >= 1 && $suffix <= 94;
    }

    protected function checksum(string $national): bool
    {
        return Checksum::luhn(substr($national, 0, 10));
    }
}
