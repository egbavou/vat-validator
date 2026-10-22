# vat-number Implementation Plan

> **For the executor (Codex CLI):** execute this plan task by task, in order. Follow TDD: write the test, run it and see it fail, implement, run it and see it pass. **Never run `git add`, `git commit` or any git write command** — the owner reviews and commits himself. **Never run `composer install`/`update`/`require`/`remove`** — dependencies are installed by the orchestrator after Task 1. Never call the real VIES service from tests. Code, comments and test names are in English. Run PHP tools with `XDEBUG_MODE=off`; run PHPStan with `--debug` (the sandbox blocks its parallel workers).

**Goal:** Build `egbavou/vat-number`, a PHP ^8.3 library that validates VAT numbers offline (EU-27 + XI with checksums, GB/CH/NO format) and optionally checks their existence on the EU VIES REST service, with Laravel 12/13 rules inside the package.

**Architecture:** A `final readonly` value object `VatNumber` parses the input (separators, prefix, aliases, country parameter) and delegates the national part to one internal rule class per prefix. `VatNumberChecker` posts to VIES through injected PSR-18/PSR-17 objects and maps every outcome to a typed `VatNumberCheckResult` without ever throwing. Laravel classes live under `src/Laravel/`; the core never references `Illuminate` or Guzzle.

**Tech Stack:** PHP 8.3–8.5, PSR-7/17/18, Pest 3/4, PHPStan 2 (level max), Laravel Pint, Orchestra Testbench 10/11, Guzzle (dev + Laravel integration), `dragonbe/vies` (dev-only cross-check oracle).

**Sources of truth (read before coding):**
- `docs/plans/2026-10-21-design.md` — validated design, including §9 (decisions of 2026-10-22).
- `docs/research/2026-10-22-country-rules.md` — per-country rules (§3), VIES API (§4), real test vectors.
- `tests/Fixtures/vies/` — 66 raw VIES responses. Do not modify them.
- Sibling packages `../france-business-id` and `../benin-business-id` — same tooling and conventions.

**Conventions:**
- `declare(strict_types=1);` everywhere in `src/` and `tests/`.
- Tests use `PHPUnit\Framework\Assert::assert*` (not `expect()`), except the architecture test.
- Exception message format: `Invalid VAT number: {reason value}.`
- Internal classes carry `/** @internal */`.

---

### Task 1: Package scaffolding

**Files:** create `composer.json`, `config/vat-number.php`, `phpunit.xml`, `phpstan.neon`, `.gitignore`, `.gitattributes`, `LICENSE`, `.github/workflows/ci.yml`, `tests/Pest.php`, `tests/Laravel/TestCase.php`.

**Step 1: `composer.json`**

```json
{
    "name": "egbavou/vat-number",
    "description": "Offline VAT number validation (EU-27, XI, GB, CH, NO) with an optional VIES existence check and Laravel validation rules.",
    "type": "library",
    "license": "MIT",
    "keywords": ["vat", "vat-number", "vies", "tva", "eu", "validation", "laravel"],
    "authors": [
        {
            "name": "Egbavou"
        }
    ],
    "require": {
        "php": "^8.3",
        "psr/http-client": "^1.0",
        "psr/http-factory": "^1.0",
        "psr/http-message": "^1.1 || ^2.0"
    },
    "require-dev": {
        "dragonbe/vies": "^2.3",
        "guzzlehttp/guzzle": "^7.8",
        "illuminate/support": "^12.0 || ^13.0",
        "illuminate/translation": "^12.0 || ^13.0",
        "illuminate/validation": "^12.0 || ^13.0",
        "laravel/pint": "^1.24",
        "orchestra/testbench": "^10.0 || ^11.0",
        "pestphp/pest": "^3.8 || ^4.0",
        "phpstan/phpstan": "^2.1"
    },
    "suggest": {
        "illuminate/support": "Required for the Laravel validation rules (^12.0 or ^13.0).",
        "guzzlehttp/guzzle": "PSR-18 client wired automatically by the Laravel integration (^7.8)."
    },
    "autoload": {
        "psr-4": {
            "Egbavou\\VatNumber\\": "src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "Egbavou\\VatNumber\\Tests\\": "tests/"
        }
    },
    "extra": {
        "laravel": {
            "providers": [
                "Egbavou\\VatNumber\\Laravel\\VatNumberServiceProvider"
            ]
        }
    },
    "config": {
        "allow-plugins": {
            "pestphp/pest-plugin": true
        },
        "sort-packages": true
    },
    "scripts": {
        "test": "pest",
        "analyse": "phpstan analyse",
        "format": "pint"
    }
}
```

`dragonbe/vies` requires `ext-soap`; it is a dev-only oracle (Task 7).

**Step 2: `config/vat-number.php`**

```php
<?php

return [
    'vies' => [
        // Seconds before giving up; the result is then Unavailable (Transport). VIES took up to 11.8 s in tests.
        'timeout' => env('VAT_NUMBER_VIES_TIMEOUT', 20),

        // VAT number of YOUR company (e.g. 'BE0123456789'). When set, VIES returns a consultation number
        // (VatNumberCheckResult::$reference). It proves the consultation, not the requester's identity.
        'requester' => env('VAT_NUMBER_REQUESTER'),
    ],
];
```

