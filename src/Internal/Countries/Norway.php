<?php

declare(strict_types=1);

namespace Egbavou\VatValidator\Internal\Countries;

use Egbavou\VatValidator\Internal\Checksum;

/** @internal Brønnøysundregistrene organization-number modulus 11. */
final class Norway extends AbstractCountryRule
{
    protected const string PREFIX = 'NO';

    protected const string COUNTRY = 'NO';

    protected const bool VIES = false;

    protected const array LENGTHS = [9];

    public function normalize(string $national): string
    {
        return preg_replace('/(?:MVA)\z/', '', $national) ?? $national;
    }

    protected function checksum(string $national): bool
    {
        $check = 11 - Checksum::weightedSum($national, [3, 2, 7, 6, 5, 4, 3, 2]) % 11;

        return $check !== 10 && $check % 11 === (int) $national[8];
    }
}
