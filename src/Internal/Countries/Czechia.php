<?php

declare(strict_types=1);

namespace Egbavou\VatValidator\Internal\Countries;

use Egbavou\VatValidator\Internal\Checksum;

/**
 * @internal Research §3 CZ: law 133/2000 §13 for rodné číslo; secondary IČO formula.
 * Design §9 decision of 2026-10-22: nine-digit VČP starting with 6 are format only;
 * the administration has not published their construction. Allow IČO starting 9 and the old remainder-10 exception permissively.
 */
final class Czechia extends AbstractCountryRule
{
    protected const string PREFIX = 'CZ';

    protected const array LENGTHS = [8, 9, 10];

    protected function structure(string $national): bool
    {
        $length = strlen($national);
        if ($length === 8 || ($length === 9 && $national[0] === '6')) {
            return true;
        }
        $year = (int) substr($national, 0, 2);
        $month = (int) substr($national, 2, 2);
        if ($month > 50) {
            $month -= 50;
        }
        if ($length === 10 && $year >= 4 && $year < 54 && $month > 20) {
            $month -= 20;
        }
        $fullYear = $length === 9 || $year >= 54 ? 1900 + $year : 2000 + $year;
        if (! checkdate($month, (int) substr($national, 4, 2), $fullYear)) {
            return false;
        }
        if ($length === 9) {
            return $year <= 53;
        }

        return true;
    }

    protected function checksum(string $national): bool
    {
        $length = strlen($national);
        if ($length === 8) {
            $check = 11 - Checksum::weightedSum($national, [8, 7, 6, 5, 4, 3, 2]) % 11;

            return ($check === 11 ? 1 : ($check === 10 ? 0 : $check)) === (int) $national[7];
        }
        if ($length === 9) {
            return true;
        }

        return (int) $national % 11 === 0 || ((int) substr($national, 0, 9) % 11 === 10 && $national[9] === '0');
    }
}
