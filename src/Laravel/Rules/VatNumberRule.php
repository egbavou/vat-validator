<?php

declare(strict_types=1);

namespace Egbavou\VatValidator\Laravel\Rules;

use Closure;
use Egbavou\VatValidator\InvalidIdentifierException;
use Egbavou\VatValidator\Laravel\Rules\Concerns\ResolvesCountry;
use Egbavou\VatValidator\VatNumber;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

final class VatNumberRule implements DataAwareRule, ValidationRule
{
    use ResolvesCountry;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('vat-validator::validation.invalid_characters')->translate();

            return;
        }
        try {
            VatNumber::from($value, $this->resolveCountry());
        } catch (InvalidIdentifierException $exception) {
            $fail('vat-validator::validation.'.$exception->reason->value)->translate();
        }
    }
}
