# VAT Validator

Offline VAT number validation for PHP 8.3+, with an optional VIES existence check and Laravel 12/13 validation rules. MIT licensed.

```sh
composer require egbavou/vat-validator
```

The core needs only PSR HTTP interfaces. Supply a PSR-18 client and PSR-17 request/stream factories for VIES. Laravel integration uses Guzzle (`guzzlehttp/guzzle`) and is discovered automatically.

## Offline validation

```php
use Egbavou\VatValidator\VatNumber;

VatNumber::isValid('fr 27 552 032 534'); // true
VatNumber::tryFrom('FR123');           // null
$vat = VatNumber::from('27552032534', country: 'FR');
$vat = VatNumber::fromSiren('552 032 534'); // also accepts Stringable

$vat->value;       // FR27552032534
$vat->prefix;      // FR (VAT prefix)
$vat->number;      // 27552032534 (national part)
$vat->countryCode; // FR (ISO country)
$vat->format();    // FR27552032534
(string) $vat;     // FR27552032534
```

`VatNumber` is an immutable value object with a private constructor. `from()` throws `InvalidIdentifierException`, whose `reason` is a `FailureReason`. The stable exception message is `Invalid VAT number: {reason value}.` Validation checks characters, length, structure, then checksum.

| Reason | Meaning |
|---|---|
| `invalid_characters` | Characters outside the national alphabet |
| `invalid_length` | Wrong national length or empty input |
| `invalid_structure` | Forbidden first digit, suffix, office code or number range |
| `invalid_checksum` | Wrong national check digits |
| `unknown_country_prefix` | Unsupported prefix or country parameter |
| `missing_country_prefix` | National input without a country parameter |
| `country_mismatch` | Input prefix and country parameter disagree |

All EU-27 countries and Northern Ireland (`XI`) are covered. Checksums are verified where supported; Latvian personal codes, Czech nine-digit VČP starting with `6`, and Bulgarian ten-digit natural-person numbers use format-only validation. GB, Switzerland and Norway have offline format validation, including their documented checksum, without an online existence source. Passing offline validation does not establish VAT registration.

Spaces (including Unicode space separators), dots and hyphens are removed; letters become uppercase. Country parameters accept ISO codes or VAT prefixes. Greek `GR` becomes `EL`, whose ISO country is `GR`. `XI` has ISO country `GB`. Swiss `CHE` has ISO country `CH`; optional `MWST`, `TVA` and `IVA` suffixes are removed. Norwegian `MVA` is removed. Legacy nine-digit Belgian numbers are padded with `0`; Greek eight-digit numbers are rejected. French alphanumeric keys and Monaco numbers beginning with `000` in the SIREN part are supported. `fromSiren()` requires a genuine nine-digit SIREN passing Luhn, including for `Stringable` inputs.

Country algorithms and source choices are documented in [the country research](docs/research/2026-10-22-country-rules.md). BMF primary rules take precedence over conflicting libraries. Where secondary sources disagree without a primary rule, validation uses the permissive choice and leaves registration to VIES.

To follow: Spain's ministry draft of 23/06/2026 proposes alphanumeric entity NIFs. It is not in force; the current Spanish rules remain unchanged.

## VIES checks

```php
use Egbavou\VatValidator\VatNumberChecker;

// $client: Psr\Http\Client\ClientInterface
// $factory: both Psr\Http\Message\RequestFactoryInterface and StreamFactoryInterface
$checker = new VatNumberChecker($client, $factory, $factory, requester: 'BE0417497106');
$result = $checker->check('FR27552032534'); // also accepts VatNumber, or country: 'FR'
```

The checker uses the official REST `POST /check-vat-number` endpoint. It sends the canonical prefix and national number separately. It never retries; the application decides whether and when to try again.

| `CheckStatus` | Meaning |
|---|---|
| `Found` | VIES confirms registration |
| `NotFound` | VIES explicitly reports an invalid registration |
| `InvalidFormat` | Offline validation failed; no HTTP request |
| `Unavailable` | No source, registry error or technical failure |