**Step 3: `phpunit.xml`, `phpstan.neon`, `.gitignore`, `.gitattributes`, `LICENSE`, `tests/Pest.php`, `tests/Laravel/TestCase.php`** — copy the files of `../france-business-id` and adapt names:
- namespace `Egbavou\FranceBusinessId` → `Egbavou\VatNumber`;
- provider `FranceBusinessIdServiceProvider` → `VatNumberServiceProvider`;
- `.gitattributes`: keep the same export-ignore lines; also export-ignore `/tests/Fixtures` is already covered by `/tests`.

**Step 4: `.github/workflows/ci.yml`** — copy `../france-business-id/.github/workflows/ci.yml`, then in **every** `shivammathur/setup-php@v2` step add `extensions: soap` (needed by `dragonbe/vies`). Keep the 3×3 matrix, the quality job and the lowest-dependencies job unchanged otherwise.

**Step 5: Stop** and report "Task 1 done".

#### Orchestrator step (not for Codex)

```bash
cd /Users/egbavou/MyProjets/Packages/vat-number
composer validate --strict
composer install --no-interaction
XDEBUG_MODE=off vendor/bin/pest --version
```

---

### Task 2: Enums, exception and result object

**Files:**
- Create: `src/FailureReason.php`, `src/InvalidIdentifierException.php`, `src/CheckStatus.php`, `src/UnavailableReason.php`, `src/VatNumberCheckResult.php`
- Test: `tests/Unit/TypesTest.php`

**Step 1: Failing test `tests/Unit/TypesTest.php`**

```php
<?php

declare(strict_types=1);

use Egbavou\VatNumber\CheckStatus;
use Egbavou\VatNumber\FailureReason;
use Egbavou\VatNumber\InvalidIdentifierException;
use Egbavou\VatNumber\UnavailableReason;
use Egbavou\VatNumber\VatNumberCheckResult;
use PHPUnit\Framework\Assert;

it('defines the failure reasons as snake_case translation keys', function (): void {
    Assert::assertSame(
        ['invalid_characters', 'invalid_length', 'invalid_checksum', 'invalid_structure', 'unknown_country_prefix', 'missing_country_prefix', 'country_mismatch'],
        array_map(static fn (FailureReason $reason): string => $reason->value, FailureReason::cases()),
    );
});

it('exposes the reason and a stable message on the exception', function (): void {
    $exception = new InvalidIdentifierException(FailureReason::InvalidStructure);

    Assert::assertSame(FailureReason::InvalidStructure, $exception->reason);
    Assert::assertSame('Invalid VAT number: invalid_structure.', $exception->getMessage());
    Assert::assertInstanceOf(InvalidArgumentException::class, $exception);
});

it('defines the check statuses and unavailability reasons', function (): void {
    Assert::assertSame(['found', 'not_found', 'invalid_format', 'unavailable'], array_map(static fn (CheckStatus $s): string => $s->value, CheckStatus::cases()));
    Assert::assertSame(
        ['no_source', 'registry_error', 'transport', 'http_status', 'invalid_json', 'schema'],
        array_map(static fn (UnavailableReason $r): string => $r->value, UnavailableReason::cases()),
    );
});

it('is an immutable result object', function (): void {
    Assert::assertTrue((new ReflectionClass(VatNumberCheckResult::class))->isReadOnly());
});
```

**Step 2:** run `vendor/bin/pest tests/Unit/TypesTest.php` → FAIL (classes missing).

**Step 3: Implement**

```php
<?php

declare(strict_types=1);

namespace Egbavou\VatNumber;

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
```

```php
<?php

declare(strict_types=1);

namespace Egbavou\VatNumber;

use InvalidArgumentException;

final class InvalidIdentifierException extends InvalidArgumentException
{
    public function __construct(public readonly FailureReason $reason)
    {
        parent::__construct("Invalid VAT number: {$reason->value}.");
    }
}
```

```php
<?php

declare(strict_types=1);

namespace Egbavou\VatNumber;

enum CheckStatus: string
{
    case Found = 'found';
    case NotFound = 'not_found';
    case InvalidFormat = 'invalid_format';
    case Unavailable = 'unavailable';
}
```

Same form and values as `../benin-business-id/src/CheckStatus.php`.

```php
<?php

declare(strict_types=1);

namespace Egbavou\VatNumber;

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
```

```php
<?php

declare(strict_types=1);

namespace Egbavou\VatNumber;

use DateTimeImmutable;

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
    ) {}
}
```

`VatNumber` does not exist yet: create it in Task 4. Until then this test file only needs the types it references, so run Step 4 after Task 4 Step 3 if PHPStan or autoloading complains.

**Step 4:** run the test → PASS.

---

### Task 3: Checksum helpers

**Files:**
- Create: `src/Internal/Checksum.php`
- Test: `tests/Unit/ChecksumTest.php`

Implement pure static helpers used by several countries, each documented with the algorithm it implements:
- `luhn(string $digits): bool`
- `iso7064Mod11_10(string $digits): bool` (DE, HR)
- `iso7064Mod97_10(string $alphanumeric): bool` (NL sole-proprietor numbers; letters map A=10…Z=35, `+`=36, `*`=37 as described in research §3 NL — check the exact mapping there)
- `weightedSum(string $digits, list<int> $weights): int`
- `digitSum(string $digits): int`

