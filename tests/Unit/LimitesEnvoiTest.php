<?php

use App\Support\LimitesEnvoi;

test('php.ini shorthand sizes are converted to bytes', function (string $valeur, int $octets) {
    expect(LimitesEnvoi::enOctets($valeur))->toBe($octets);
})->with([
    ['8M', 8 * 1024 * 1024],
    ['512K', 512 * 1024],
    ['1G', 1024 * 1024 * 1024],
    ['2048', 2048],
    ['0', 0],
    ['', 0],
]);

test('sizes are displayed in Mo', function () {
    expect(LimitesEnvoi::enMo(8 * 1024 * 1024))->toBe('8 Mo')
        ->and(LimitesEnvoi::enMo((int) (5.5 * 1024 * 1024)))->toBe('5,5 Mo');
});
