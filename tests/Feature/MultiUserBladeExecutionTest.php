<?php

declare(strict_types=1);

use App\Models\Plugin;
use App\Models\User;
use App\Support\UserBladeExecution;

beforeEach(function (): void {
    config([
        'app.multi_user_mode' => true,
        'app.dangerously_allow_blade_for_non_admins' => false,
        'app.blade.allow_php_execution' => null,
    ]);
});

function bladeRecipeFor(?User $user): Plugin
{
    return Plugin::factory()->create([
        'user_id' => $user?->id,
        'plugin_type' => 'recipe',
        'markup_language' => 'blade',
        'render_markup' => "{{ 'ok' }}",
        'data_strategy' => 'static',
    ]);
}

test('admins can render blade recipes in multi-user mode', function (): void {
    $admin = User::factory()->admin()->create();
    User::factory()->create();

    expect(bladeRecipeFor($admin)->render(standalone: false))->toBe('ok');
});

test('non-admins cannot render blade recipes in multi-user mode', function (): void {
    User::factory()->admin()->create();
    $user = User::factory()->create();

    expect(fn () => bladeRecipeFor($user)->render(standalone: false))
        ->toThrow(InvalidArgumentException::class, UserBladeExecution::ADMIN_ONLY_MESSAGE);
});

test('the dangerous flag lets non-admins render blade in multi-user mode', function (): void {
    config(['app.dangerously_allow_blade_for_non_admins' => true]);
    User::factory()->admin()->create();
    $user = User::factory()->create();

    expect(bladeRecipeFor($user)->render(standalone: false))->toBe('ok');
});

test('INSECURE_BLADE_ALLOW_PHP_EXECUTION=false disables blade even for admins', function (): void {
    config(['app.blade.allow_php_execution' => false]);
    $admin = User::factory()->admin()->create();

    expect(UserBladeExecution::allowed($admin))->toBeFalse()
        ->and(UserBladeExecution::deniedMessage())->toBe(UserBladeExecution::DISABLED_MESSAGE);
});

test('blade is denied without any user in multi-user mode', function (): void {
    User::factory()->admin()->create();

    expect(UserBladeExecution::allowed())->toBeFalse();
});

test('unowned blade recipes render in multi-user mode', function (): void {
    User::factory()->admin()->create();
    User::factory()->create();

    expect(bladeRecipeFor(null)->render(standalone: false))->toBe('ok');
});
