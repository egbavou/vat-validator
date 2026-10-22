<?php

declare(strict_types=1);

use Egbavou\VatValidator\FailureReason;
use Egbavou\VatValidator\Laravel\Rules\VatNumberExistsRule;
use Egbavou\VatValidator\Laravel\Rules\VatNumberRule;
use Egbavou\VatValidator\Tests\Support\FakeHttpClient;
use Egbavou\VatValidator\VatNumberChecker;
use GuzzleHttp\Psr7\HttpFactory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Validator as ValidatorFacade;
use PHPUnit\Framework\Assert;

/** @param array<string, mixed> $data */
function validateVat(mixed $value, ValidationRule $rule, string $locale = 'en', array $data = []): Validator
{
    App::setLocale($locale);

    return ValidatorFacade::make(['identifier' => $value] + $data, ['identifier' => [$rule]]);
}

function vatMessage(string $reason, string $locale): string
{
    $messages = require __DIR__.'/../../lang/'.$locale.'/validation.php';
    Assert::assertIsArray($messages);
    Assert::assertIsString($messages[$reason]);

    return str_replace(':attribute', 'identifier', $messages[$reason]);
}

function bindVatFixture(string $fixture): FakeHttpClient
{
    $client = new FakeHttpClient([FakeHttpClient::fixture($fixture)]);
    $factory = new HttpFactory;
    App::instance(VatNumberChecker::class, new VatNumberChecker($client, $factory, $factory));

    return $client;
}

it('translates every failure reason for both rules in both languages', function (string $input, ?string $country, FailureReason $reason): void {
    App::bind(VatNumberChecker::class, function (): never {
        throw new LogicException('Invalid formats must not resolve a checker.');
    });
    foreach (['en', 'fr'] as $locale) {
        foreach ([new VatNumberRule(country: $country), new VatNumberExistsRule(country: $country)] as $rule) {
            $validator = validateVat($input, $rule, $locale);
            Assert::assertTrue($validator->fails());
            Assert::assertSame(vatMessage($reason->value, $locale), $validator->errors()->first('identifier'));
        }
    }
})->with([
    ['FRIO334175221', null, FailureReason::InvalidCharacters],
    ['FR123', null, FailureReason::InvalidLength],
    ['FR28552032534', null, FailureReason::InvalidChecksum],
    ['BE2123456791', null, FailureReason::InvalidStructure],
    ['ZZ27552032534', null, FailureReason::UnknownCountryPrefix],
    ['27552032534', null, FailureReason::MissingCountryPrefix],
    ['BE0403170701', 'FR', FailureReason::CountryMismatch],
]);

it('rejects non-string values with the invalid characters message', function (mixed $value): void {
    foreach (['en', 'fr'] as $locale) {
        foreach ([new VatNumberRule, new VatNumberExistsRule] as $rule) {
            $validator = validateVat($value, $rule, $locale);
            Assert::assertTrue($validator->fails());
            Assert::assertSame(vatMessage('invalid_characters', $locale), $validator->errors()->first('identifier'));
        }
    }
})->with([[123], [true], [null], [[]], [new stdClass]]);

it('leaves an empty string to required', function (): void {
    foreach ([new VatNumberRule, new VatNumberExistsRule] as $rule) {
        Assert::assertFalse(validateVat('', $rule)->fails());
        Assert::assertTrue(ValidatorFacade::make(['identifier' => ''], ['identifier' => ['required', $rule]])->fails());
    }
});

it('resolves fixed and data-aware countries for both rules', function (string $value, ?string $country, ?string $field, array $data, bool $valid): void {
    $fields = [];
    foreach ($data as $key => $fieldValue) {
        Assert::assertIsString($key);
        $fields[$key] = $fieldValue;
    }
    foreach ([new VatNumberRule($country, $field), new VatNumberExistsRule($country, $field)] as $rule) {
        $client = bindVatFixture('post-valid-fr.json');
        Assert::assertSame(! $valid, validateVat($value, $rule, data: $fields)->fails());
        Assert::assertCount($valid && $rule instanceof VatNumberExistsRule ? 1 : 0, $client->requests);
    }
})->with([
    ['27552032534', 'FR', null, [], true],
    ['27552032534', null, 'vat_country', ['vat_country' => 'FR'], true],
    ['27552032534', null, 'company.country', ['company' => ['country' => 'FR']], true],
    ['27552032534', 'FR', 'vat_country', ['vat_country' => 'BE'], true],
    ['FR27552032534', null, null, [], true],
    ['FR27552032534', null, 'vat_country', ['vat_country' => ''], true],
    ['FR27552032534', null, 'vat_country', [], true],
    ['27552032534', null, 'vat_country', ['vat_country' => ''], false],
    ['27552032534', null, 'vat_country', [], false],
    ['27552032534', null, 'vat_country', ['vat_country' => 123], false],
    ['27552032534', null, 'vat_country', ['vat_country' => ' '], false],
]);

it('validates a format without resolving the checker', function (): void {
    App::bind(VatNumberChecker::class, function (): never {
        throw new LogicException('Format validation must not resolve a checker.');
    });
    Assert::assertFalse(validateVat('FR27552032534', new VatNumberRule)->fails());
});

it('maps existence outcomes and translates their messages', function (string $input, string $fixture, bool $strict, ?string $message): void {
    foreach (['en', 'fr'] as $locale) {
        $client = bindVatFixture($fixture);
        $rule = new VatNumberExistsRule;
        if ($strict) {
            Assert::assertSame($rule, $rule->failWhenUnavailable());
        }
        $validator = validateVat($input, $rule, $locale);
        Assert::assertSame($message !== null, $validator->fails());
        if ($message !== null) {
            Assert::assertSame(vatMessage($message, $locale), $validator->errors()->first('identifier'));
        }
        Assert::assertCount(1, $client->requests);
    }
})->with([
    ['FR27552032534', 'post-valid-fr.json', false, null],
    ['FR27552032534', 'post-valid-fr.json', true, null],
    ['FR44732829320', 'post-invalid-fr.json', false, 'not_found'],
    ['FR44732829320', 'post-invalid-fr.json', true, 'not_found'],
    ['FR27552032534', 'post-error-ms-max-concurrent-req-fr.json', false, null],
    ['FR27552032534', 'post-error-ms-max-concurrent-req-fr.json', true, 'unavailable'],
]);
