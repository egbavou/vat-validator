<?php

declare(strict_types=1);

use Egbavou\VatValidator\Internal\Checksum;
use PHPUnit\Framework\Assert;

it('checks Luhn digits', function (string $digits, bool $valid): void {
    Assert::assertSame($valid, Checksum::luhn($digits));
})->with(['standard' => ['79927398713', true], 'changed check' => ['79927398710', false], 'SIREN' => ['552032534', true]]);

it('checks ISO 7064 MOD 11,10', function (string $digits, bool $valid): void {
    Assert::assertSame($valid, Checksum::iso7064Mod11_10($digits));
})->with(['Siemens' => ['129274202', true], 'changed check' => ['129274203', false], 'BMF' => ['111111125', true]]);

it('checks ISO 7064 MOD 97-10 without integer overflow', function (string $value, bool $valid): void {
    Assert::assertSame($valid, Checksum::iso7064Mod97_10($value));
})->with(['BMF' => ['NL123456789B13', true], 'changed check' => ['NL123456789B14', false], 'synthetic' => ['NL001000000B24', true], 'symbols' => ['NL+*0000000B43', true]]);

it('calculates a weighted sum', function (string $digits, array $weights, int $sum): void {
    $integers = [];
    foreach ($weights as $weight) {
        Assert::assertIsInt($weight);
        $integers[] = $weight;
    }
    Assert::assertSame($sum, Checksum::weightedSum($digits, $integers));
})->with([['123', [3, 2, 1], 10], ['9876', [1, 2, 3], 46]]);

it('sums decimal digits', function (string $digits, int $sum): void {
    Assert::assertSame($sum, Checksum::digitSum($digits));
})->with([['1234567890', 45], ['18', 9], ['', 0]]);
