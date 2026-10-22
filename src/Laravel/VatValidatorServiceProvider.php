<?php

declare(strict_types=1);

namespace Egbavou\VatValidator\Laravel;

use Egbavou\VatValidator\VatNumberChecker;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

final class VatValidatorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/vat-validator.php', 'vat-validator');
        $this->app->singleton(VatNumberChecker::class, function (Application $app): VatNumberChecker {
            $config = $app->make(Repository::class);
            $timeout = $config->get('vat-validator.vies.timeout');
            $requester = $config->get('vat-validator.vies.requester');
            $factory = new HttpFactory;

            return new VatNumberChecker(
                new Client(['timeout' => is_numeric($timeout) && (float) $timeout > 0 ? (float) $timeout : 20.0]),
                $factory,
                $factory,
                requester: is_string($requester) && trim($requester) !== '' ? $requester : null,
            );
        });
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../../lang', 'vat-validator');
        $this->publishes([
            __DIR__.'/../../config/vat-validator.php' => $this->app->configPath('vat-validator.php'),
        ], 'vat-validator-config');
        $this->publishes([
            __DIR__.'/../../lang' => $this->app->langPath('vendor/vat-validator'),
        ], 'vat-validator-lang');
    }
}
