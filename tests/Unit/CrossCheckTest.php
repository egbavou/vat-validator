<?php

declare(strict_types=1);

use DragonBe\Vies\Validator\ValidatorInterface;
use Egbavou\VatValidator\Internal\Countries\CountryRules;
use Egbavou\VatValidator\Tests\Support\VatCases;
use Egbavou\VatValidator\VatNumber;
use PHPUnit\Framework\Assert;

const DOCUMENTED_DIVERGENCES = [
    'NLABCDEFGHIJ27' => 'BMF pp.14–17 and research §3 NL permit the full MOD97 alphabet, without a B at position ten; dragonbe requires B.',
    'RO01590082' => 'BMF Romanian format: no leading zero since 5/12/2007; design §9 gives BMF precedence over dragonbe.',
    'RO00' => 'BMF Romanian format: no leading zero since 5/12/2007; design §9 gives BMF precedence over dragonbe.',
    'RO0000000000' => 'BMF Romanian format: no leading zero since 5/12/2007; design §9 gives BMF precedence over dragonbe.',
    'LV33123456789' => 'Design §9 Latvia decision: personal codes 32–39 have no checksum (law art. 6(2), in force 28/06/2021).',
    'LV38000000000' => 'Design §9 Latvia decision: personal codes 32–39 have no checksum (law art. 6(2), in force 28/06/2021).',
    'LV39999999999' => 'Design §9 Latvia decision: personal codes 32–39 have no checksum (law art. 6(2), in force 28/06/2021).',
    'CZ699004743' => 'Design §9 Czechia VČP decision (2026-10-22): nine digits starting with 6 are format only; construction unpublished.',
    'BG7501011233' => 'Design §9 Bulgaria decision (2026-10-22): ten-digit natural-person numbers are format only.',
    'BG1234567890' => 'Design §9 Bulgaria decision (2026-10-22): ten-digit numbers include unpublished NAP service formats.',
    'CY60005142Q' => 'Design §9 Cyprus 6-series decision: compute the letter, verified on 24 VIES-valid numbers on 2026-10-22; dragonbe omits it.',
    'BE403170701' => 'VIES FAQ note 3 and design §9 pad old nine-digit Belgian numbers.',
    'BG1234560034' => 'Design §9 Bulgaria decision (2026-10-22): ten-digit natural-person numbers are format only.',
    'BG9999999996' => 'Design §9 Bulgaria decision (2026-10-22): ten-digit numbers include unpublished NAP service formats.',
    'CY12000000F' => 'Task 5 permissiveness: the exclusion of 12 has no source in research §3 CY.',
    'ESN01234566' => 'Task 5 and research §3 ES accept either CIF control form.',
    'ESJ0123456F' => 'Task 5 and research §3 ES accept either CIF control form.',
    'LV01018012347' => 'Task 6 and research §3 LV accept structurally valid old personal codes.',
    'LV32123456785' => 'Task 6 and research §3 LV accept new 32 personal codes without an unconfirmed key.',
    'MT10001700' => 'Task 6 and research §3 MT use the more permissive mod-37 congruence.',
    'XI010000035' => 'BMF pp.8–9 exclude the 0100000–0999999 range for MOD97.',
    'XI010000090' => 'BMF pp.8–9 require P > 1000000 for MOD9755.',
    'XI950000087' => 'BMF pp.8–9 exclude the 9490001–9700000 range for MOD97.',
    'BE2123456791' => 'BMF p.2 restricts the first digit to 0 or 1.',
    'CY10137520F' => 'Research §3 CY requires the transcode checksum; dragonbe omits it.',
    'DE012345679' => 'BMF p.3 excludes an initial zero.',
    'DK00000000' => 'BMF p.4 excludes an initial zero.',
    'EE200070005' => 'BMF p.22 requires initial 10.',
    'FR29123456788' => 'Research §3 FR and INSEE require SIREN Luhn except Monaco.',
    'IE6388047VX' => 'BMF pp.10–11 restrict C9 to A–I.',
    'IT12345672005' => 'BMF p.12 excludes office 200.',
    'NL100000060B01' => 'BMF pp.14–17 reject remainder 10 in mod 11.',
    'PT012345679' => 'BMF p.18 excludes an initial zero.',
    'XI220430231000' => 'BMF pp.8–9,32–33 exclude branch 000.',
];

it('cross-checks every supported vector and verifies documented divergences', function (string $input): void {
    $clean = preg_replace('/[\p{Z}\x{0009}-\x{000D}\x{0085}.\-]+/u', '', strtoupper($input));
    Assert::assertIsString($clean);
    $detected = CountryRules::detectPrefix($clean);
    Assert::assertNotNull($detected);
    $prefix = $detected['prefix'];
    $class = 'DragonBe\\Vies\\Validator\\Validator'.$prefix;
    Assert::assertTrue(class_exists($class));
    $validator = new $class;
    Assert::assertInstanceOf(ValidatorInterface::class, $validator);
    $national = substr($clean, $detected['length']);
    $ours = VatNumber::isValid($input);
    // dragonbe's French alphanumeric arithmetic emits PHP float-to-int deprecations.
    set_error_handler(static fn (int $severity): bool => $severity === E_DEPRECATED);
    try {
        $theirs = $validator->validate($national);
    } finally {
        restore_error_handler();
    }
    if (isset(DOCUMENTED_DIVERGENCES[$input])) {
        Assert::assertNotSame($ours, $theirs, DOCUMENTED_DIVERGENCES[$input]);
    } else {
        Assert::assertSame($ours, $theirs, $input.' needs a research-backed explanation.');
    }
})->with(function (): array {
    $vectors = [];
    foreach ([...array_values(VatCases::validNumbers()), ...array_values(VatCases::invalidNumbers())] as $row) {
        if (! str_starts_with($row[0], 'CHE') && ! str_starts_with($row[0], 'NO') && ! str_starts_with($row[0], 'GB')) {
            $vectors[$row[0]] = [$row[0]];
        }
    }

    return $vectors;
});

it('exercises every documented divergence', function (): void {
    $inputs = array_column([...array_values(VatCases::validNumbers()), ...array_values(VatCases::invalidNumbers())], 0);
    foreach (array_keys(DOCUMENTED_DIVERGENCES) as $input) {
        Assert::assertContains($input, $inputs);
    }
});
