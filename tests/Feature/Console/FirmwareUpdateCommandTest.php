<?php

declare(strict_types=1);

use App\Enums\FirmwareModel;
use App\Models\Device;
use App\Models\Firmware;
use App\Models\User;

test('firmware update command has correct signature', function (): void {
    $this->artisan('trmnl:firmware:update --help')
        ->assertExitCode(0);
});

test('firmware update command can be called', function (): void {
    $user = User::factory()->create();
    $device = Device::factory()->create(['user_id' => $user->id]);
    $firmware = Firmware::factory()->trmnl()->create(['version_tag' => '1.0.0']);

    $this->artisan('trmnl:firmware:update')
        ->expectsQuestion('Check for new firmware?', 'no')
        ->expectsQuestion('Which device model? (OTA updates are for TRMNL devices only)', FirmwareModel::Trmnl->value)
        ->expectsQuestion('Update to which version?', $firmware->id)
        ->expectsQuestion('Which devices should be updated?', ["_$device->id"])
        ->assertExitCode(0);

    $device->refresh();
    expect($device->update_firmware_id)->toBe($firmware->id);
});

test('firmware update command updates all devices when all is selected', function (): void {
    $user = User::factory()->create();
    $device1 = Device::factory()->create(['user_id' => $user->id]);
    $device2 = Device::factory()->create(['user_id' => $user->id]);
    $firmware = Firmware::factory()->trmnl()->create(['version_tag' => '1.0.0']);

    $this->artisan('trmnl:firmware:update')
        ->expectsQuestion('Check for new firmware?', 'no')
        ->expectsQuestion('Which device model? (OTA updates are for TRMNL devices only)', FirmwareModel::Trmnl->value)
        ->expectsQuestion('Update to which version?', $firmware->id)
        ->expectsQuestion('Which devices should be updated?', ['all'])
        ->assertExitCode(0);

    $device1->refresh();
    $device2->refresh();
    expect($device1->update_firmware_id)->toBe($firmware->id)
        ->and($device2->update_firmware_id)->toBe($firmware->id);
});

test('firmware update command aborts when no devices selected', function (): void {
    $firmware = Firmware::factory()->trmnl()->create(['version_tag' => '1.0.0']);

    $this->artisan('trmnl:firmware:update')
        ->expectsQuestion('Check for new firmware?', 'no')
        ->expectsQuestion('Which device model? (OTA updates are for TRMNL devices only)', FirmwareModel::Trmnl->value)
        ->expectsQuestion('Update to which version?', $firmware->id)
        ->expectsQuestion('Which devices should be updated?', [])
        ->expectsOutput('No devices selected. Aborting.')
        ->assertExitCode(0);
});

test('firmware update command calls firmware check when check is selected', function (): void {
    $user = User::factory()->create();
    $device = Device::factory()->create(['user_id' => $user->id]);
    $firmware = Firmware::factory()->trmnl()->create(['version_tag' => '1.0.0']);

    $this->artisan('trmnl:firmware:update')
        ->expectsQuestion('Check for new firmware?', 'check')
        ->expectsQuestion('Which device model? (OTA updates are for TRMNL devices only)', FirmwareModel::Trmnl->value)
        ->expectsQuestion('Update to which version?', $firmware->id)
        ->expectsQuestion('Which devices should be updated?', ["_$device->id"])
        ->assertExitCode(0);

    $device->refresh();
    expect($device->update_firmware_id)->toBe($firmware->id);
});

test('firmware update command calls firmware check with download when download is selected', function (): void {
    $user = User::factory()->create();
    $device = Device::factory()->create(['user_id' => $user->id]);
    $firmware = Firmware::factory()->trmnl()->create(['version_tag' => '1.0.0']);

    $this->artisan('trmnl:firmware:update')
        ->expectsQuestion('Check for new firmware?', 'download')
        ->expectsQuestion('Which device model? (OTA updates are for TRMNL devices only)', FirmwareModel::Trmnl->value)
        ->expectsQuestion('Update to which version?', $firmware->id)
        ->expectsQuestion('Which devices should be updated?', ["_$device->id"])
        ->assertExitCode(0);

    $device->refresh();
    expect($device->update_firmware_id)->toBe($firmware->id);
});

test('firmware update command offers only firmware for the selected model', function (): void {
    $user = User::factory()->create();
    $device = Device::factory()->create(['user_id' => $user->id]);
    $ogFirmware = Firmware::factory()->trmnl()->latest()->create(['version_tag' => '1.0.0']);
    $xFirmware = Firmware::factory()->trmnlX()->latest()->create(['version_tag' => '1.0.0']);

    $this->artisan('trmnl:firmware:update')
        ->expectsQuestion('Check for new firmware?', 'no')
        ->expectsQuestion('Which device model? (OTA updates are for TRMNL devices only)', FirmwareModel::TrmnlX->value)
        ->expectsQuestion('Update to which version?', $xFirmware->id)
        ->expectsQuestion('Which devices should be updated?', ["_$device->id"])
        ->assertExitCode(0);

    $device->refresh();
    expect($device->update_firmware_id)->toBe($xFirmware->id)
        ->and($device->update_firmware_id)->not->toBe($ogFirmware->id);
});

test('firmware update command errors when no firmware exists for selected model', function (): void {
    Firmware::factory()->trmnl()->create(['version_tag' => '1.0.0']);

    $this->artisan('trmnl:firmware:update')
        ->expectsQuestion('Check for new firmware?', 'no')
        ->expectsQuestion('Which device model? (OTA updates are for TRMNL devices only)', FirmwareModel::TrmnlX->value)
        ->expectsOutput('No firmware found for model [trmnl_x]. Run trmnl:firmware:check first.')
        ->assertExitCode(0);
});
