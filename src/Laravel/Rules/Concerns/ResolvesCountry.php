<?php

declare(strict_types=1);

namespace Egbavou\VatValidator\Laravel\Rules\Concerns;

use Illuminate\Support\Arr;

/** @internal Shared DataAwareRule country resolution. */
trait ResolvesCountry
{
    /** @var array<string, mixed> */
    private array $data = [];

    public function __construct(private readonly ?string $country = null, private readonly ?string $countryFrom = null) {}

    /** @param array<string, mixed> $data */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    private function resolveCountry(): ?string
    {
        if ($this->country !== null) {
            return $this->country;
        }
        $value = $this->countryFrom === null ? null : Arr::get($this->data, $this->countryFrom);

        return is_string($value) && trim($value) !== '' ? $value : null;
    }
}
