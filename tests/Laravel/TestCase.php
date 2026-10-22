<?php

declare(strict_types=1);

namespace Egbavou\VatValidator\Tests\Laravel;

use Egbavou\VatValidator\Laravel\VatValidatorServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [VatValidatorServiceProvider::class];
    }
}
