<?php

declare(strict_types=1);

namespace Egbavou\VatValidator\Laravel\Rules;

use Closure;
use Egbavou\VatValidator\CheckStatus;
use Egbavou\VatValidator\InvalidIdentifierException;
use Egbavou\VatValidator\Laravel\Rules\Concerns\ResolvesCountry;
use Egbavou\VatValidator\VatNumber;
use Egbavou\VatValidator\VatNumberChecker;
use Illuminate\Container\Container;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

final class VatNumberExistsRule implements DataAwareRule, ValidationRule
{
    use ResolvesCountry;

    private bool $strict = false;

    public function failWhenUnavailable(): static
    {
        $this->strict = true;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('vat-validator::validation.invalid_characters')->translate();

            return;
        }
        try {
            $number = VatNumber::from($value, $this->resolveCountry());
        } catch (InvalidIdentifierException $exception) {
            $fail('vat-validator::validation.'.$exception->reason->value)->translate();

            return;
        }
        $checker = Container::getInstance()->make(VatNumberChecker::class);
        $status = $checker->check($number)->status;
        if ($status === CheckStatus::NotFound) {
            $fail('vat-validator::validation.not_found')->translate();
        } elseif ($status === CheckStatus::Unavailable && $this->strict) {
            $fail('vat-validator::validation.unavailable')->translate();
        }
    }
}
