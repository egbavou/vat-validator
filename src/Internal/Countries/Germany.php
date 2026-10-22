<?php

declare(strict_types=1);

namespace Egbavou\VatValidator\Internal\Countries;

use Egbavou\VatValidator\Internal\Checksum;

/** @internal BMF p.3, ISO 7064 MOD 11,10. */
final class Germany extends AbstractCountryRule
{
    protected const string PREFIX = 'DE';

    protected const array LENGTHS = [9];

    protected function structure(string $national): bool
    {
        return $national[0] !== '0';
    }

    protected function checksum(string $national): bool
    {
        return Checksum::iso7064Mod11_10($national);
    }
}
