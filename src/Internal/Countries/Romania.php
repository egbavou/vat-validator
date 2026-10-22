<?php

declare(strict_types=1);

namespace Egbavou\VatValidator\Internal\Countries;

use Egbavou\VatValidator\Internal\Checksum;

/** @internal Research §3 RO: BMF forbids leading zeros since 5/12/2007; secondary weighted checksum. */
final class Romania extends AbstractCountryRule
{
    protected const string PREFIX = 'RO';

    protected const array LENGTHS = [2, 3, 4, 5, 6, 7, 8, 9, 10];

    protected function structure(string $national): bool
    {
        return $national[0] !== '0';
    }

    protected function checksum(string $national): bool
    {
        $body = str_pad(substr($national, 0, -1), 9, '0', STR_PAD_LEFT);
        $check = 10 * Checksum::weightedSum($body, [7, 5, 3, 2, 1, 7, 5, 3, 2]) % 11;

        return ($check === 10 ? 0 : $check) === (int) substr($national, -1);
    }
}
