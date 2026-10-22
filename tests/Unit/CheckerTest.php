<?php

declare(strict_types=1);

use Egbavou\VatValidator\CheckStatus;
use Egbavou\VatValidator\Internal\ViesException;
use Egbavou\VatValidator\Tests\Support\FakeHttpClient;
use Egbavou\VatValidator\UnavailableReason;
use Egbavou\VatValidator\VatNumber;
use Egbavou\VatValidator\VatNumberChecker;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\PumpStream;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Assert;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\ResponseInterface;

/** @param list<ResponseInterface|Throwable> $responses
 * @return array{VatNumberChecker, FakeHttpClient}
 */
function checkerWith(array $responses = [], ?string $requester = null): array
{
    $client = new FakeHttpClient($responses);
    $factory = new HttpFactory;

    return [new VatNumberChecker($client, $factory, $factory, $requester), $client];
}

it('reads the real valid Belgian response', function (): void {
    [$checker, $client] = checkerWith([FakeHttpClient::fixture('post-valid-be.json')]);
    $result = $checker->check('BE0417497106');
    Assert::assertSame(CheckStatus::Found, $result->status);
    Assert::assertSame('NV Anheuser-Busch InBev', $result->name);
    Assert::assertSame("Grote Markt 1\n1000 Brussel", $result->address);
    Assert::assertNull($result->reference);
    Assert::assertNull($result->unavailableReason);
    Assert::assertNull($result->failure);
    Assert::assertNull($result->registryErrorCode);
    Assert::assertSame('2026-10-22T01:00:38.289Z', $result->checkedAt->format('Y-m-d\TH:i:s.v\Z'));
    Assert::assertSame('UTC', $result->checkedAt->getTimezone()->getName());
    Assert::assertSame('BE0417497106', $result->vatNumber?->value);
    Assert::assertCount(1, $client->requests);
});

it('decodes every real successful POST fixture', function (string $file): void {
    $response = FakeHttpClient::fixture($file);
    $data = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
    Assert::assertIsArray($data);
    Assert::assertIsString($data['countryCode']);
    Assert::assertIsString($data['vatNumber']);
    [$checker] = checkerWith([$response]);
    $result = $checker->check($data['countryCode'].$data['vatNumber']);
    Assert::assertSame(CheckStatus::Found, $result->status);
    Assert::assertSame($data['requestDate'], $result->checkedAt->format('Y-m-d\TH:i:s.v\Z'));
})->with(function (): array {
    $files = glob(__DIR__.'/../Fixtures/vies/post-valid-*.json') ?: [];

    return array_map(static fn (string $file): array => [basename($file)], $files);
});

it('maps absent identity markers and invalid registry responses', function (string $input, string $fixture, CheckStatus $status): void {
    [$checker] = checkerWith([FakeHttpClient::fixture($fixture)]);
    $result = $checker->check($input);
    Assert::assertSame($status, $result->status);
    Assert::assertNull($result->name);
    Assert::assertNull($result->address);
    Assert::assertNull($result->unavailableReason);
    Assert::assertNull($result->failure);
})->with([
    ['DE129274202', 'post-valid-de-without-name-address.json', CheckStatus::Found],
    ['FR44732829320', 'post-invalid-fr.json', CheckStatus::NotFound],
    ['ESA28015865', 'post-invalid-es-placeholder-name.json', CheckStatus::NotFound],
]);

it('reads a consultation reference', function (): void {
    [$checker] = checkerWith([FakeHttpClient::fixture('post-valid-with-requester-dk.json')], 'BE0417497106');
    Assert::assertSame('WAPIAAAAaFhMB-VU', $checker->check('DK24256790')->reference);
});

it('maps every member-state error without retrying', function (string $file): void {
    $response = FakeHttpClient::fixture($file);
    $data = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
    Assert::assertIsArray($data);
    Assert::assertIsArray($data['errorWrappers']);
    Assert::assertIsArray($data['errorWrappers'][0]);
    [$checker, $client] = checkerWith([$response]);
    $result = $checker->check('FR27552032534');
    Assert::assertSame(CheckStatus::Unavailable, $result->status);
    Assert::assertSame(UnavailableReason::RegistryError, $result->unavailableReason);
    Assert::assertNull($result->failure);
    Assert::assertSame($data['errorWrappers'][0]['error'], $result->registryErrorCode);
    Assert::assertCount(1, $client->requests);
})->with(function (): array {
    return array_map(static fn (string $file): array => [basename($file)], [
        ...(glob(__DIR__.'/../Fixtures/vies/post-error-*.json') ?: []),
        ...(glob(__DIR__.'/../Fixtures/vies/post-test-service-error-*.json') ?: []),
    ]);
});

