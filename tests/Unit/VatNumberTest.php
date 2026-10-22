<?php

declare(strict_types=1);

use Egbavou\VatValidator\FailureReason;
use Egbavou\VatValidator\InvalidIdentifierException;
use Egbavou\VatValidator\Tests\Support\VatCases;
use Egbavou\VatValidator\VatNumber;
use PHPUnit\Framework\Assert;

it('parses inputs and reports stable failures', function (string $input, ?string $country, string|FailureReason $expected): void {
    if (is_string($expected)) {
        $vat = VatNumber::from($input, $country);
        Assert::assertSame($expected, $vat->value);
        Assert::assertSame($expected, $vat->format());
        Assert::assertSame($expected, (string) $vat);
    } else {
        try {
            VatNumber::from($input, $country);
            Assert::fail('Expected an invalid VAT number.');
        } catch (InvalidIdentifierException $exception) {
            Assert::assertSame($expected, $exception->reason);
            Assert::assertSame('Invalid VAT number: '.$expected->value.'.', $exception->getMessage());
        }
    }
})->with(VatCases::parsing());

it('is readonly with a private constructor', function (): void {
    $reflection = new ReflectionClass(VatNumber::class);
    Assert::assertTrue($reflection->isReadOnly());
    Assert::assertTrue($reflection->getConstructor()?->isPrivate());
});

it('exposes the Swiss prefix and ISO country separately', function (): void {
    $vat = VatNumber::from('CHE116281710');
    Assert::assertSame('CHE', $vat->prefix);
    Assert::assertSame('CH', $vat->countryCode);
    Assert::assertSame('116281710', $vat->number);
});

it('builds VAT numbers from SIRENs', function (string|Stringable $input, string|FailureReason $expected): void {
    if (is_string($expected)) {
        Assert::assertSame($expected, VatNumber::fromSiren($input)->value);
    } else {
        try {
            VatNumber::fromSiren($input);
            Assert::fail('Expected an invalid SIREN.');
        } catch (InvalidIdentifierException $exception) {
            Assert::assertSame($expected, $exception->reason);
        }
    }
})->with([
    ['552032534', 'FR27552032534'], ['552 032 534', 'FR27552032534'],
    [new class implements Stringable
    {
        public function __toString(): string
        {
            return '552032534';
        }
    }, 'FR27552032534'],
    ['000000000', FailureReason::InvalidChecksum], ['000000018', FailureReason::InvalidChecksum],
    ['552032535', FailureReason::InvalidChecksum], ['000042183', FailureReason::InvalidChecksum],
    ['55203253', FailureReason::InvalidLength], ['55203253A', FailureReason::InvalidCharacters],
]);

it('normalizes Greek aliases and exposes ISO metadata', function (): void {
    $vat = VatNumber::from('GR094014201');
    Assert::assertSame('EL094014201', $vat->value);
    Assert::assertSame('EL', $vat->prefix);
    Assert::assertSame('094014201', $vat->number);
    Assert::assertSame('GR', $vat->countryCode);
    Assert::assertSame('EL094014201', VatNumber::from('094014201', 'GR')->value);
    Assert::assertSame('EL094014201', VatNumber::from('GR094014201', 'EL')->value);
});

it('exposes the Northern Irish VAT prefix and British ISO country', function (): void {
    $vat = VatNumber::from('XI220430231');
    Assert::assertSame('XI', $vat->prefix);
    Assert::assertSame('GB', $vat->countryCode);
});

it('round trips national numbers beginning with an own or foreign country prefix', function (string $full): void {
    $vat = VatNumber::from($full);
    Assert::assertEquals($vat, VatNumber::from($vat->number, country: $vat->countryCode));
    Assert::assertEquals($vat, VatNumber::from($full, country: $vat->countryCode));
})->with(['FRFR552032799', 'NLNL0000000B27', 'FRSE552032799', 'NLBE0000000B48']);