Tests: known vectors for each helper (Luhn: `79927398713` true, `79927398710` false; ISO 7064 MOD 11,10: a valid DE number from research §3 DE and the same with its last digit changed; MOD 97-10: the NL example from research §3 NL). Use datasets, `Assert::assertSame`.

---

### Task 4: Parsing core — `VatNumber`, rule contract, registry, and the FR, BE, CH, NO rules

**Files:**
- Create: `src/VatNumber.php`, `src/Internal/Countries/CountryRule.php`, `src/Internal/Countries/CountryRules.php`, `src/Internal/Countries/France.php`, `src/Internal/Countries/Belgium.php`, `src/Internal/Countries/Switzerland.php`, `src/Internal/Countries/Norway.php`
- Create: `tests/Support/VatCases.php`
- Test: `tests/Unit/VatNumberTest.php`, `tests/Unit/CountriesTest.php`

**Step 1: Rule contract**

```php
<?php

declare(strict_types=1);

namespace Egbavou\VatNumber\Internal\Countries;

use Egbavou\VatNumber\FailureReason;

/** @internal One implementation per VAT prefix. */
interface CountryRule
{
    /** VAT prefix as used by VIES and in the normalized value: 'EL', 'XI', 'CHE'. */
    public function prefix(): string;

    /** ISO 3166-1 alpha-2 code: 'GR' for EL, 'GB' for XI, 'CH' for CHE. */
    public function countryCode(): string;

    public function coveredByVies(): bool;

    /** Country-specific normalization of the national part (suffix removal, padding). Identity by default. */
    public function normalize(string $national): string;

    /** Null when valid; otherwise the first failing check, in the order characters, length, structure, checksum. */
    public function validate(string $national): ?FailureReason;
}
```

**Step 2: Registry `CountryRules`** — `/** @internal */ final class` with:
- a typed constant `RULES` mapping each prefix to its rule class: `AT, BE, BG, CY, CZ, DE, DK, EE, EL, ES, FI, FR, HR, HU, IE, IT, LT, LU, LV, MT, NL, PL, PT, RO, SE, SI, SK, XI, GB, CHE, NO` → `Austria, Belgium, Bulgaria, Cyprus, Czechia, Germany, Denmark, Estonia, Greece, Spain, Finland, France, Croatia, Hungary, Ireland, Italy, Lithuania, Luxembourg, Latvia, Malta, Netherlands, Poland, Portugal, Romania, Sweden, Slovenia, Slovakia, NorthernIreland, UnitedKingdom, Switzerland, Norway`. During Task 4 only FR, BE, CHE, NO exist: add the others to `RULES` as you create them in Tasks 5–6.
- aliases: input prefix `GR` → `EL`; country parameter `GR` → `EL`, `CH` → `CHE`.
- `forPrefix(string $prefix): ?CountryRule` (instances cached in a static array).
- `prefixForCountry(string $country): ?string` — accepts an ISO code (`FR`, `GR`, `CH`, `GB`, `XI`) or a VAT prefix (`EL`, `CHE`); returns the canonical prefix or null.
- `detectPrefix(string $value): ?array{prefix: string, length: int}` — tries `CHE` first, then every 2-letter prefix and the `GR` alias, at the start of `$value`.

**Step 3: `VatNumber`**