it('gives registry errors precedence over a false valid field', function (): void {
    [$checker] = checkerWith([new Response(200, [], '{"valid":false,"errorWrappers":[{"error":"MS_MAX_CONCURRENT_REQ"}]}')]);
    $result = $checker->check('FR27552032534');
    Assert::assertSame(CheckStatus::Unavailable, $result->status);
    Assert::assertSame(UnavailableReason::RegistryError, $result->unavailableReason);
    Assert::assertNull($result->failure);
});

it('cannot mistake the GET false plus userError trap for NotFound', function (string $file): void {
    [$checker] = checkerWith([FakeHttpClient::fixture($file)]);
    $result = $checker->check('FR27552032534');
    Assert::assertSame(CheckStatus::Unavailable, $result->status);
    Assert::assertSame(UnavailableReason::Schema, $result->unavailableReason);
    Assert::assertInstanceOf(ViesException::class, $result->failure);
    Assert::assertSame('VIES response has no boolean "valid" field.', $result->failure->getMessage());
})->with(['get-error-ms-max-concurrent-req-fr.json', 'get-error-ms-max-concurrent-req-cz.json', 'get-error-invalid-input-xx.json']);

it('maps non-200 status before attempting JSON or registry errors', function (string $file, int $status): void {
    [$checker] = checkerWith([FakeHttpClient::fixture($file, $status)]);
    $result = $checker->check('BE0417497106');
    Assert::assertSame(CheckStatus::Unavailable, $result->status);
    Assert::assertSame(UnavailableReason::HttpStatus, $result->unavailableReason);
    Assert::assertInstanceOf(ViesException::class, $result->failure);
    Assert::assertSame("VIES returned HTTP {$status}.", $result->failure->getMessage());
    Assert::assertNull($result->registryErrorCode);
})->with(function (): array {
    $files = [...(glob(__DIR__.'/../Fixtures/vies/post-http*.json') ?: []), ...(glob(__DIR__.'/../Fixtures/vies/http*.html') ?: [])];
    $cases = [];
    foreach ($files as $file) {
        if (preg_match('/http([0-9]{3})/', basename($file), $matches) !== 1) {
            throw new UnexpectedValueException('HTTP fixture filename must contain its status.');
        }
        $cases[] = [basename($file), (int) $matches[1]];
    }

    return $cases;
});

it('maps an HTML body served in HTTP 200 to InvalidJson', function (): void {
    [$checker] = checkerWith([FakeHttpClient::fixture('http404-not-found.html')]);
    $result = $checker->check('BE0417497106');
    Assert::assertSame(UnavailableReason::InvalidJson, $result->unavailableReason);
    Assert::assertInstanceOf(JsonException::class, $result->failure);
    Assert::assertSame('Syntax error', $result->failure->getMessage());
});

it('rejects unexpected response schemas', function (string $body, string $message): void {
    [$checker] = checkerWith([new Response(200, [], $body)]);
    $result = $checker->check('BE0417497106');
    Assert::assertSame(CheckStatus::Unavailable, $result->status);
    Assert::assertSame(UnavailableReason::Schema, $result->unavailableReason);
    Assert::assertInstanceOf(ViesException::class, $result->failure);
    Assert::assertSame($message, $result->failure->getMessage());
})->with([
    ['{"foo":1}', 'VIES response has no boolean "valid" field.'],
    ['"text"', 'VIES response is not a JSON object.'],
    ['null', 'VIES response is not a JSON object.'],
    ['{"valid":"true"}', 'VIES response has no boolean "valid" field.'],
    ['{"valid":true}', 'VIES response has no valid "requestDate" timestamp.'],
    ['{"valid":true,"requestDate":"bad","vatNumber":"private VAT","name":"private name","address":"private address"}', 'VIES response has no valid "requestDate" timestamp.'],
]);

