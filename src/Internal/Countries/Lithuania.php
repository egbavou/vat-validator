<?php

declare(strict_types=1);

namespace Egbavou\VatValidator\Internal\Countries;

use Egbavou\VatValidator\Internal\Checksum;

/** @internal BMF p.24, both nine- and twelve-digit families. */
final class Lithuania extends AbstractCountryRule
{
    protected const string PREFIX = 'LT';

    protected const array LENGTHS = [9, 12];

    protected function structure(string $national): bool
    {
        return substr($national, -2, 1) === '1';
    }

    protected function checksum(string $national): bool
    {
        $length = strlen($national) - 1;
        $weights = [];
        $fallback = [];
        for ($i = 0; $i < $length; $i++) {
            $weights[] = $i % 9 + 1;
            $fallback[] = ($i + 2) % 9 + 1;
        }
        $check = Checksum::weightedSum($national, $weights) % 11;
        if ($check === 10) {
            $check = Checksum::weightedSum($national, $fallback) % 11 % 10;
        }

        return $check === (int) substr($national, -1);
    }
}
