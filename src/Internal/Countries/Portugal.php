<?php

declare(strict_types=1);

namespace Egbavou\VatValidator\Internal\Countries;

use Egbavou\VatValidator\Internal\Checksum;

/** @internal BMF p.18; DL 14/2013 art.4. */
final class Portugal extends AbstractCountryRule
{
    protected const string PREFIX = 'PT';

    protected const array LENGTHS = [9];

    protected function structure(string $national): bool
    {
        return $national[0] !== '0';
    }

    protected function checksum(string $national): bool
    {
        $check = 11 - Checksum::weightedSum($national, [9, 8, 7, 6, 5, 4, 3, 2]) % 11;

        return ($check >= 10 ? 0 : $check) === (int) $national[8];
    }
}
