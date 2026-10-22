<?php

declare(strict_types=1);

use Egbavou\VatValidator\CheckStatus;
use Egbavou\VatValidator\FailureReason;
use Egbavou\VatValidator\InvalidIdentifierException;
use Egbavou\VatValidator\UnavailableReason;
use Egbavou\VatValidator\VatNumberCheckResult;
use PHPUnit\Framework\Assert;

it('defines the failure reasons as snake_case translation keys', function (): void {
    Assert::assertSame(
        ['invalid_characters', 'invalid_length', 'invalid_checksum', 'invalid_structure', 'unknown_country_prefix', 'missing_country_prefix', 'country_mismatch'],
        array_map(static fn (FailureReason $reason): string => $reason->value, FailureReason::cases()),
    );
});

it('exposes the reason and a stable message on the exception', function (): void {
    $exception = new InvalidIdentifierException(FailureReason::InvalidStructure);

    Assert::assertSame(FailureReason::InvalidStructure, $exception->reason);
    Assert::assertSame('Invalid VAT number: invalid_structure.', $exception->getMessage());
    Assert::assertInstanceOf(InvalidArgumentException::class, $exception);
});

it('defines the check statuses and unavailability reasons', function (): void {
    Assert::assertSame(['found', 'not_found', 'invalid_format', 'unavailable'], array_map(static fn (CheckStatus $s): string => $s->value, CheckStatus::cases()));
    Assert::assertSame(
        ['no_source', 'registry_error', 'transport', 'http_status', 'invalid_json', 'schema'],
        array_map(static fn (UnavailableReason $r): string => $r->value, UnavailableReason::cases()),
    );
});

it('is an immutable result object', function (): void {
    Assert::assertTrue((new ReflectionClass(VatNumberCheckResult::class))->isReadOnly());
});
