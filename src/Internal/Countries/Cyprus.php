<?php

declare(strict_types=1);

namespace Egbavou\VatValidator\Internal\Countries;

/**
 * @internal Research §3 CY: EC TIN transcode table (secondary for VAT); no unsourced first-digit or 12-prefix exclusion.
 * The control letter is computed for the 6-series too: Cyprus Tax Department notice 04/05/2023,
 * OECD TIN sheet 28/04/2023; verified on 24 real VIES-valid numbers on 2026-10-22 (design §9).
 */
final class Cyprus extends AbstractCountryRule
{
    protected const string PREFIX = 'CY';

    protected const array LENGTHS = [9];

    protected const string CHARACTERS = '/\A[0-9]{0,8}[A-Z]?\z/';

    protected function checksum(string $national): bool
    {
        $table = [1, 0, 5, 7, 9, 13, 15, 17, 19, 21];
        $sum = 0;
        for ($i = 0; $i < 8; $i++) {
            $digit = (int) $national[$i];
            $sum += $i % 2 === 0 ? $table[$digit] : $digit;
        }

        return chr(65 + $sum % 26) === $national[8];
    }
}