it('turns PSR-18 transport exceptions into a result', function (): void {
    $failure = new class('timeout') extends RuntimeException implements ClientExceptionInterface {};
    [$checker, $client] = checkerWith([$failure]);
    $before = new DateTimeImmutable('now');
    $result = $checker->check('BE0417497106');
    Assert::assertSame(CheckStatus::Unavailable, $result->status);
    Assert::assertSame(UnavailableReason::Transport, $result->unavailableReason);
    Assert::assertSame($failure, $result->failure);
    Assert::assertInstanceOf(ClientExceptionInterface::class, $result->failure);
    Assert::assertSame('timeout', $result->failure->getMessage());
    Assert::assertGreaterThanOrEqual($before, $result->checkedAt);
    Assert::assertLessThanOrEqual(new DateTimeImmutable('now'), $result->checkedAt);
    Assert::assertCount(1, $client->requests);
});

it('never sends a request for a country without a source', function (string $input): void {
    [$checker, $client] = checkerWith();
    $result = $checker->check($input);
    Assert::assertSame(CheckStatus::Unavailable, $result->status);
    Assert::assertSame(UnavailableReason::NoSource, $result->unavailableReason);
    Assert::assertNull($result->failure);
    Assert::assertSame($input, $result->vatNumber?->value);
    Assert::assertCount(0, $client->requests);
})->with(['GB220430231', 'CHE116281710', 'NO974760673']);

it('never sends a request for an invalid format', function (): void {
    [$checker, $client] = checkerWith();
    $result = $checker->check('FR123');
    Assert::assertSame(CheckStatus::InvalidFormat, $result->status);
    Assert::assertNull($result->vatNumber);
    Assert::assertNull($result->unavailableReason);
    Assert::assertNull($result->failure);
    Assert::assertCount(0, $client->requests);
});

it('accepts a value object or a national number with a country', function (bool $object): void {
    [$checker, $client] = checkerWith([FakeHttpClient::fixture('post-valid-fr.json')]);
    $input = $object ? VatNumber::from('FR27552032534') : '27552032534';
    Assert::assertSame(CheckStatus::Found, $checker->check($input, 'FR')->status);
    Assert::assertSame(['countryCode' => 'FR', 'vatNumber' => '27552032534'], json_decode((string) $client->requests[0]->getBody(), true));
})->with([true, false]);

it('sends canonical prefixes and only the national number', function (string $input, string $prefix, string $national): void {
    [$checker, $client] = checkerWith([FakeHttpClient::fixture('post-valid-be.json')]);
    $checker->check($input);
    $request = $client->requests[0];
    Assert::assertSame('POST', $request->getMethod());
    Assert::assertSame(VatNumberChecker::DEFAULT_ENDPOINT, (string) $request->getUri());
    Assert::assertSame('application/json', $request->getHeaderLine('Content-Type'));
    Assert::assertSame('application/json', $request->getHeaderLine('Accept'));
    Assert::assertSame(['countryCode' => $prefix, 'vatNumber' => $national], json_decode((string) $request->getBody(), true));
})->with([['GR094014201', 'EL', '094014201'], ['XI220430231', 'XI', '220430231']]);

it('sends a valid VIES requester and ignores other requester values', function (?string $requester, bool $included): void {
    [$checker, $client] = checkerWith([FakeHttpClient::fixture('post-valid-be.json')], $requester);
    $checker->check('BE0417497106');
    $expected = ['countryCode' => 'BE', 'vatNumber' => '0417497106'];
    if ($included) {
        $expected += ['requesterMemberStateCode' => 'BE', 'requesterNumber' => '0417497106'];
    }
    Assert::assertSame($expected, json_decode((string) $client->requests[0]->getBody(), true));
})->with([['BE0417497106', true], ['FR123', false], ['GB220430231', false], ['CHE116281710', false], [null, false]]);

it('normalizes lines without changing internal spaces', function (string $name, ?string $expected): void {
    $body = json_encode(['valid' => true, 'requestDate' => '2026-10-22T01:00:38.289Z', 'name' => $name, 'address' => $name], JSON_THROW_ON_ERROR);
    [$checker] = checkerWith([new Response(200, [], $body)]);
    $result = $checker->check('BE0417497106');
    Assert::assertSame($expected, $result->name);
    Assert::assertSame($expected, $result->address);
})->with([["  \nFOO  BAR \n\nLINE 2\n", "FOO  BAR\nLINE 2"], ['---', null], ['', null], ['N/A', null]]);

