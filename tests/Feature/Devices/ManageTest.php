<?php

use App\Models\Device;
use App\Models\User;

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

test('device management page renders a pencil icon link to configure and an empty Actions header', function (): void {
    $user = User::factory()->create();
    // A distinct value (not the create-device modal's default of 900) so the
    // assertion below can't accidentally match that unrelated hidden modal.
    $device = Device::factory()->create([
        'user_id' => $user->id,
        'default_refresh_interval' => 1234,
    ]);

    $response = $this->actingAs($user)->get('/devices');

    $response->assertOk();
    $response->assertDontSee('Actions');
    // Precise tag match: the create-device modal still legitimately contains
    // the word "Refresh" (label "Refresh Rate (seconds)"), so a bare
    // assertDontSee('Refresh') would be a false failure.
    $response->assertDontSee('>Refresh<', false);
    $response->assertDontSee('1234');
    $response->assertDontSee('☁️ Proxy');
    $response->assertSee(route('devices.configure', $device), false);
});
