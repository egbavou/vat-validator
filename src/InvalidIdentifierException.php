<?php

declare(strict_types=1);

namespace Egbavou\VatValidator;

use InvalidArgumentException;

final class InvalidIdentifierException extends InvalidArgumentException
{
    public function __construct(public readonly FailureReason $reason)
    {
        parent::__construct("Invalid VAT number: {$reason->value}.");
    }
}
