<?php

declare(strict_types=1);

namespace Egbavou\VatValidator;

enum FailureReason: string
{
    case InvalidCharacters = 'invalid_characters';
    case InvalidLength = 'invalid_length';
    case InvalidChecksum = 'invalid_checksum';
    case InvalidStructure = 'invalid_structure';
    case UnknownCountryPrefix = 'unknown_country_prefix';
    case MissingCountryPrefix = 'missing_country_prefix';
    case CountryMismatch = 'country_mismatch';
}
