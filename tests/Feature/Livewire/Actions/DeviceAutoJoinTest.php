<?php

declare(strict_types=1);

use App\Livewire\Actions\DeviceAutoJoin;
use App\Models\User;
use Livewire\Livewire;

test('device auto join component can be rendered for admins', function (): void {
    $user = User::factory()->admin()->create();

    Livewire::actingAs($user)
        ->test(DeviceAutoJoin::class)
        ->assertSee('Auto-Join Permitted')
        ->assertSet('deviceAutojoin', false);
});

test('device auto join reflects global state: on when any user has an open window', function (): void {
    User::factory()->autoJoinEnabled()->create();
    $userB = User::factory()->create();

    Livewire::actingAs($userB)
        ->test(DeviceAutoJoin::class)
        ->assertSet('deviceAutojoin', true);
});

test('device auto join reflects global state: off when no user has an open window', function (): void {
    User::factory()->create();
    $userB = User::factory()->create();

    Livewire::actingAs($userB)
        ->test(DeviceAutoJoin::class)
        ->assertSet('deviceAutojoin', false);
});

test('device auto join toggle is hidden for non-admin users', function (): void {
    User::factory()->create(['id' => 1, 'is_admin' => true]);
    $otherUser = User::factory()->create(['id' => 2, 'is_admin' => false]);

    Livewire::actingAs($otherUser)
        ->test(DeviceAutoJoin::class)
        ->assertDontSee('Auto-Join Permitted');
});

test('non-admin users cannot toggle auto-join', function (): void {
    User::factory()->create(['id' => 1, 'is_admin' => true]);
    $otherUser = User::factory()->create(['id' => 2, 'is_admin' => false]);

    Livewire::actingAs($otherUser)
        ->test(DeviceAutoJoin::class)
        ->set('deviceAutojoin', true)
        ->assertStatus(403);

    expect($otherUser->fresh()->hasActiveAutoJoin())->toBeFalse();
});

test('turning on opens the current user auto join window', function (): void {
    $user = User::factory()->admin()->create();

    Livewire::actingAs($user)
        ->test(DeviceAutoJoin::class)
        ->set('deviceAutojoin', true)
        ->assertSet('deviceAutojoin', true);

    expect($user->fresh()->hasActiveAutoJoin())->toBeTrue();
});

test('turning off closes the auto join window for all users', function (): void {
    $userA = User::factory()->admin()->autoJoinEnabled()->create();
    $userB = User::factory()->autoJoinEnabled()->create();

    Livewire::actingAs($userA)
        ->test(DeviceAutoJoin::class)
        ->set('deviceAutojoin', false)
        ->assertSet('deviceAutojoin', false)
        ->assertSet('autoJoinExpiresAt', null);

    expect($userA->fresh()->assign_new_devices_until)->toBeNull()
        ->and($userB->fresh()->assign_new_devices_until)->toBeNull();
});

test('device auto join component renders correct view', function (): void {
    $user = User::factory()->admin()->create();

    Livewire::actingAs($user)
        ->test(DeviceAutoJoin::class)
        ->assertViewIs('livewire.actions.device-auto-join');
});

test('device auto join is persisted in the layout so wire navigate does not remount it', function (): void {
    $user = User::factory()->admin()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertSee('x-persist="device-auto-join-desktop"', false)
        ->assertSee('x-persist="device-auto-join-mobile"', false)
        ->assertSee('Auto-Join Permitted');
});

test('device auto join component handles multiple updates correctly', function (): void {
    $user = User::factory()->admin()->create();

    $component = Livewire::actingAs($user)
        ->test(DeviceAutoJoin::class)
        ->set('deviceAutojoin', true);

    expect($user->fresh()->hasActiveAutoJoin())->toBeTrue();

    $component->set('deviceAutojoin', false);

    expect($user->fresh()->hasActiveAutoJoin())->toBeFalse();
});

test('device auto join sibling instances stay in sync', function (): void {
    $user = User::factory()->admin()->create();

    Livewire::actingAs($user)
        ->test(DeviceAutoJoin::class)
        ->set('deviceAutojoin', true)
        ->assertDispatched('device-auto-join-changed');

    Livewire::actingAs($user->refresh())
        ->test(DeviceAutoJoin::class)
        ->dispatch('device-auto-join-changed')
        ->assertSet('deviceAutojoin', true)
        ->assertSet('autoJoinExpiresAt', $user->assign_new_devices_until->timestamp);
});

test('enabling auto join opens a window of fifteen minutes', function (): void {
    $this->freezeTime();
    $user = User::factory()->admin()->create();

    Livewire::actingAs($user)
        ->test(DeviceAutoJoin::class)
        ->set('deviceAutojoin', true)
        ->assertSet('autoJoinExpiresAt', now()->addMinutes(15)->timestamp)
        ->assertSet('serverNow', now()->timestamp);

    expect($user->refresh()->assign_new_devices_until->timestamp)->toBe(now()->addMinutes(15)->timestamp);
});

test('device auto join component mounts as disabled once the window has expired', function (): void {
    $user = User::factory()->admin()->create(['assign_new_devices_until' => now()->subSecond()]);

    Livewire::actingAs($user)
        ->test(DeviceAutoJoin::class)
        ->assertSet('deviceAutojoin', false)
        ->assertSet('autoJoinExpiresAt', null);
});

test('device auto join switches off when synced after the window expires', function (): void {
    $user = User::factory()->admin()->autoJoinEnabled()->create();

    $component = Livewire::actingAs($user)
        ->test(DeviceAutoJoin::class)
        ->assertSet('deviceAutojoin', true);

    $this->travel(16)->minutes();

    $component->call('syncState')
        ->assertSet('deviceAutojoin', false)
        ->assertSet('autoJoinExpiresAt', null);
});

test('device auto join exposes the expiry and server time for the countdown on mount', function (): void {
    $this->freezeTime();
    $user = User::factory()->admin()->autoJoinEnabled()->create();

    Livewire::actingAs($user)
        ->test(DeviceAutoJoin::class)
        ->assertSet('autoJoinExpiresAt', now()->addMinutes(15)->timestamp)
        ->assertSet('serverNow', now()->timestamp);
});
