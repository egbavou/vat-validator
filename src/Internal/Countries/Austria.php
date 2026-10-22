<?php

declare(strict_types=1);

namespace Egbavou\VatValidator\Internal\Countries;

use Egbavou\VatValidator\Internal\Checksum;

/** @internal BMF p.1 (modified Luhn). */
final class Austria extends AbstractCountryRule
{
    protected const string PREFIX = 'AT';

    protected const array LENGTHS = [9];

    protected const string CHARACTERS = '/\AU[0-9]*\z/';

    protected function checksum(string $national): bool
    {
        $sum = 4;
        for ($i = 1; $i <= 7; $i++) {
            $digit = (int) $national[$i];
            $sum += $i % 2 === 0 ? Checksum::digitSum((string) (2 * $digit)) : $digit;
        }

        return (10 - $sum % 10) % 10 === (int) $national[8];
    }
}
