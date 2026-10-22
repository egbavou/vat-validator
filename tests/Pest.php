<?php

declare(strict_types=1);

use Egbavou\VatValidator\Tests\Laravel\TestCase;

uses()->group('laravel')->in('Laravel');

if (class_exists(Orchestra\Testbench\TestCase::class)) {
    uses(TestCase::class)->in('Laravel');
}
