<?php

declare(strict_types=1);

use Egbavou\VatValidator\CheckStatus;
use Egbavou\VatValidator\Laravel\VatValidatorServiceProvider;
use Egbavou\VatValidator\Tests\Support\FakeHttpClient;
use Egbavou\VatValidator\VatNumberChecker;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Translation\Translator;
use Illuminate\Support\ServiceProvider;
use PHPUnit\Framework\Assert;
use Psr\Http\Message\RequestInterface;

it('merges defaults registers a singleton and loads translations and publish tags', function (): void {
    $config = app(Repository::class);
    Assert::assertSame(20, $config->get('vat-validator.vies.timeout'));
    Assert::assertNull($config->get('vat-validator.vies.requester'));
    Assert::assertSame(app(VatNumberChecker::class), app(VatNumberChecker::class));
    $translator = app(Translator::class);
    foreach (['en', 'fr'] as $locale) {
        Assert::assertNotSame('vat-validator::validation.invalid_structure', $translator->get('vat-validator::validation.invalid_structure', [], $locale));
    }
    $application = app(Application::class);
    foreach (['config' => ['config/vat-validator.php', $application->configPath('vat-validator.php')], 'lang' => ['lang', $application->langPath('vendor/vat-validator')]] as $tag => [$source, $target]) {
        $paths = ServiceProvider::pathsToPublish(VatValidatorServiceProvider::class, 'vat-validator-'.$tag);
        Assert::assertCount(1, $paths);
        Assert::assertSame($target, array_values($paths)[0]);
        Assert::assertSame(realpath(__DIR__.'/../../'.$source), realpath(array_keys($paths)[0]));
    }
});

it('normalizes the configured timeout', function (mixed $timeout, float $expected): void {
    app(Repository::class)->set('vat-validator.vies.timeout', $timeout);
    $checker = app(VatNumberChecker::class);
    $client = (new ReflectionProperty($checker, 'httpClient'))->getValue($checker);
    Assert::assertInstanceOf(Client::class, $client);
    Assert::assertSame($expected, $client->getConfig('timeout'));
})->with([[3, 3.0], [2.5, 2.5], ['2.5', 2.5], [0, 20.0], [-1, 20.0], ['0', 20.0], ['garbage', 20.0], [null, 20.0], [false, 20.0]]);

it('sends the configured requester through a mocked Guzzle handler', function (mixed $requester, bool $included): void {
    app(Repository::class)->set('vat-validator.vies.requester', $requester);
    $checker = app(VatNumberChecker::class);
    $client = (new ReflectionProperty($checker, 'httpClient'))->getValue($checker);
    Assert::assertInstanceOf(Client::class, $client);
    $stack = $client->getConfig('handler');
    Assert::assertInstanceOf(HandlerStack::class, $stack);
    $stack->setHandler(new MockHandler([FakeHttpClient::fixture('post-valid-be.json')]));
    $history = [];
    $stack->push(Middleware::history($history));
    Assert::assertSame(CheckStatus::Found, $checker->check('BE0417497106')->status);
    Assert::assertIsArray($history);
    Assert::assertCount(1, $history);
    Assert::assertIsArray($history[0]);
    Assert::assertInstanceOf(RequestInterface::class, $history[0]['request']);
    $expected = ['countryCode' => 'BE', 'vatNumber' => '0417497106'];
    if ($included) {
        $expected += ['requesterMemberStateCode' => 'BE', 'requesterNumber' => '0417497106'];
    }
    Assert::assertSame($expected, json_decode((string) $history[0]['request']->getBody(), true));
})->with([['BE0417497106', true], ['', false], [null, false], [123, false], ['FR123', false], ['CHE116281710', false]]);