```php
<?php

declare(strict_types=1);

namespace Egbavou\VatNumber;

use Egbavou\VatNumber\Internal\Checksum;
use Egbavou\VatNumber\Internal\Countries\CountryRules;
use Stringable;

final readonly class VatNumber implements Stringable
{
    private const string SEPARATORS = '/[\p{Z}\x{0009}-\x{000D}\x{0085}.\-]+/u';

    private function __construct(
        public string $value,
        public string $prefix,
        public string $number,
        public string $countryCode,
    ) {}

    public static function isValid(string $input, ?string $country = null): bool
    {
        return self::tryFrom($input, $country) !== null;
    }

    public static function tryFrom(string $input, ?string $country = null): ?self
    {
        try {
            return self::from($input, $country);
        } catch (InvalidIdentifierException) {
            return null;
        }
    }

    /**
     * @throws InvalidIdentifierException
     */
    public static function from(string $input, ?string $country = null): self
    {
        $value = self::clean($input);
        $expected = null;

        if ($country !== null && trim($country) !== '') {
            $expected = CountryRules::prefixForCountry(strtoupper(trim($country)))
                ?? throw new InvalidIdentifierException(FailureReason::UnknownCountryPrefix);
        }

        [$prefix, $national] = self::split($value, $expected);
        $rule = CountryRules::forPrefix($prefix) ?? throw new InvalidIdentifierException(FailureReason::UnknownCountryPrefix);
        $national = $rule->normalize($national);
        $reason = $rule->validate($national);

        if ($reason !== null) {
            throw new InvalidIdentifierException($reason);
        }

        return new self($prefix.$national, $prefix, $national, $rule->countryCode());
    }

    /**
     * Builds the French VAT number of a SIREN. The SIREN must be 9 digits passing Luhn;
     * Monaco numbers (starting with 000) are not SIRENs and are rejected.
     *
     * @throws InvalidIdentifierException
     */
    public static function fromSiren(string|Stringable $siren): self
    {
        $digits = preg_replace(self::SEPARATORS, '', (string) $siren);

        if ($digits === null || preg_match('/\A[0-9]*\z/', $digits) !== 1) {
            throw new InvalidIdentifierException(FailureReason::InvalidCharacters);
        }

        if (strlen($digits) !== 9) {
            throw new InvalidIdentifierException(FailureReason::InvalidLength);
        }

        if (str_starts_with($digits, '000') || ! Checksum::luhn($digits)) {
            throw new InvalidIdentifierException(FailureReason::InvalidChecksum);
        }

        $key = (12 + 3 * ((int) $digits % 97)) % 97;

        return self::from(sprintf('FR%02d%s', $key, $digits));
    }

    public function format(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    /** @throws InvalidIdentifierException */
    private static function clean(string $input): string
    {
        $value = preg_replace(self::SEPARATORS, '', $input);

        if ($value === null) {
            throw new InvalidIdentifierException(FailureReason::InvalidCharacters);
        }

        $value = strtoupper($value);

        if ($value === '') {
            throw new InvalidIdentifierException(FailureReason::InvalidLength);
        }

        if (preg_match('/\A[0-9A-Z+*]+\z/', $value) !== 1) {
            throw new InvalidIdentifierException(FailureReason::InvalidCharacters);
        }

        return $value;
    }

    /**
     * @return array{string, string} canonical prefix, national part
     *
     * @throws InvalidIdentifierException
     */
    private static function split(string $value, ?string $expected): array
    {
        $detected = CountryRules::detectPrefix($value);

        if ($expected === null) {
            if ($detected === null) {
                throw new InvalidIdentifierException(
                    preg_match('/\A[A-Z]{2}/', $value) === 1 ? FailureReason::UnknownCountryPrefix : FailureReason::MissingCountryPrefix,
                );
            }

            return [$detected['prefix'], substr($value, $detected['length'])];
        }

        if ($detected !== null && $detected['prefix'] === $expected) {
            return [$expected, substr($value, $detected['length'])];
        }

        if ($detected !== null) {
            // Another country's prefix. The whole input may still be a valid national part of the
            // expected country (e.g. a French alphanumeric key 'SE…'); otherwise the countries disagree.
            $rule = CountryRules::forPrefix($expected);

            if ($rule !== null && $rule->validate($rule->normalize($value)) === null) {
                return [$expected, $value];
            }

            throw new InvalidIdentifierException(FailureReason::CountryMismatch);
        }

        return [$expected, $value];
    }
}
```

**Step 4: The four first rules**, per research §3 and design §9:
- `France` (prefix `FR`, ISO `FR`, VIES yes): national `^[0-9A-HJ-NP-Z]{2}\d{9}$`. Characters outside `[0-9A-HJ-NP-Z]` in the key (e.g. `I`, `O`) → `InvalidCharacters`; length ≠ 11 → `InvalidLength`; numeric key `(12 + 3 × (SIREN mod 97)) mod 97`; alphanumeric key: research §3 FR formula (`idx`, `s`, `(SIREN + 1 + floor(s / 11)) mod 11 = s mod 11`, **integer** division); SIREN must pass Luhn **unless it starts with `000`** (Monaco, key still checked) → otherwise `InvalidChecksum`.
- `Belgium` (`BE`): `normalize()` prepends `0` to a 9-digit national part; then `^[01]\d{9}$` (first digit other than 0/1 → `InvalidStructure`); `C9C10 = 97 − (int(C1…C8) mod 97)`.
- `Switzerland` (prefix `CHE`, ISO `CH`, VIES no): `normalize()` removes a trailing `MWST`, `TVA` or `IVA`; then 9 digits; eCH-0097 weights 5,4,3,2,7,6,5,4, `11 − r`, 11 → 0, 10 → `InvalidChecksum`.
- `Norway` (`NO`, VIES no): `normalize()` removes a trailing `MVA`; 9 digits; weights 3,2,7,6,5,4,3,2, `11 − r`, 11 → 0, 10 → `InvalidChecksum`.

