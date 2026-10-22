<?php

declare(strict_types=1);

namespace Egbavou\VatValidator\Internal\Countries;

use Egbavou\VatValidator\Internal\Checksum;

/**
 * @internal eCH-0108 v6.0.0 §3.1.1: ^CHE[1-9][0-9]{8}$; ESTV suffixes.
 * Checksum weights from eCH-0097 v5.2.0 §2.4, abrogated 20/03/2024.
 */
final class Switzerland extends AbstractCountryRule
{
    protected const string PREFIX = 'CHE';

    protected const string COUNTRY = 'CH';

    protected const bool VIES = false;

    protected const array LENGTHS = [9];

    public function normalize(string $national): string
    {
        return preg_replace('/(?:MWST|TVA|IVA)\z/', '', $national) ?? $national;
    }

    protected function structure(string $national): bool
    {
        return $national[0] !== '0';
    }

    protected function checksum(string $national): bool
    {
        $check = 11 - Checksum::weightedSum($national, [5, 4, 3, 2, 7, 6, 5, 4]) % 11;

        return $check !== 10 && $check % 11 === (int) $national[8];
    }
}
