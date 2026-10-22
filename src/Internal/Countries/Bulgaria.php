<?php

declare(strict_types=1);

namespace Egbavou\VatValidator\Internal\Countries;

use Egbavou\VatValidator\Internal\Checksum;

/**
 * @internal Research §3 BG: secondary BULSTAT checksum for nine-digit company numbers.
 * Design §9 decision of 2026-10-22: ten-digit natural-person numbers are format only,
 * including NAP service numbers introduced in 2024 whose construction is unpublished.
 */
final class Bulgaria extends AbstractCountryRule
{
    protected const string PREFIX = 'BG';

    protected const array LENGTHS = [9, 10];

    protected function checksum(string $national): bool
    {
        $last = (int) substr($national, -1);
        if (strlen($national) === 9) {
            $check = Checksum::weightedSum($national, [1, 2, 3, 4, 5, 6, 7, 8]) % 11;
            if ($check === 10) {
                $check = Checksum::weightedSum($national, [3, 4, 5, 6, 7, 8, 9, 10]) % 11 % 10;
            }

            return $last === $check;
        }

        return true;
    }
}
