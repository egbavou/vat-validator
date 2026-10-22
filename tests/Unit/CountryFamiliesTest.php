<?php

declare(strict_types=1);

use Egbavou\VatValidator\FailureReason;
use Egbavou\VatValidator\InvalidIdentifierException;
use Egbavou\VatValidator\VatNumber;
use PHPUnit\Framework\Assert;

// Synthetic controls calculated from research §3; these do not assert registration.
it('validates each national checksum family independently', function (string $valid, string $invalid, FailureReason $reason): void {
    Assert::assertSame($valid, VatNumber::from($valid)->value);
    Assert::assertFalse(VatNumber::isValid($invalid));
    try {
        VatNumber::from($invalid);
        Assert::fail('Expected the family control to fail.');
    } catch (InvalidIdentifierException $exception) {
        Assert::assertSame($reason, $exception->reason);
    }
})->with([
    'CZ IČO eight digits' => ['CZ00177041', 'CZ00177042', FailureReason::InvalidChecksum],
    'CZ IČO remainder zero' => ['CZ00000001', 'CZ00000002', FailureReason::InvalidChecksum],
    'CZ IČO remainder one' => ['CZ00000060', 'CZ00000061', FailureReason::InvalidChecksum],
    'CZ nine-digit rodné číslo date only' => ['CZ530101123', 'CZ540101123', FailureReason::InvalidStructure],
    'CZ nine-digit female month' => ['CZ005101999', 'CZ001301123', FailureReason::InvalidStructure],
    'CZ nine-digit VČP format only' => ['CZ699004743', 'CZ69900474', FailureReason::InvalidChecksum],
    'CZ ten-digit rodné číslo' => ['CZ8001010006', 'CZ8001010000', FailureReason::InvalidChecksum],
    'CZ ten-digit female month' => ['CZ8051010000', 'CZ8051010001', FailureReason::InvalidChecksum],
    'CZ ten-digit month plus twenty' => ['CZ0421010007', 'CZ0421010008', FailureReason::InvalidChecksum],
    'CZ ten-digit old remainder ten' => ['CZ8001010040', 'CZ8001010041', FailureReason::InvalidChecksum],
    'BG first weights' => ['BG100000001', 'BG100000002', FailureReason::InvalidChecksum],
    'BG second weights' => ['BG100000086', 'BG100000087', FailureReason::InvalidChecksum],
    'BG second remainder ten becomes zero' => ['BG100000550', 'BG100000551', FailureReason::InvalidChecksum],
    'IE old letter' => ['IE8Z49289F', 'IE8Z49289G', FailureReason::InvalidChecksum],
    'IE old plus' => ['IE8+49289F', 'IE8+49289G', FailureReason::InvalidChecksum],
    'IE old star' => ['IE8*49289F', 'IE8*49289G', FailureReason::InvalidChecksum],
    'IE new eight characters' => ['IE3628739L', 'IE3628739M', FailureReason::InvalidChecksum],
    'IE new nine characters' => ['IE3628739UA', 'IE3628739VA', FailureReason::InvalidChecksum],
    'ES DNI' => ['ES12345678Z', 'ES12345678A', FailureReason::InvalidChecksum],
    'ES NIE X' => ['ESX1234567L', 'ESX1234567M', FailureReason::InvalidChecksum],
    'ES NIE Y' => ['ESY1234567X', 'ESY1234567Y', FailureReason::InvalidChecksum],
    'ES NIE Z' => ['ESZ1234567R', 'ESZ1234567S', FailureReason::InvalidChecksum],
    'ES KLM' => ['ESK1234567L', 'ESK1234567M', FailureReason::InvalidChecksum],
    'ES CIF digit' => ['ESA28015865', 'ESA28015866', FailureReason::InvalidChecksum],
    'ES CIF letter' => ['ESQ2826000H', 'ESQ2826000I', FailureReason::InvalidChecksum],
    'NL mod eleven' => ['NL805734958B01', 'NL805734959B01', FailureReason::InvalidChecksum],
    'NL mod eleven remainder ten rejected' => ['NL003028112B01', 'NL100000060B01', FailureReason::InvalidChecksum],
    'NL mod ninety-seven numeric B layout' => ['NL123456789B13', 'NL123456789B14', FailureReason::InvalidChecksum],
    'NL mod ninety-seven general alphabet' => ['NLABCDEFGHIJ27', 'NLABCDEFGHIJ28', FailureReason::InvalidChecksum],
    'NL mod ninety-seven symbols' => ['NL+*ABCDEFGH72', 'NL+*ABCDEFGH73', FailureReason::InvalidChecksum],
    'SE Luhn' => ['SE556012579001', 'SE556012579101', FailureReason::InvalidChecksum],
]);

it('enforces Swedish suffix boundaries independently of Luhn', function (string $suffix, ?FailureReason $reason): void {
    $number = 'SE5560125790'.$suffix;
    if ($reason === null) {
        Assert::assertSame($number, VatNumber::from($number)->value);
    } else {
        try {
            VatNumber::from($number);
            Assert::fail('Expected the suffix to fail.');
        } catch (InvalidIdentifierException $exception) {
            Assert::assertSame($reason, $exception->reason);
        }
    }
})->with([
    ['00', FailureReason::InvalidStructure], ['01', null], ['94', null],
    ['95', FailureReason::InvalidStructure], ['99', FailureReason::InvalidStructure],
]);

it('covers both British prefixes and their MOD97 MOD9755 GD HA and branch families', function (string $prefix): void {
    foreach (['123456782', '123456727', '123456782001', '123456727999', 'GD000', 'GD499', 'HA500', 'HA999'] as $national) {
        Assert::assertTrue(VatNumber::isValid($prefix.$national), $national);
    }
    foreach (['123456783', '123456728', '123456783001', '123456728999'] as $national) {
        try {
            VatNumber::from($prefix.$national);
            Assert::fail('Expected the checksum to fail.');
        } catch (InvalidIdentifierException $exception) {
            Assert::assertSame(FailureReason::InvalidChecksum, $exception->reason);
        }
    }
    foreach (['GD500', 'HA499', '123456782000', '010000035', '010000090', '950000087'] as $national) {
        try {
            VatNumber::from($prefix.$national);
            Assert::fail('Expected the range to fail.');
        } catch (InvalidIdentifierException $exception) {
            Assert::assertSame(FailureReason::InvalidStructure, $exception->reason);
        }
    }
})->with(['GB', 'XI']);