The immutable result includes `vatNumber` (null for invalid format), `checkedAt`, `name`, `address`, `reference`, `unavailableReason`, `registryErrorCode` and `failure`. `checkedAt` uses the UTC VIES consultation timestamp, or the current UTC time when no registry timestamp is available. Name/address lines are trimmed, empty lines removed and internal spaces preserved. `---`, an empty string and `N/A` become null.

For `Unavailable`, `UnavailableReason` distinguishes `NoSource`, `RegistryError`, `Transport`, `HttpStatus`, `InvalidJson` and `Schema`. Registry failures preserve the exact `registryErrorCode`, such as `MS_MAX_CONCURRENT_REQ`. An error accompanying a false validity flag cannot become `NotFound`. GB, CH and NO return `NoSource` without sending a request. Other statuses have no unavailability reason.

For logging technical failures, `failure` contains the original throwable for `Transport` or `InvalidJson`, and an internal exception with a short diagnostic for `HttpStatus` or `Schema`. Transport failures carry the HTTP client's own exception, whose message is not controlled by this package; messages written by the package (HttpStatus, Schema) never contain the response body, the VAT number, a name or an address. It is null for all other outcomes, including `NoSource` and `RegistryError`; log `registryErrorCode` for registry errors.

A valid EU/XI requester enables the consultation receipt in `reference`; invalid or non-VIES requesters are ignored. The receipt proves the consultation, not the requester's identity. Under Laravel, configure **your company's VAT number** with `VAT_VALIDATOR_REQUESTER`. The default Laravel timeout is **20 seconds**; outside Laravel, configure the injected client. VIES has [scheduled downtimes](docs/research/2026-10-22-country-rules.md#46-autres-points-utiles-à-limplémentation) and member states may temporarily refuse checks.

## Laravel

```php
use Egbavou\VatValidator\Laravel\Rules\VatNumberRule;
use Egbavou\VatValidator\Laravel\Rules\VatNumberExistsRule;

$rules = [
    'vat_number' => ['required', new VatNumberRule(countryFrom: 'vat_country')],
    // Alternatives: new VatNumberRule(country: 'FR'), or new VatNumberRule for prefixed input.
];

$onlineRules = [
    'vat_number' => ['required', (new VatNumberExistsRule(countryFrom: 'company.country'))
        ->failWhenUnavailable()],
];
```

A fixed `country` takes precedence over `countryFrom`, which supports nested dot paths. An absent, empty or non-string country field requires prefixed input. Non-string VAT values fail with `invalid_characters`. Empty strings are left to Laravel's `required` rule.

The existence rule checks format before resolving the checker. It fails on invalid format and `NotFound`. `Unavailable` passes by default; `failWhenUnavailable()` makes it fail. The format rule performs no HTTP work.

```sh
php artisan vendor:publish --tag=vat-validator-config
php artisan vendor:publish --tag=vat-validator-lang
```

Configuration is published to `config/vat-validator.php`:

```php
'vies' => [
    'timeout' => env('VAT_VALIDATOR_VIES_TIMEOUT', 20),
    'requester' => env('VAT_VALIDATOR_REQUESTER'),
],
```

English and French messages use `vat-validator::validation.<reason>`, plus `not_found` and `unavailable`. Override them in `lang/vendor/vat-validator/{en,fr}/validation.php`. The provider binds `VatNumberChecker` as a singleton.

## Development and scope

```sh
XDEBUG_MODE=off vendor/bin/pest
XDEBUG_MODE=off vendor/bin/pest --testsuite=core
XDEBUG_MODE=off vendor/bin/phpstan analyse --debug
XDEBUG_MODE=off vendor/bin/pint --test
composer validate --strict
```

Tests use recorded fixtures and fake HTTP clients. The core has no Laravel or Guzzle dependency; dragonbe is a development-only cross-check, with documented source-backed divergences. This package provides no VAT rates, reverse-charge logic, SIRET validation or tax calculations.

See [LICENSE](LICENSE).
