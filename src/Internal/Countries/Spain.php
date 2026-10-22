<?php

declare(strict_types=1);

namespace Egbavou\VatValidator\Internal\Countries;

use Egbavou\VatValidator\Internal\Checksum;

/** @internal Research §3 ES: BOE entity structure; EC TIN DNI; secondary CIF algorithm. Permissively accept a digit or letter CIF check for every entity (stdnum; Task 5). */
final class Spain extends AbstractCountryRule
{
    protected const string PREFIX = 'ES';

    protected const array LENGTHS = [9];

    protected function characters(string $national): bool
    {
        if (preg_match('/\A[0-9A-Z]*\z/', $national) !== 1) {
            return false;
        }
        if (strlen($national) !== 9) {
            return true;
        }

        return preg_match('/\A(?:[0-9]{8}[A-Z]|[XYZKLM][0-9]{7}[A-Z]|[ABCDEFGHJNPQRSUVW][0-9]{7}[0-9A-Z])\z/', $national) === 1;
    }

    protected function checksum(string $national): bool
    {
        $first = $national[0];
        $body = substr($national, 1, 7);
        if (ctype_digit($first) || str_contains('XYZKLM', $first)) {
            $digits = ctype_digit($first) ? substr($national, 0, 8) : (str_contains('XYZ', $first) ? (string) strpos('XYZ', $first).$body : $body);

            return 'TRWAGMYFPDXBNJZSQVHLCKE'[(int) $digits % 23] === $national[8];
        }
        $sum = 0;
        for ($i = 0; $i < 7; $i++) {
            $digit = (int) $body[$i];
            $sum += $i % 2 === 0 ? Checksum::digitSum((string) (2 * $digit)) : $digit;
        }
        $check = (10 - $sum % 10) % 10;

        return $national[8] === (string) $check || $national[8] === 'JABCDEFGHI'[$check];
    }
}
