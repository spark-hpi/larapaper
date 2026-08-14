<?php

declare(strict_types=1);

use App\Models\Device;
use App\Models\DeviceModel;
use App\Models\User;

test('dashboard shows device image with correct rotation', function (): void {
    $user = User::factory()->create();
    $device = Device::factory()->create([
        'user_id' => $user->id,
        'rotate' => 90,
        'current_screen_image' => 'test-image-uuid',
    ]);

    // Mock the file existence check
    Illuminate\Support\Facades\Storage::fake('public');
    Illuminate\Support\Facades\Storage::disk('public')->put('images/generated/test-image-uuid.png', 'fake-image-content');

    $response = $this->actingAs($user)
        ->get(route('dashboard'));

    $response->assertSuccessful();
    $response->assertSee('rotate-[270deg]');
    $response->assertSee('origin-center');
});

test('device configure page shows device image with correct rotation', function (): void {
    $user = User::factory()->create();
    $device = Device::factory()->create([
        'user_id' => $user->id,
        'rotate' => 90,
        'current_screen_image' => 'test-image-uuid',
    ]);

    // Mock the file existence check
    Illuminate\Support\Facades\Storage::fake('public');
    Illuminate\Support\Facades\Storage::disk('public')->put('images/generated/test-image-uuid.png', 'fake-image-content');

    $response = $this->actingAs($user)
        ->get(route('devices.configure', $device));

    $response->assertSuccessful();
    $response->assertSee('rotate-[270deg]');
    $response->assertSee('origin-center');
});

test('device with no rotation shows no transform style', function (): void {
    $user = User::factory()->create();
    $device = Device::factory()->create([
        'user_id' => $user->id,
        'rotate' => 0,
        'current_screen_image' => 'test-image-uuid',
    ]);

    // Mock the file existence check
    Illuminate\Support\Facades\Storage::fake('public');
    Illuminate\Support\Facades\Storage::disk('public')->put('images/generated/test-image-uuid.png', 'fake-image-content');

    $response = $this->actingAs($user)
        ->get(route('dashboard'));

    $response->assertSuccessful();
    $response->assertSee('rotate-[0deg]');
});

test('device with null rotation defaults to 0', function (): void {
    $user = User::factory()->create();
    $device = Device::factory()->create([
        'user_id' => $user->id,
        'rotate' => null,
        'current_screen_image' => 'test-image-uuid',
    ]);

    // Mock the file existence check
    Illuminate\Support\Facades\Storage::fake('public');
    Illuminate\Support\Facades\Storage::disk('public')->put('images/generated/test-image-uuid.png', 'fake-image-content');

    $response = $this->actingAs($user)
        ->get(route('dashboard'));

    $response->assertSuccessful();
    $response->assertSee('rotate-[0deg]');
});

test('dashboard shows device image with 270 degree rotation as rotate 90', function (): void {
    $user = User::factory()->create();
    Device::factory()->create([
        'user_id' => $user->id,
        'rotate' => 270,
        'current_screen_image' => 'test-image-uuid',
    ]);

    Illuminate\Support\Facades\Storage::fake('public');
    Illuminate\Support\Facades\Storage::disk('public')->put('images/generated/test-image-uuid.png', 'fake-image-content');

    $response = $this->actingAs($user)
        ->get(route('dashboard'));

    $response->assertSuccessful();
    $response->assertSee('rotate-[90deg]');
    $response->assertSee('origin-center');
});

test('device configure page shows device image with 270 degree rotation as rotate 90', function (): void {
    $user = User::factory()->create();
    $device = Device::factory()->create([
        'user_id' => $user->id,
        'rotate' => 270,
        'current_screen_image' => 'test-image-uuid',
    ]);

    Illuminate\Support\Facades\Storage::fake('public');
    Illuminate\Support\Facades\Storage::disk('public')->put('images/generated/test-image-uuid.png', 'fake-image-content');

    $response = $this->actingAs($user)
        ->get(route('devices.configure', $device));

    $response->assertSuccessful();
    $response->assertSee('rotate-[90deg]');
    $response->assertSee('origin-center');
});

test('dashboard uses device model rotation for preview when device rotate differs', function (): void {
    $user = User::factory()->create();
    $deviceModel = DeviceModel::factory()->create(['rotation' => 0]);
    Device::factory()->create([
        'user_id' => $user->id,
        'device_model_id' => $deviceModel->id,
        'rotate' => 270,
        'current_screen_image' => 'test-image-uuid',
    ]);

    Illuminate\Support\Facades\Storage::fake('public');
    Illuminate\Support\Facades\Storage::disk('public')->put('images/generated/test-image-uuid.png', 'fake-image-content');

    $response = $this->actingAs($user)
        ->get(route('dashboard'));

    $response->assertSuccessful();
    $response->assertSee('rotate-[0deg]');
    $response->assertDontSee('rotate-[90deg]');
});
