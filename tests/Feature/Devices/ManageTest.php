<?php

use App\Models\Device;
use App\Models\DeviceModel;
use App\Models\User;
use Carbon\Carbon;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

test('device management page can be rendered', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->get('/devices');

    $response->assertOk();
});

test('user can create a new device', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $deviceData = [
        'name' => 'Test Device',
        'mac_address' => '00:11:22:33:44:55',
        'api_key' => 'test-api-key',
        'default_refresh_interval' => 900,
        'friendly_id' => 'test-device-1',
    ];

    $response = Livewire::test('devices.manage')
        ->set('name', $deviceData['name'])
        ->set('mac_address', $deviceData['mac_address'])
        ->set('api_key', $deviceData['api_key'])
        ->set('default_refresh_interval', $deviceData['default_refresh_interval'])
        ->set('friendly_id', $deviceData['friendly_id'])
        ->call('createDevice');

    $response->assertHasNoErrors();

    expect(Device::count())->toBe(1);

    $device = Device::first();
    expect($device->name)->toBe($deviceData['name']);
    expect($device->mac_address)->toBe($deviceData['mac_address']);
    expect($device->api_key)->toBe($deviceData['api_key']);
    expect($device->default_refresh_interval)->toBe($deviceData['default_refresh_interval']);
    expect($device->friendly_id)->toBe($deviceData['friendly_id']);
    expect($device->user_id)->toBe($user->id);
});

test('device creation requires required fields', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = Livewire::test('devices.manage')
        ->set('name', '')
        ->set('mac_address', '')
        ->set('api_key', '')
        ->set('default_refresh_interval', '')
        ->set('friendly_id', '')
        ->call('createDevice');

    $response->assertHasErrors([
        'mac_address',
        'api_key',
        'default_refresh_interval',
    ]);
});

test('user can toggle proxy cloud for their device', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);
    $device = Device::factory()->create([
        'user_id' => $user->id,
        'proxy_cloud' => false,
    ]);

    $response = Livewire::test('devices.manage')
        ->call('toggleProxyCloud', $device);

    $response->assertHasNoErrors();
    expect($device->fresh()->proxy_cloud)->toBeTrue();

    // Toggle back to false
    $response = Livewire::test('devices.manage')
        ->call('toggleProxyCloud', $device);

    expect($device->fresh()->proxy_cloud)->toBeFalse();
});

test('user cannot toggle proxy cloud for other users devices', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $otherUser = User::factory()->create();
    $device = Device::factory()->create([
        'user_id' => $otherUser->id,
        'proxy_cloud' => false,
    ]);

    $response = Livewire::test('devices.manage')
        ->call('toggleProxyCloud', $device);

    $response->assertStatus(403);
    expect($device->fresh()->proxy_cloud)->toBeFalse();
});

test('user can pause device with preset duration', function (): void {
    $user = User::factory()->create();
    $device = Device::factory()->create(['user_id' => $user->id]);
    $this->actingAs($user);

    Carbon::setTestNow('2026-08-04 12:00:00');

    Livewire::test('devices.manage')
        ->set('pause_duration', 60)
        ->call('pauseDevice', $device->id)
        ->assertHasNoErrors();

    expect($device->fresh()->pause_until?->equalTo(now()->addMinutes(60)))->toBeTrue();
});

test('user can pause device with custom date and time in user timezone', function (): void {
    $user = User::factory()->create(['timezone' => 'Europe/Berlin']);
    $device = Device::factory()->create(['user_id' => $user->id]);
    $this->actingAs($user);

    Carbon::setTestNow(Carbon::parse('2026-08-04 10:00:00', 'UTC'));

    Livewire::test('devices.manage')
        ->set('pause_until_date', '2026-08-10')
        ->set('pause_until_time', '14:30')
        ->call('pauseDevice', $device->id)
        ->assertHasNoErrors();

    expect($device->fresh()->pause_until?->utc()->format('Y-m-d H:i'))->toBe('2026-08-10 12:30');
});

test('custom pause rejects datetime more than 30 days in the future', function (): void {
    $user = User::factory()->create(['timezone' => 'UTC']);
    $device = Device::factory()->create(['user_id' => $user->id]);
    $this->actingAs($user);

    Carbon::setTestNow('2026-08-04 12:00:00');

    Livewire::test('devices.manage')
        ->set('pause_until_date', '2026-09-10')
        ->set('pause_until_time', '14:30')
        ->call('pauseDevice', $device->id)
        ->assertHasErrors(['pause_until_date']);

    expect($device->fresh()->pause_until)->toBeNull();
});

test('custom pause rejects datetime in the past', function (): void {
    $user = User::factory()->create(['timezone' => 'UTC']);
    $device = Device::factory()->create(['user_id' => $user->id]);
    $this->actingAs($user);

    Carbon::setTestNow('2026-08-04 12:00:00');

    Livewire::test('devices.manage')
        ->set('pause_until_date', '2026-08-04')
        ->set('pause_until_time', '08:00')
        ->call('pauseDevice', $device->id)
        ->assertHasErrors(['pause_until_date']);

    expect($device->fresh()->pause_until)->toBeNull();
});

test('user can end pause early', function (): void {
    $user = User::factory()->create();
    $device = Device::factory()->create([
        'user_id' => $user->id,
        'pause_until' => now()->addHour(),
    ]);
    $this->actingAs($user);

    Livewire::test('devices.manage')
        ->call('unpauseDevice', $device->id)
        ->assertHasNoErrors();

    expect($device->fresh()->pause_until)->toBeNull();
});

test('unpause modal shows touch bar instructions for v2 devices', function (): void {
    $user = User::factory()->create();
    $deviceModel = DeviceModel::query()->where('name', 'v2')->firstOrFail();
    $device = Device::factory()->create([
        'user_id' => $user->id,
        'device_model_id' => $deviceModel->id,
        'pause_until' => now()->addHour(),
    ]);
    $this->actingAs($user);

    Livewire::test('devices.manage')
        ->assertSee('touch bar in the middle')
        ->assertDontSee('physical screen button');
});

test('unpause modal shows screen button instructions for non-v2 devices', function (): void {
    $user = User::factory()->create();
    $device = Device::factory()->create([
        'user_id' => $user->id,
        'pause_until' => now()->addHour(),
    ]);
    $this->actingAs($user);

    Livewire::test('devices.manage')
        ->assertSee('physical screen button')
        ->assertDontSee('touch bar in the middle');
});
