<?php

declare(strict_types=1);

namespace Egbavou\VatValidator\Internal\Countries;

use Egbavou\VatValidator\Internal\Checksum;

/** @internal BMF pp.10–11: old and new formats, C9 restricted to A–I. */
final class Ireland extends AbstractCountryRule
{
    protected const string PREFIX = 'IE';

    protected const array LENGTHS = [8, 9];

    protected function characters(string $national): bool
    {
        if (! in_array(strlen($national), [8, 9], true)) {
            return preg_match('/\A[0-9A-Z+*]*\z/', $national) === 1;
        }

        return preg_match('/\A(?:[0-9]{7}[A-W][A-I]?|[0-9][A-Z+*][0-9]{5}[A-W])\z/', $national) === 1;
    }

    protected function checksum(string $national): bool
    {
        $old = ! ctype_digit($national[1]);
        $digits = $old ? '0'.substr($national, 2, 5).$national[0] : substr($national, 0, 7);
        $sum = Checksum::weightedSum($digits, [8, 7, 6, 5, 4, 3, 2]);
        if (strlen($national) === 9) {
            $sum += 9 * (ord($national[8]) - 64);
        }

        return 'WABCDEFGHIJKLMNOPQRSTUV'[$sum % 23] === $national[7];
    }
}
