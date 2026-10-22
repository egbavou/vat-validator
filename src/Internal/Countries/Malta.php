<?php

declare(strict_types=1);

namespace Egbavou\VatValidator\Internal\Countries;

use Egbavou\VatValidator\Internal\Checksum;

/** @internal Research §3 MT: secondary sources disagree; accept the wider stdnum congruence mod 37, including 00 and +37/+74 controls (Task 6). No unsourced body >100000 restriction. */
final class Malta extends AbstractCountryRule
{
    protected const string PREFIX = 'MT';

    protected const array LENGTHS = [8];

    protected function structure(string $national): bool
    {
        return $national[0] !== '0';
    }

    protected function checksum(string $national): bool
    {
        return Checksum::weightedSum($national, [3, 4, 6, 7, 8, 9, 10, 1]) % 37 === 0;
    }
}
