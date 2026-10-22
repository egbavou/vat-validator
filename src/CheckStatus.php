<?php

declare(strict_types=1);

namespace Egbavou\VatValidator;

enum CheckStatus: string
{
    case Found = 'found';
    case NotFound = 'not_found';
    case InvalidFormat = 'invalid_format';
    case Unavailable = 'unavailable';
}
