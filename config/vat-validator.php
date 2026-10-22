<?php

return [
    'vies' => [
        // Seconds before giving up; the result is then Unavailable (Transport). VIES took up to 11.8 s in tests.
        'timeout' => env('VAT_VALIDATOR_VIES_TIMEOUT', 20),

        // VAT number of YOUR company (e.g. 'BE0123456789'). When set, VIES returns a consultation number
        // (VatNumberCheckResult::$reference). It proves the consultation, not the requester's identity.
        'requester' => env('VAT_VALIDATOR_REQUESTER'),
    ],
];