**Step 5: Test data `tests/Support/VatCases.php`** — `final class VatCases` with static dataset methods (PHPStan-typed `@return` like `../france-business-id/tests/Support/IdentifierCases.php`):
- `validNumbers(): array<string, array{string, string}>` — input, expected normalized value. Start with the FR, BE, CH, NO rows of research §3 (every real number listed in each "Numéro valide" table, plus `FR0W314684960`, `FR73000042183`, `CHE-116.281.710 MWST`, `NO 974760673 MVA`, `BE403170701` → `BE0403170701`, `GR094014201` → `EL094014201` once Greece exists). Extend it in Tasks 5–6.
- `invalidNumbers(): array<string, array{string, FailureReason}>` — every row of the "Numéro invalide" tables, mapping « Structure » to `FailureReason::InvalidStructure` and following design §9 for BE (`BE041749710` → padded → `InvalidChecksum`).
- `parsing(): array<string, array{string, ?string, string|FailureReason}>` — input, country parameter, expected value or reason:
  - `['FR27552032534', null, 'FR27552032534']`, `['fr 27 552 032 534', null, 'FR27552032534']`
  - `['27552032534', 'FR', 'FR27552032534']`, `['27552032534', 'fr', 'FR27552032534']`, `['FR27552032534', 'FR', 'FR27552032534']`
  - `['27552032534', null, FailureReason::MissingCountryPrefix]`
  - `['ZZ27552032534', null, FailureReason::UnknownCountryPrefix]`, `['27552032534', 'ZZ', FailureReason::UnknownCountryPrefix]`
  - `['BE0403170701', 'FR', FailureReason::CountryMismatch]`
  - `['094014201', 'GR', 'EL094014201']`, `['GR094014201', 'EL', 'EL094014201']`
  - `['116281710', 'CH', 'CHE116281710']`
  - `['', null, FailureReason::InvalidLength]`, `['FR27/552032534', null, FailureReason::InvalidCharacters]`, `["FR27\u{180E}552032534", null, FailureReason::InvalidCharacters]`, `["FR27\xff552032534", null, FailureReason::InvalidCharacters]`
  - separators accepted: `"FR\u{00A0}27\u{202F}552.032-534"` → `'FR27552032534'`.

**Step 6: Tests**
- `tests/Unit/VatNumberTest.php`: the `parsing` dataset (assert value, or exception reason and message); properties for `GR094014201` (`value` `EL094014201`, `prefix` `EL`, `number` `094014201`, `countryCode` `GR`), for `XI` once it exists (`countryCode` `GB`) and for `CHE116281710` (`prefix` `CHE`, `countryCode` `CH`); `format()` and `(string)` equal `value`; private constructor; readonly class; `fromSiren`: `'552032534'` → `FR27552032534`, `'552 032 534'` accepted, `Stringable` accepted, `'552032535'` → `InvalidChecksum`, `'000042183'` → `InvalidChecksum`, `'55203253'` → `InvalidLength`, `'55203253A'` → `InvalidCharacters`.
- `tests/Unit/CountriesTest.php`: `validNumbers` (isValid, value) and `invalidNumbers` (reason) datasets; plus a guard that every prefix in `CountryRules::RULES` has at least 2 valid and 2 invalid vectors (expose the prefix list through a public static method `CountryRules::prefixes()` for the test).

**Step 7:** run the tests → PASS.

---

### Task 5: EU countries, part 1 — AT, BG, CY, CZ, DE, DK, EE, EL, ES, FI, HR, HU, IE, IT

For each prefix, create `src/Internal/Countries/<Country>.php` implementing **exactly** research §3 for that country, with these precedence rules (design §9):
1. When the BMF (primary source) and dragonbe/stdnum disagree, follow the BMF.
2. For countries without a primary source (BG, CY, CZ, EL, ES), when secondary sources disagree, be **permissive**: never reject a number that could be valid. In particular: ES accepts a CIF control character as digit or letter; CY does **not** exclude the `12` prefix (unsourced).
3. Structure violations (forbidden first digit, `10` required in EE, IT office code, etc.) → `FailureReason::InvalidStructure`.
4. EL: no padding of 8-digit inputs (→ `InvalidLength`).

Add each prefix to `CountryRules::RULES` and all its research vectors (valid and invalid) to `VatCases`. TDD per country: add the vectors first, run `vendor/bin/pest tests/Unit/CountriesTest.php`, see them fail, implement, see them pass. Every class gets a short docblock naming its source (BMF page or secondary source) as written in research §3.

---

### Task 6: EU countries, part 2 — LT, LU, LV, MT, NL, PL, PT, RO, SE, SI, SK, XI, GB

Same procedure and precedence rules as Task 5. Specifics:
- MT, LV, RO have no primary source → permissive (MT: congruence mod 37 rather than the exact `37 − r`; LV: personal codes without a confirmed key are accepted when they pass the structure checks).
- NL: try the mod-11 test first, then ISO 7064 MOD 97-10 on `NL` + national part (research §3 NL); a remainder of 10 in mod-11 does **not** become 0 (BMF); suffix `00` → `InvalidStructure`.
- SE: suffix `01`–`94` accepted (BMF).
- `NorthernIreland` (`XI`, ISO `GB`, VIES **yes**) and `UnitedKingdom` (`GB`, ISO `GB`, VIES **no**) share one implementation of the BMF rules (9 or 12 digits, `GD`/`HA` + 3 digits with their ranges, mod 97 and mod 97 "9755"); put it in an `@internal` abstract class or trait used by both.

After this task `CountriesTest`'s guard must pass for all 31 prefixes.

---

### Task 7: Cross-check against `dragonbe/vies`

**Files:** Test `tests/Unit/CrossCheckTest.php`.

Inspect `vendor/dragonbe/vies/src/Vies/Validator/` to find the validator class and method for each country (e.g. `ValidatorBE::validate(string): bool`). For every vector in `VatCases::validNumbers()` and `VatCases::invalidNumbers()` whose prefix dragonbe supports (EU-27 + XI), compare dragonbe's verdict on the national part with `VatNumber::isValid()`.