it('rejects malformed registry timestamps without throwing', function (string $date): void {
    $body = json_encode(['valid' => true, 'requestDate' => $date], JSON_THROW_ON_ERROR);
    [$checker] = checkerWith([new Response(200, [], $body)]);
    $result = $checker->check('BE0417497106');
    Assert::assertSame(CheckStatus::Unavailable, $result->status);
    Assert::assertSame(UnavailableReason::Schema, $result->unavailableReason);
    Assert::assertInstanceOf(ViesException::class, $result->failure);
    Assert::assertSame('VIES response has no valid "requestDate" timestamp.', $result->failure->getMessage());
})->with(["2026-10-22T01:00:38.289Z\0", '2026-02-31T01:00:38.289Z', '2026-10-22T25:00:38.289Z']);

it('preserves non-PSR throwables from request handling', function (): void {
    $failure = new Error('request failed');
    [$checker] = checkerWith([$failure]);
    $result = $checker->check('BE0417497106');
    Assert::assertSame(CheckStatus::Unavailable, $result->status);
    Assert::assertSame(UnavailableReason::Transport, $result->unavailableReason);
    Assert::assertSame($failure, $result->failure);
    Assert::assertSame(Error::class, $result->failure::class);
    Assert::assertSame('request failed', $result->failure->getMessage());
});

it('preserves response body read failures as transport failures', function (Throwable $failure): void {
    $body = new PumpStream(static function () use ($failure): never {
        throw $failure;
    });
    [$checker] = checkerWith([new Response(200, [], $body)]);
    $result = $checker->check('BE0417497106');
    Assert::assertSame(CheckStatus::Unavailable, $result->status);
    Assert::assertSame(UnavailableReason::Transport, $result->unavailableReason);
    Assert::assertSame($failure, $result->failure);
})->with([
    [new RuntimeException('Connection reset while reading response')],
    [new JsonException('Exception thrown by the stream, before decoding')],
    [new Error('Stream failure')],
]);

it('retains the HTTP clients original exception and uncontrolled diagnostic message', function (): void {
    $diagnostic = 'VAT BE0417497106, name Alice Example, address 123 Private Lane';
    $request = new Request('POST', VatNumberChecker::DEFAULT_ENDPOINT);
    $failure = new RequestException($diagnostic, $request, new Response(503, [], $diagnostic));
    [$checker] = checkerWith([$failure]);
    $result = $checker->check('BE0417497106');
    Assert::assertSame(CheckStatus::Unavailable, $result->status);
    Assert::assertSame(UnavailableReason::Transport, $result->unavailableReason);
    Assert::assertSame($failure, $result->failure);
    Assert::assertSame($diagnostic, $result->failure->getMessage());
});

it('rejects malformed string fields before either successful status with safe diagnostics', function (string $field, mixed $value, bool $valid): void {
    $data = json_decode((string) FakeHttpClient::fixture('post-valid-be.json')->getBody(), true, 512, JSON_THROW_ON_ERROR);
    Assert::assertIsArray($data);
    $data[$field] = $value;
    $data['valid'] = $valid;
    $body = json_encode($data, JSON_THROW_ON_ERROR);
    [$checker] = checkerWith([new Response(200, [], $body)]);
    $result = $checker->check('BE0417497106');
    Assert::assertSame(CheckStatus::Unavailable, $result->status);
    Assert::assertSame(UnavailableReason::Schema, $result->unavailableReason);
    Assert::assertInstanceOf(ViesException::class, $result->failure);
    Assert::assertSame('VIES response has no string "'.$field.'" field.', $result->failure->getMessage());
})->with(function (): array {
    $cases = [];
    foreach (['name', 'address', 'requestIdentifier', 'countryCode', 'vatNumber'] as $field) {
        foreach (['object' => (object) ['private' => 'VAT BE0417497106, name Alice Example, address 123 Private Lane'], 'array' => [], 'integer' => 42, 'boolean' => false, 'null' => null] as $type => $value) {
            foreach ([true, false] as $valid) {
                $cases[$field.' '.$type.' '.($valid ? 'Found' : 'NotFound')] = [$field, $value, $valid];
            }
        }
    }

    return $cases;
});

it('hides a returned reference without a valid VIES requester', function (?string $requester): void {
    [$checker] = checkerWith([FakeHttpClient::fixture('post-valid-with-requester-dk.json')], $requester);
    $result = $checker->check('DK24256790');
    Assert::assertSame(CheckStatus::Found, $result->status);
    Assert::assertNull($result->reference);
})->with([null, '', 'FR123', 'GB220430231', 'CHE116281710', 'NO974760673']);
