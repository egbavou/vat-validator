<?php

declare(strict_types=1);

namespace Egbavou\VatValidator;

use DateTimeImmutable;
use Throwable;

final readonly class VatNumberCheckResult
{
    /** @internal Results are created by VatNumberChecker. */
    public function __construct(
        public CheckStatus $status,
        public DateTimeImmutable $checkedAt,
        public ?VatNumber $vatNumber,
        public ?string $name = null,
        public ?string $address = null,
        public ?string $reference = null,
        public ?UnavailableReason $unavailableReason = null,
        public ?string $registryErrorCode = null,
        public ?Throwable $failure = null,
    ) {}
}
