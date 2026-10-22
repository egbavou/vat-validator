<?php

declare(strict_types=1);

namespace Egbavou\VatValidator;

/** Why a check ended with CheckStatus::Unavailable. Aligned with egbavou/benin-business-id. */
enum UnavailableReason: string
{
    case NoSource = 'no_source';
    case RegistryError = 'registry_error';
    case Transport = 'transport';
    case HttpStatus = 'http_status';
    case InvalidJson = 'invalid_json';
    case Schema = 'schema';
}
