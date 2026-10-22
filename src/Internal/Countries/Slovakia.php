<?php

declare(strict_types=1);

namespace Egbavou\VatValidator\Internal\Countries;

/** @internal BMF p.29: permitted third digits and divisibility by 11. */
final class Slovakia extends AbstractCountryRule
{
    protected const string PREFIX = 'SK';

    protected const array LENGTHS = [10];

    protected function structure(string $national): bool
    {
        return $national[0] !== '0' && str_contains('234789', $national[2]);
    }

    protected function checksum(string $national): bool
    {
        return (int) $national % 11 === 0;
    }
}
