<?php

declare(strict_types=1);

return [
    'invalid_characters' => 'The :attribute field contains characters that are not allowed in a VAT number.',
    'invalid_length' => 'The :attribute field does not have the length of a VAT number for this country.',
    'invalid_checksum' => 'The :attribute field is not a valid VAT number (wrong check digits).',
    'invalid_structure' => 'The :attribute field does not have the structure of a VAT number for this country.',
    'unknown_country_prefix' => 'The :attribute field starts with an unknown country prefix.',
    'missing_country_prefix' => 'The :attribute field must start with a country prefix, such as FR or DE.',
    'country_mismatch' => 'The :attribute field does not belong to the selected country.',
    'not_found' => 'The VAT number in :attribute is not registered in VIES.',
    'unavailable' => 'The VAT number in :attribute cannot be verified right now. Please try again later.',
];
