<?php

declare(strict_types=1);

namespace Egbavou\VatValidator\Internal\Countries;

use Egbavou\VatValidator\FailureReason;

/** @internal One implementation per VAT prefix. */
interface CountryRule
{
    /** VAT prefix as used by VIES and in the normalized value: 'EL', 'XI', 'CHE'. */
    public function prefix(): string;

    /** ISO 3166-1 alpha-2 code: 'GR' for EL, 'GB' for XI, 'CH' for CHE. */
    public function countryCode(): string;

    public function coveredByVies(): bool;

    /** Country-specific normalization of the national part (suffix removal, padding). Identity by default. */
    public function normalize(string $national): string;

    /** Null when valid; otherwise the first failing check, in the order characters, length, structure, checksum. */
    public function validate(string $national): ?FailureReason;
}
