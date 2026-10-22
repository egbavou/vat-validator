<?php

declare(strict_types=1);

namespace Egbavou\VatValidator;

use Egbavou\VatValidator\Internal\Checksum;
use Egbavou\VatValidator\Internal\Countries\CountryRules;
use Stringable;

final readonly class VatNumber implements Stringable
{
    private const string SEPARATORS = '/[\p{Z}\x{0009}-\x{000D}\x{0085}.\-]+/u';

    private function __construct(
        public string $value,
        public string $prefix,
        public string $number,
        public string $countryCode,
    ) {}

    public static function isValid(string $input, ?string $country = null): bool
    {
        return self::tryFrom($input, $country) !== null;
    }

    public static function tryFrom(string $input, ?string $country = null): ?self
    {
        try {
            return self::from($input, $country);
        } catch (InvalidIdentifierException) {
            return null;
        }
    }

    /**
     * @throws InvalidIdentifierException
     */
    public static function from(string $input, ?string $country = null): self
    {
        $value = self::clean($input);
        $expected = null;

        if ($country !== null && trim($country) !== '') {
            $expected = CountryRules::prefixForCountry(strtoupper(trim($country)))
                ?? throw new InvalidIdentifierException(FailureReason::UnknownCountryPrefix);
        }

        [$prefix, $national] = self::split($value, $expected);
        $rule = CountryRules::forPrefix($prefix) ?? throw new InvalidIdentifierException(FailureReason::UnknownCountryPrefix);
        $national = $rule->normalize($national);
        $reason = $rule->validate($national);

        if ($reason !== null) {
            throw new InvalidIdentifierException($reason);
        }

        return new self($prefix.$national, $prefix, $national, $rule->countryCode());
    }

    /**
     * Builds the French VAT number of a SIREN. The SIREN must be 9 digits passing Luhn;
     * Monaco numbers (starting with 000) are not SIRENs and are rejected.
     *
     * @throws InvalidIdentifierException
     */
    public static function fromSiren(string|Stringable $siren): self
    {
        $digits = preg_replace(self::SEPARATORS, '', (string) $siren);

        if ($digits === null || preg_match('/\A[0-9]*\z/', $digits) !== 1) {
            throw new InvalidIdentifierException(FailureReason::InvalidCharacters);
        }

        if (strlen($digits) !== 9) {
            throw new InvalidIdentifierException(FailureReason::InvalidLength);
        }

        if (str_starts_with($digits, '000') || ! Checksum::luhn($digits)) {
            throw new InvalidIdentifierException(FailureReason::InvalidChecksum);
        }

        $key = (12 + 3 * ((int) $digits % 97)) % 97;

        return self::from(sprintf('FR%02d%s', $key, $digits));
    }

    public function format(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    /** @throws InvalidIdentifierException */
    private static function clean(string $input): string
    {
        $value = preg_replace(self::SEPARATORS, '', $input);

        if ($value === null) {
            throw new InvalidIdentifierException(FailureReason::InvalidCharacters);
        }

        $value = strtoupper($value);

        if ($value === '') {
            throw new InvalidIdentifierException(FailureReason::InvalidLength);
        }

        if (preg_match('/\A[0-9A-Z+*]+\z/', $value) !== 1) {
            throw new InvalidIdentifierException(FailureReason::InvalidCharacters);
        }

        return $value;
    }

    /**
     * @return array{string, string} canonical prefix, national part
     *
     * @throws InvalidIdentifierException
     */
    private static function split(string $value, ?string $expected): array
    {
        $detected = CountryRules::detectPrefix($value);

        if ($expected === null) {
            if ($detected === null) {
                throw new InvalidIdentifierException(
                    preg_match('/\A[A-Z]{2}/', $value) === 1 ? FailureReason::UnknownCountryPrefix : FailureReason::MissingCountryPrefix,
                );
            }

            return [$detected['prefix'], substr($value, $detected['length'])];
        }

        if ($detected !== null && $detected['prefix'] === $expected) {
            $national = substr($value, $detected['length']);
            $rule = CountryRules::forPrefix($expected);

            // A national alphanumeric key can itself look like its country's prefix.
            if ($rule !== null && $rule->validate($rule->normalize($national)) !== null
                && $rule->validate($rule->normalize($value)) === null) {
                return [$expected, $value];
            }

            return [$expected, $national];
        }

        if ($detected !== null) {
            // Another country's prefix. The whole input may still be a valid national part of the
            // expected country (e.g. a French alphanumeric key 'SE…'); otherwise the countries disagree.
            $rule = CountryRules::forPrefix($expected);

            if ($rule !== null && $rule->validate($rule->normalize($value)) === null) {
                return [$expected, $value];
            }

            throw new InvalidIdentifierException(FailureReason::CountryMismatch);
        }

        return [$expected, $value];
    }
}
