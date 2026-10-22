<?php

declare(strict_types=1);

namespace Egbavou\VatValidator\Tests\Support;

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
