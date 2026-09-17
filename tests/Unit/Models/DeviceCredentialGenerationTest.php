<?php

declare(strict_types=1);

use App\Models\Device;
use App\Models\User;
use Illuminate\Support\Str;

test('generated device credentials skip values already in use', function (string $method, string $column, int $length): void {
    Device::factory()->for(User::factory())->create([
        $column => str_repeat('A', $length),
    ]);

    $values = [
        str_repeat('A', $length),
        str_repeat('B', $length),
    ];
    Str::createRandomStringsUsing(function () use (&$values): string {
        return array_shift($values) ?? 'fallback';
    });

    try {
        expect(Device::{$method}())->toBe(str_repeat('B', $length));
    } finally {
        Str::createRandomStringsNormally();
    }
})->with([
    'friendly id' => ['generateFriendlyId', 'friendly_id', 6],
    'api key' => ['generateApiKey', 'api_key', 22],
]);