- Expected divergences are the rows marked **oui** in the "dragonbe" column of research §3, plus the official-rule conflicts listed in research §5 point 3. Keep them in a constant `DOCUMENTED_DIVERGENCES` (`input => one-line reason citing the source we follow`) and assert that dragonbe indeed diverges on them.
- If dragonbe disagrees on a vector that is not documented: **do not change `src/` to match dragonbe.** Re-read research §3 for that country. If our result follows the research document, add the vector to `DOCUMENTED_DIVERGENCES` with its reason. If our result contradicts the research document, our code is wrong: fix `src/` to follow the document. List every divergence you added, and every fix, in your final report.
- PHP deprecation notices emitted by dragonbe (it uses float division) must not fail the suite; if they do, wrap the dragonbe call with `@` or a local error handler, never change project-wide settings.

---

### Task 8: `VatNumberChecker`

**Files:**
- Create: `src/VatNumberChecker.php`, `tests/Support/FakeHttpClient.php`
- Test: `tests/Unit/CheckerTest.php`

**Step 1: `tests/Support/FakeHttpClient.php`**

```php
<?php

declare(strict_types=1);

namespace Egbavou\VatNumber\Tests\Support;

use GuzzleHttp\Psr7\Response;
use LogicException;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;

final class FakeHttpClient implements ClientInterface
{
    /** @var list<RequestInterface> */
    public array $requests = [];

    /** @param list<ResponseInterface|Throwable> $queue */
    public function __construct(private array $queue = []) {}

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->requests[] = $request;
        $next = array_shift($this->queue) ?? throw new LogicException('No response queued.');

        if ($next instanceof Throwable) {
            throw $next;
        }

        return $next;
    }

    public static function fixture(string $file, int $status = 200): ResponseInterface
    {
        $body = file_get_contents(__DIR__.'/../Fixtures/vies/'.$file);

        if ($body === false) {
            throw new LogicException("Missing fixture {$file}.");
        }

        return new Response($status, ['Content-Type' => 'application/json'], $body);
    }
}
```

**Step 2: Tests first** (`tests/Unit/CheckerTest.php`), each with a `FakeHttpClient` and `GuzzleHttp\Psr7\HttpFactory` (used as both PSR-17 factories). Cover, with the exact fixture files:

| Case | Input / queued response | Expected |
|---|---|---|
| valid | `BE0417497106` / `post-valid-be.json` | `Found`; name `NV Anheuser-Busch InBev`; address `"Grote Markt 1\n1000 Brussel"`; `reference` null; `checkedAt` = `2026-10-22T01:00:38.289Z` (UTC); `vatNumber->value` `BE0417497106` |
| valid, no name/address | `post-valid-de-without-name-address.json` | `Found`; name and address null |
| valid with requester | `post-valid-with-requester-dk.json` | `reference` = the `requestIdentifier` of the fixture |
| invalid | `FR44732829320` / `post-invalid-fr.json` | `NotFound`; name and address null |
| invalid, placeholder | `post-invalid-es-placeholder-name.json` | `NotFound`; name null |
| registry error | `post-error-ms-max-concurrent-req-fr.json` | `Unavailable`, `RegistryError`, `registryErrorCode` `MS_MAX_CONCURRENT_REQ` |
| every test-service error | each `post-test-service-error-*.json` | `Unavailable`, `RegistryError`, code read from the file |
| HTTP 400 / 500 | `post-http400-*.json` with status 400, `post-http500-*.json` with 500 | `Unavailable`, `HttpStatus` |
| HTML body in a 200 | `http404-not-found.html` served with status 200 | `Unavailable`, `InvalidJson` |
| schema | 200 with `{"foo":1}`; with `"text"`; with `valid` = `"true"`; with `valid` true but no `requestDate` | `Unavailable`, `Schema` |
| transport | the client throws a `Psr\Http\Client\ClientExceptionInterface` (anonymous class extending `RuntimeException`) | `Unavailable`, `Transport` |
| no source | `GB…`, `CHE116281710`, `NO974760673` (valid formats) | `Unavailable`, `NoSource`, **no request sent** |
| invalid format | `'FR123'` | `InvalidFormat`, `vatNumber` null, **no request sent** |
| VatNumber input | pass a `VatNumber` object | same as string |
| country parameter | `check('27552032534', 'FR')` | request body `vatNumber` = `27552032534`, `countryCode` = `FR` |

Request assertions: method `POST`; URI = `VatNumberChecker::DEFAULT_ENDPOINT`; header `Content-Type: application/json`; JSON body exactly `{"countryCode":"EL","vatNumber":"094014201"}` for input `GR094014201`; `countryCode` `XI` for an XI number; with requester `BE0417497106` the body also has `requesterMemberStateCode` `BE` and `requesterNumber` `0417497106`; a malformed requester (`FR123`) or a non-VIES requester (a valid `GB` or `CHE` number) is ignored (no requester fields).

Normalization of `name`/`address`: a unit case with an inline 200 body whose name is `"  \nFOO  BAR \n\nLINE 2\n"` → `"FOO  BAR\nLINE 2"` (each line trimmed, empty lines removed, inner spaces kept); `"---"`, `""` and `"N/A"` → null.

