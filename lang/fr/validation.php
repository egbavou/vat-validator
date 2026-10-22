<?php

declare(strict_types=1);

return [
    'invalid_characters' => 'Le champ :attribute contient des caractères interdits dans un numéro de TVA.',
    'invalid_length' => 'Le champ :attribute n\'a pas la longueur d\'un numéro de TVA de ce pays.',
    'invalid_checksum' => 'Le champ :attribute n\'est pas un numéro de TVA valide (clé de contrôle incorrecte).',
    'invalid_structure' => 'Le champ :attribute n\'a pas la structure d\'un numéro de TVA de ce pays.',
    'unknown_country_prefix' => 'Le champ :attribute commence par un code pays inconnu.',
    'missing_country_prefix' => 'Le champ :attribute doit commencer par un code pays, comme FR ou DE.',
    'country_mismatch' => 'Le champ :attribute ne correspond pas au pays sélectionné.',
    'not_found' => 'Le numéro de TVA indiqué dans :attribute n\'est pas enregistré dans VIES.',
    'unavailable' => 'Le numéro de TVA indiqué dans :attribute ne peut pas être vérifié pour le moment. Réessayez plus tard.',
];
