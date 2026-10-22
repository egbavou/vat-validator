<?php

declare(strict_types=1);

namespace Egbavou\VatValidator;

use DateTimeImmutable;
use DateTimeZone;
use Egbavou\VatValidator\Internal\Countries\CountryRules;
use Egbavou\VatValidator\Internal\ViesException;
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
            $status = $response->getStatusCode();
        } catch (Throwable $exception) {
            return self::unavailable($vatNumber, UnavailableReason::Transport, failure: $exception);
        }

        if ($status !== 200) {
            return self::unavailable($vatNumber, UnavailableReason::HttpStatus, failure: new ViesException('VIES returned HTTP '.$status.'.'));
        }

        try {
            $body = (string) $response->getBody();
        } catch (Throwable $exception) {
            return self::unavailable($vatNumber, UnavailableReason::Transport, failure: $exception);
        }

        try {
            $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            return self::unavailable($vatNumber, UnavailableReason::InvalidJson, failure: $exception);
        }

        if (! is_array($data)) {
            return self::unavailable($vatNumber, UnavailableReason::Schema, failure: new ViesException('VIES response is not a JSON object.'));
        }

        // VIES reports member-state errors in a 200 response: read errorWrappers before 'valid'.
        if (array_key_exists('errorWrappers', $data)) {
            $code = is_array($data['errorWrappers']) && is_array($data['errorWrappers'][0] ?? null)
                ? ($data['errorWrappers'][0]['error'] ?? null)
                : null;

            return self::unavailable($vatNumber, UnavailableReason::RegistryError, is_string($code) ? $code : null);
        }

        if (! is_bool($data['valid'] ?? null)) {
            return self::unavailable($vatNumber, UnavailableReason::Schema, failure: new ViesException('VIES response has no boolean "valid" field.'));
        }

        foreach (['name', 'address', 'requestIdentifier', 'countryCode', 'vatNumber'] as $field) {
            if (array_key_exists($field, $data) && ! is_string($data[$field])) {
                return self::unavailable($vatNumber, UnavailableReason::Schema, failure: new ViesException('VIES response has no string "'.$field.'" field.'));
            }
        }

        $requestDate = $data['requestDate'] ?? null;
        $checkedAt = is_string($requestDate) && preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}\.[0-9]{3}Z\z/', $requestDate) === 1
            ? DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s.v\Z', $requestDate, new DateTimeZone('UTC'))
            : false;

        // Reject dates PHP silently normalizes (for example February 31), as well as malformed timestamps.
        if ($checkedAt === false || $checkedAt->format('Y-m-d\TH:i:s.v\Z') !== $requestDate) {
            return self::unavailable($vatNumber, UnavailableReason::Schema, failure: new ViesException('VIES response has no valid "requestDate" timestamp.'));
        }

        $reference = $data['requestIdentifier'] ?? null;

        return new VatNumberCheckResult(
            status: $data['valid'] ? CheckStatus::Found : CheckStatus::NotFound,
            checkedAt: $checkedAt,
            vatNumber: $vatNumber,
            name: self::text($data['name'] ?? null),
            address: self::text($data['address'] ?? null),
            reference: $this->requester !== null && is_string($reference) && $reference !== '' ? $reference : null,
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

    private static function unavailable(VatNumber $vatNumber, UnavailableReason $reason, ?string $registryErrorCode = null, ?Throwable $failure = null): VatNumberCheckResult
    {
        return new VatNumberCheckResult(
            status: CheckStatus::Unavailable,
            checkedAt: self::now(),
            vatNumber: $vatNumber,
            unavailableReason: $reason,
            registryErrorCode: $registryErrorCode,
            failure: $failure,
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