**Step 3: Implement `src/VatNumberChecker.php`**

```php
<?php

declare(strict_types=1);

namespace Egbavou\VatNumber;

use DateTimeImmutable;
use DateTimeZone;
use Egbavou\VatNumber\Internal\Countries\CountryRules;
use JsonException;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Throwable;

final class VatNumberChecker
{
    public const string DEFAULT_ENDPOINT = 'https://ec.europa.eu/taxation_customs/vies/rest-api/check-vat-number';

    /** Values VIES uses when a name or address is not available. */
    private const array PLACEHOLDERS = ['---', '', 'N/A'];

    private ?VatNumber $requester;

    public function __construct(
        private readonly ClientInterface $httpClient,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
        ?string $requester = null,
        private readonly string $endpoint = self::DEFAULT_ENDPOINT,
    ) {
        $candidate = $requester === null ? null : VatNumber::tryFrom($requester);
        $this->requester = $candidate !== null && (CountryRules::forPrefix($candidate->prefix)?->coveredByVies() ?? false)
            ? $candidate
            : null;
    }

    public function check(string|VatNumber $input, ?string $country = null): VatNumberCheckResult
    {
        $vatNumber = $input instanceof VatNumber ? $input : VatNumber::tryFrom($input, $country);

        if ($vatNumber === null) {
            return new VatNumberCheckResult(CheckStatus::InvalidFormat, self::now(), null);
        }

        if (! (CountryRules::forPrefix($vatNumber->prefix)?->coveredByVies() ?? false)) {
            return self::unavailable($vatNumber, UnavailableReason::NoSource);
        }

        try {
            $request = $this->requestFactory->createRequest('POST', $this->endpoint)
                ->withHeader('Content-Type', 'application/json')
                ->withHeader('Accept', 'application/json')
                ->withBody($this->streamFactory->createStream(json_encode($this->payload($vatNumber), JSON_THROW_ON_ERROR)));
            $response = $this->httpClient->sendRequest($request);
        } catch (Throwable) {
            return self::unavailable($vatNumber, UnavailableReason::Transport);
        }

        if ($response->getStatusCode() !== 200) {
            return self::unavailable($vatNumber, UnavailableReason::HttpStatus);
        }

        try {
            $data = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return self::unavailable($vatNumber, UnavailableReason::InvalidJson);
        }

        if (! is_array($data)) {
            return self::unavailable($vatNumber, UnavailableReason::Schema);
        }

        // VIES reports member-state errors in a 200 response: read errorWrappers before 'valid'.
        if (array_key_exists('errorWrappers', $data)) {
            $code = is_array($data['errorWrappers']) && is_array($data['errorWrappers'][0] ?? null)
                ? ($data['errorWrappers'][0]['error'] ?? null)
                : null;

            return self::unavailable($vatNumber, UnavailableReason::RegistryError, is_string($code) ? $code : null);
        }

        $checkedAt = is_string($data['requestDate'] ?? null)
            ? DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s.v\Z', $data['requestDate'], new DateTimeZone('UTC'))
            : false;

        if (! is_bool($data['valid'] ?? null) || $checkedAt === false) {
            return self::unavailable($vatNumber, UnavailableReason::Schema);
        }

        $reference = $data['requestIdentifier'] ?? null;

        return new VatNumberCheckResult(
            status: $data['valid'] ? CheckStatus::Found : CheckStatus::NotFound,
            checkedAt: $checkedAt,
            vatNumber: $vatNumber,
            name: self::text($data['name'] ?? null),
            address: self::text($data['address'] ?? null),
            reference: is_string($reference) && $reference !== '' ? $reference : null,
        );
    }

    /** @return array<string, string> */
    private function payload(VatNumber $vatNumber): array
    {
        // Only the national part: with the prefix repeated, VIES answers valid=false without error.
        $payload = ['countryCode' => $vatNumber->prefix, 'vatNumber' => $vatNumber->number];

        if ($this->requester !== null) {
            $payload['requesterMemberStateCode'] = $this->requester->prefix;
            $payload['requesterNumber'] = $this->requester->number;
        }

        return $payload;
    }

    private static function unavailable(VatNumber $vatNumber, UnavailableReason $reason, ?string $registryErrorCode = null): VatNumberCheckResult
    {
        return new VatNumberCheckResult(
            status: CheckStatus::Unavailable,
            checkedAt: self::now(),
            vatNumber: $vatNumber,
            unavailableReason: $reason,
            registryErrorCode: $registryErrorCode,
        );
    }

    private static function text(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $lines = preg_split('/\R/u', $value) ?: [];
        $text = implode("\n", array_filter(array_map('trim', $lines), static fn (string $line): bool => $line !== ''));

        return in_array($text, self::PLACEHOLDERS, true) ? null : $text;
    }

    private static function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }
}
```

If PHPStan max rejects a construct above, fix the typing (narrowing, `@var`), never with an ignore.

**Step 4:** run `vendor/bin/pest tests/Unit/CheckerTest.php` → PASS.

---

### Task 9: Laravel integration

