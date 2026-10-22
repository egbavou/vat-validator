<?php

declare(strict_types=1);

arch('keeps the core independent of Laravel and Guzzle', function (): void {
    expect('Egbavou\\VatValidator')
        ->not->toUse(['Illuminate', 'GuzzleHttp'])
        ->ignoring('Egbavou\\VatValidator\\Laravel');
});
