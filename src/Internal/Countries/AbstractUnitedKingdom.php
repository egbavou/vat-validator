<?php

declare(strict_types=1);

namespace Egbavou\VatValidator\Internal\Countries;

use Egbavou\VatValidator\Internal\Checksum;

/** @internal BMF pp.8–9,32–33: MOD97/MOD9755 ranges, branches and GD/HA. */
abstract class AbstractUnitedKingdom extends AbstractCountryRule
{
    protected const string COUNTRY = 'GB';

    protected const array LENGTHS = [5, 9, 12];

    protected function characters(string $national): bool
    {
        return preg_match('/\A(?:[0-9]*|(?:GD|HA)[0-9]*)\z/', $national) === 1;
    }

    protected function structure(string $national): bool
    {
        if (! ctype_digit($national)) {
            if (strlen($national) !== 5) {
                return false;
            }
            $number = (int) substr($national, 2);

            return str_starts_with($national, 'GD') ? $number <= 499 : $number >= 500;
        }
        if (strlen($national) === 5 || (int) substr($national, 0, 9) === 0) {
            return false;
        }
        if (strlen($national) === 12 && substr($national, -3) === '000') {
            return false;
        }
        $body = (int) substr($national, 0, 7);
        $sum = Checksum::weightedSum($national, [8, 7, 6, 5, 4, 3, 2]) + (int) substr($national, 7, 2);
        if ($sum % 97 === 0) {
            return ! (($body >= 100000 && $body <= 999999) || ($body >= 9490001 && $body <= 9700000) || $body >= 9990001);
        }

        return ($sum + 55) % 97 !== 0 || $body > 1000000;
    }

    protected function checksum(string $national): bool
    {
        if (! ctype_digit($national)) {
            return true;
        }
        $body = (int) substr($national, 0, 7);
        $sum = Checksum::weightedSum($national, [8, 7, 6, 5, 4, 3, 2]) + (int) substr($national, 7, 2);
        $mod97Range = ! (($body >= 100000 && $body <= 999999) || ($body >= 9490001 && $body <= 9700000) || $body >= 9990001);

        return ($sum % 97 === 0 && $mod97Range) || (($sum + 55) % 97 === 0 && $body > 1000000);
    }
}