**Files:**
- Create: `src/Laravel/VatNumberServiceProvider.php`, `src/Laravel/Rules/Concerns/ResolvesCountry.php`, `src/Laravel/Rules/VatNumberRule.php`, `src/Laravel/Rules/VatNumberExistsRule.php`, `lang/en/validation.php`, `lang/fr/validation.php`
- Test: `tests/Laravel/RulesTest.php`, `tests/Laravel/ServiceProviderTest.php`

**Behaviour (design §5):**
- Both rules take `?string $country = null, ?string $countryFrom = null` and implement `ValidationRule` and `DataAwareRule`. The country is `$country` if given, else the value of the `$countryFrom` field (via `Illuminate\Support\Arr::get` on the validation data) when it is a non-empty string, else null. Put this in the `ResolvesCountry` trait.
- Non-string value → message `invalid_characters`. Format failure → message of its `FailureReason`.
- `VatNumberExistsRule` resolves `VatNumberChecker` with `Illuminate\Container\Container::getInstance()->make(...)` **only after** the format is valid, then: `NotFound` → `not_found`; `Unavailable` → passes unless `failWhenUnavailable()` was called (fluent, returns `static`) → `unavailable`; `Found` → passes.
- Message keys: `vat-number::validation.<key>`, keys = the 7 `FailureReason` values + `not_found` + `unavailable`.

**Provider:** `register()` merges `config/vat-number.php` under `vat-number` and binds `VatNumberChecker` as a singleton built with `new GuzzleHttp\Client(['timeout' => <config vies.timeout if numeric and > 0, else 20.0>])`, one `GuzzleHttp\Psr7\HttpFactory` as both factories, and `requester:` the config value when it is a non-empty string. `boot()` loads translations (`vat-number`) and publishes config (`vat-number-config`, to `config_path('vat-number.php')` via `$this->app->configPath(...)`) and lang (`vat-number-lang`, to `lang/vendor/vat-number`). Model it on `../benin-business-id/src/Laravel/BeninBusinessIdServiceProvider.php`.

**Messages:**

`lang/en/validation.php`:
```php
<?php

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
```

`lang/fr/validation.php`:
```php
<?php

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
```

**Tests** (model them on `../benin-business-id/tests/Laravel/RulesTest.php` and `ServiceProviderTest.php`):
- every `FailureReason` gives its message in en and fr, for both rules;
- non-string values (`123`, `true`, `null`, `[]`, object) → `invalid_characters`;
- `''` is left to `required` (rule not run);
- `country: 'FR'` and `countryFrom: 'vat_country'` (with the field present, empty and absent) work, including nested keys (`company.country`);
- the format rule never resolves the checker (bind a throwing closure for `VatNumberChecker`, as in the Benin test);
- `VatNumberExistsRule` with a `VatNumberChecker` built on `FakeHttpClient` and fixtures: Found passes; NotFound fails with `not_found`; an error fixture passes by default and fails with `unavailable` after `failWhenUnavailable()`;
- provider: singleton; timeout taken from config (assert the Guzzle client config through reflection, or bind and inspect); requester from config reaches the request body; translations loaded; both publish tags map the right source (`realpath`) to the right destination.

---

### Task 10: Architecture, README, full verification

**Step 1: `tests/Unit/ArchitectureTest.php`**

```php
<?php

declare(strict_types=1);

arch('keeps the core independent of Laravel and Guzzle', function (): void {
    expect('Egbavou\\VatNumber')
        ->not->toUse(['Illuminate', 'GuzzleHttp'])
        ->ignoring('Egbavou\\VatNumber\\Laravel');
});
```

**Step 2: `README.md`** (English): install; offline validation (`isValid`, `from`, `country:`, `fromSiren`, properties, reasons); covered countries (EU-27 + XI with checksums, GB/CH/NO format only; GR accepted as EL; CH/NO suffixes; old 9-digit BE numbers padded; Monaco FR numbers); VIES check (statuses, `unavailableReason`, `registryErrorCode`, no retries — the application decides; `reference` requires `VAT_NUMBER_REQUESTER` and proves the consultation, not the requester's identity; 20 s default timeout; scheduled VIES downtimes exist — link research §4.6); Laravel rules and config publishing; scope (no VAT rates, no reverse-charge logic); licence MIT.

**Step 3: Full verification**

```bash
XDEBUG_MODE=off vendor/bin/pest
XDEBUG_MODE=off vendor/bin/pest --testsuite=core
XDEBUG_MODE=off vendor/bin/phpstan analyse --debug
XDEBUG_MODE=off vendor/bin/pint --test
composer validate --strict
```

All must pass. **Step 4:** report files created, test counts per suite, the dragonbe divergences added in Task 7, every deviation from this plan with its reason. Do not commit.

#### Orchestrator verification (not for Codex)

1. Re-run Task 10 Step 3 myself.
2. Core without Laravel and without Guzzle, in a throw-away copy (`composer remove --dev illuminate/support illuminate/validation illuminate/translation orchestra/testbench`), then `vendor/bin/pest --testsuite=core`. Note: `CheckerTest` uses Guzzle's PSR-7 objects; Guzzle stays installed in that copy (it is a dev dependency) — the architecture test guarantees `src/` does not use it.
3. Read the whole diff against the design and the research document; verify a sample of country rules by hand against research §3; then show it to the owner. No commit without his OK.
