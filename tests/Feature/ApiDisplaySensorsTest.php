<?php

use App\Enums\DeviceSensorKind;
use App\Enums\DeviceSensorSource;
use App\Models\Device;
use App\Models\DeviceSensor;

test('display endpoint ingests HTTP_SENSORS header into device_sensors table', function (): void {
    $device = Device::factory()->create([
        'api_key' => 'test-key',
    ]);

    $headerValue = 'make=Sensirion;model=SCD41;kind=carbon_dioxide;value=405;unit=ppm;created_at=1770850256,make=Sensirion;model=SCD41;kind=temperature;value=20.10;unit=celcius;created_at=1770850256';

    $response = $this->getJson('/api/display', [
        'access-token' => 'test-key',
        'HTTP_SENSORS' => $headerValue,
    ]);

    $response->assertOk();

    $this->assertDatabaseCount('device_sensors', 2);

    $co2 = DeviceSensor::where('device_id', $device->id)
        ->where('kind', DeviceSensorKind::CARBON_DIOXIDE)
        ->first();

    expect($co2)->not->toBeNull()
        ->and($co2->value)->toBe(405.0)
        ->and($co2->unit)->toBe('ppm')
        ->and($co2->source)->toBe(DeviceSensorSource::DEVICE);
});

test('display endpoint skips malformed sensor records but still succeeds', function (): void {
    $device = Device::factory()->create([
        'api_key' => 'test-key-2',
    ]);

    $headerValue = 'make=Bad;model=MissingKind;value=10;unit=ppm;created_at=1770850256,make=Sensirion;model=SCD41;kind=temperature;value=20.10;unit=celcius;created_at=1770850256';

    $response = $this->getJson('/api/display', [
        'access-token' => 'test-key-2',
        'HTTP_SENSORS' => $headerValue,
    ]);

    $response->assertOk();

    $this->assertDatabaseCount('device_sensors', 1);
});
