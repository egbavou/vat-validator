<?php

declare(strict_types=1);

use Egbavou\VatValidator\FailureReason;
use Egbavou\VatValidator\Internal\Countries\CountryRules;
use Egbavou\VatValidator\InvalidIdentifierException;
use Egbavou\VatValidator\Tests\Support\VatCases;
use Egbavou\VatValidator\VatNumber;
use PHPUnit\Framework\Assert;

it('accepts the research vectors', function (string $input, string $normalized): void {
    Assert::assertTrue(VatNumber::isValid($input));
    Assert::assertSame($normalized, VatNumber::from($input)->value);
})->with(VatCases::validNumbers());

it('rejects the research vectors with the first failure reason', function (string $input, FailureReason $reason): void {
    Assert::assertFalse(VatNumber::isValid($input));
    Assert::assertNull(VatNumber::tryFrom($input));
    try {
        VatNumber::from($input);
        Assert::fail('Expected an invalid VAT number.');
    } catch (InvalidIdentifierException $exception) {
        Assert::assertSame($reason, $exception->reason);
    }
})->with(VatCases::invalidNumbers());

it('has at least two valid and invalid vectors for every registered prefix', function (): void {
    foreach (CountryRules::prefixes() as $prefix) {
        $valid = array_filter(VatCases::validNumbers(), static fn (array $row): bool => str_starts_with($row[1], $prefix));
        $invalid = array_filter(VatCases::invalidNumbers(), static fn (array $row): bool => str_starts_with($row[0], $prefix));
        Assert::assertGreaterThanOrEqual(2, count($valid), $prefix.' valid');
        Assert::assertGreaterThanOrEqual(2, count($invalid), $prefix.' invalid');
    }
});
