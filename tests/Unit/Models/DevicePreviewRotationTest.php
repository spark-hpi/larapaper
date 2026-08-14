<?php

declare(strict_types=1);

use App\Models\Device;
use App\Models\DeviceModel;

test('preview rotation converts effective rotation to positive css angle', function (int|string|null $rotate, int $expected): void {
    $device = Device::factory()->make(['rotate' => $rotate]);

    expect($device->preview_rotation)->toBe($expected);
})->with([
    'no rotation' => [0, 0],
    '90 degrees' => [90, 270],
    '180 degrees' => [180, 180],
    '270 degrees' => [270, 90],
    'null rotation' => [null, 0],
]);

test('preview rotation uses device model rotation when present', function (): void {
    $deviceModel = DeviceModel::factory()->make(['rotation' => 0]);
    $device = Device::factory()->make([
        'rotate' => 270,
        'device_model_id' => $deviceModel->id,
    ]);
    $device->setRelation('deviceModel', $deviceModel);

    expect($device->deviceModel?->rotation ?? ($device->rotate ?? 0))->toBe(0)
        ->and($device->preview_rotation)->toBe(0);
});

test('preview rotation uses device model rotation over device rotate', function (): void {
    $deviceModel = DeviceModel::factory()->make(['rotation' => 90]);
    $device = Device::factory()->make([
        'rotate' => 0,
        'device_model_id' => $deviceModel->id,
    ]);
    $device->setRelation('deviceModel', $deviceModel);

    expect($device->deviceModel?->rotation ?? ($device->rotate ?? 0))->toBe(90)
        ->and($device->preview_rotation)->toBe(270);
});
