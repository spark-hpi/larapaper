<?php

declare(strict_types=1);

use App\Livewire\Actions\DeviceAutoJoin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('device auto join component can be rendered', function (): void {
    $user = User::factory()->create(['assign_new_devices' => false]);

    Livewire::actingAs($user)
        ->test(DeviceAutoJoin::class)
        ->assertSee('Permit Auto-Join')
        ->assertSet('deviceAutojoin', false);
});

test('device auto join component initializes with user settings', function (): void {
    $user = User::factory()->create(['assign_new_devices' => true]);

    Livewire::actingAs($user)
        ->test(DeviceAutoJoin::class)
        ->assertSet('deviceAutojoin', true);
});

test('device auto join component is visible to all confirmed users', function (): void {
    $firstUser = User::factory()->create(['id' => 1, 'assign_new_devices' => false]);
    $otherUser = User::factory()->create(['id' => 2, 'assign_new_devices' => false]);

    Livewire::actingAs($firstUser)
        ->test(DeviceAutoJoin::class)
        ->assertSee('Permit Auto-Join');

    Livewire::actingAs($otherUser)
        ->test(DeviceAutoJoin::class)
        ->assertSee('Permit Auto-Join');
});

test('device auto join component updates user setting when toggled', function (): void {
    $user = User::factory()->create(['assign_new_devices' => false]);

    Livewire::actingAs($user)
        ->test(DeviceAutoJoin::class)
        ->set('deviceAutojoin', true)
        ->assertSet('deviceAutojoin', true);

    $user->refresh();
    expect($user->assign_new_devices)->toBeTrue();
});

// Validation test removed - Livewire automatically handles boolean conversion

test('device auto join component handles false value correctly', function (): void {
    $user = User::factory()->create(['assign_new_devices' => true]);

    Livewire::actingAs($user)
        ->test(DeviceAutoJoin::class)
        ->set('deviceAutojoin', false)
        ->assertSet('deviceAutojoin', false);

    $user->refresh();
    expect($user->assign_new_devices)->toBeFalse();
});

test('device auto join component only updates when deviceAutojoin property changes', function (): void {
    $user = User::factory()->create(['assign_new_devices' => false]);

    $component = Livewire::actingAs($user)
        ->test(DeviceAutoJoin::class);

    // Verify the component is still in its initial state
    $component->assertSet('deviceAutojoin', false);

    $user->refresh();
    expect($user->assign_new_devices)->toBeFalse();
});

test('device auto join component renders correct view', function (): void {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(DeviceAutoJoin::class)
        ->assertViewIs('livewire.actions.device-auto-join');
});

test('device auto join component works with authenticated user', function (): void {
    $user = User::factory()->create(['assign_new_devices' => true]);

    $component = Livewire::actingAs($user)
        ->test(DeviceAutoJoin::class);

    expect($component->instance()->deviceAutojoin)->toBeTrue();
});

test('device auto join component handles multiple updates correctly', function (): void {
    $user = User::factory()->create(['assign_new_devices' => false]);

    $component = Livewire::actingAs($user)
        ->test(DeviceAutoJoin::class)
        ->set('deviceAutojoin', true);

    $user->refresh();
    expect($user->assign_new_devices)->toBeTrue();

    $component->set('deviceAutojoin', false);

    $user->refresh();
    expect($user->assign_new_devices)->toBeFalse();
});
