<?php

declare(strict_types=1);

namespace Egbavou\VatValidator\Internal\Countries;

/** @internal BMF p.2; VIES FAQ note 3 permits padding legacy nine-digit inputs. */
final class Belgium extends AbstractCountryRule
{
    protected const string PREFIX = 'BE';

    protected const array LENGTHS = [10];

    public function normalize(string $national): string
    {
        return strlen($national) === 9 && ctype_digit($national) ? '0'.$national : $national;
    }

    protected function structure(string $national): bool
    {
        return $national[0] === '0' || $national[0] === '1';
    }

    protected function checksum(string $national): bool
    {
        return (int) substr($national, -2) === 97 - (int) substr($national, 0, 8) % 97;
    }
}
