<?php

declare(strict_types=1);

namespace Egbavou\VatValidator\Internal\Countries;

use Egbavou\VatValidator\Internal\Checksum;

/** @internal Croatian OIB law NN 60/08 art.3; Pravilnik NN 1/09 art.3(1), ISO 7064 MOD 11,10. */
final class Croatia extends AbstractCountryRule
{
    protected const string PREFIX = 'HR';

    protected const array LENGTHS = [11];

    protected function checksum(string $national): bool
    {
        return Checksum::iso7064Mod11_10($national);
    }
}
