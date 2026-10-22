<?php

declare(strict_types=1);

namespace Egbavou\VatValidator\Internal\Countries;

use Egbavou\VatValidator\FailureReason;

/** @internal Common normalization and ordered checks; algorithms stay in each national rule. */
abstract class AbstractCountryRule implements CountryRule
{
    protected const string PREFIX = '';

    protected const string COUNTRY = '';

    protected const bool VIES = true;

    protected const string CHARACTERS = '/\A[0-9]*\z/';

    /** @var list<int> */
    protected const array LENGTHS = [];

    public function prefix(): string
    {
        return static::PREFIX;
    }

    public function countryCode(): string
    {
        return static::COUNTRY === '' ? static::PREFIX : static::COUNTRY;
    }

    public function coveredByVies(): bool
    {
        return static::VIES;
    }

    public function normalize(string $national): string
    {
        return $national;
    }

    public function validate(string $national): ?FailureReason
    {
        if (! $this->characters($national)) {
            return FailureReason::InvalidCharacters;
        }
        if (! in_array(strlen($national), static::LENGTHS, true)) {
            return FailureReason::InvalidLength;
        }
        if (! $this->structure($national)) {
            return FailureReason::InvalidStructure;
        }

        return $this->checksum($national) ? null : FailureReason::InvalidChecksum;
    }

    protected function characters(string $national): bool
    {
        return preg_match(static::CHARACTERS, $national) === 1;
    }

    protected function structure(string $national): bool
    {
        return true;
    }

    abstract protected function checksum(string $national): bool;
}
