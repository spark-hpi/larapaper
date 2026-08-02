<?php

declare(strict_types=1);

use App\Services\DeviceScreenFilename;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('public');
    Storage::disk('public')->makeDirectory('images/generated');
});

it('returns null for empty path', function (): void {
    $screenFilename = app(DeviceScreenFilename::class);

    expect($screenFilename->make(null, 'plugin:1'))->toBeNull()
        ->and($screenFilename->make('', 'plugin:1'))->toBeNull();
});

it('outputs touchbar format for each prefix constant', function (string $prefix): void {
    $path = 'images/generated/test-image.png';
    Storage::disk('public')->put($path, 'content');

    $screenFilename = app(DeviceScreenFilename::class);
    $filename = $screenFilename->make($path, 'plugin:1', $prefix);

    expect($filename)->toMatch('/^'.preg_quote($prefix, '/').'[a-f0-9]{6}-\d{10}$/');
})->with([
    'plugin' => [DeviceScreenFilename::PREFIX_PLUGIN],
    'mashup' => [DeviceScreenFilename::PREFIX_MASHUP],
    'system' => [DeviceScreenFilename::PREFIX_SYSTEM],
]);

it('returns the same filename for the same path and identity', function (): void {
    $path = 'images/generated/stable.png';
    Storage::disk('public')->put($path, 'content');

    $screenFilename = app(DeviceScreenFilename::class);

    $first = $screenFilename->make($path, 'plugin:42', DeviceScreenFilename::PREFIX_PLUGIN);
    $second = $screenFilename->make($path, 'plugin:42', DeviceScreenFilename::PREFIX_PLUGIN);

    expect($first)->toBe($second);
});

it('changes only the timestamp suffix when file mtime changes', function (): void {
    $path = 'images/generated/changing.png';
    Storage::disk('public')->put($path, 'v1');
    touch(Storage::disk('public')->path($path), 1_700_000_000);

    $screenFilename = app(DeviceScreenFilename::class);
    $first = $screenFilename->make($path, 'plugin:7', DeviceScreenFilename::PREFIX_PLUGIN);

    touch(Storage::disk('public')->path($path), 1_700_000_100);

    $second = $screenFilename->make($path, 'plugin:7', DeviceScreenFilename::PREFIX_PLUGIN);

    expect(mb_substr($first, 0, 13))->toBe(mb_substr($second, 0, 13))
        ->and($first)->not->toBe($second);
});

it('falls back to current timestamp when file is missing', function (): void {
    $screenFilename = app(DeviceScreenFilename::class);

    $filename = $screenFilename->make('images/generated/missing.png', 'plugin:1', DeviceScreenFilename::PREFIX_PLUGIN);

    expect($filename)->toMatch('/^plugin-[a-f0-9]{6}-\d{10}$/')
        ->and((int) mb_substr((string) $filename, -10))->toBeGreaterThanOrEqual(1_000_000_000);
});

it('produces a stable identity hash for a given seed', function (): void {
    $path = 'images/generated/test.png';
    Storage::disk('public')->put($path, 'content');

    $screenFilename = app(DeviceScreenFilename::class);

    $first = $screenFilename->make($path, 'plugin:42', DeviceScreenFilename::PREFIX_PLUGIN);
    $second = $screenFilename->make($path, 'plugin:42', DeviceScreenFilename::PREFIX_PLUGIN);

    $hash = mb_substr(md5('plugin:42'), 0, 6);

    expect($first)->toBe('plugin-'.$hash.'-'.mb_substr((string) $first, -10))
        ->and($second)->toBe($first);
});
