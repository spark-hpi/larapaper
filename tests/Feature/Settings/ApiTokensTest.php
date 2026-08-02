<?php

use App\Models\User;
use Livewire\Livewire;

use OffloadProject\Toggle\Facades\Toggle;

beforeEach(function (): void {
    Toggle::enable('mcp');
});

test('api tokens page is accessible', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('settings.api-tokens'))
        ->assertOk()
        ->assertSee('API Tokens')
        ->assertSee('Token type');
});

test('can create an api token with full access', function (): void {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::settings.api-tokens')
        ->set('tokenName', 'CLI Token')
        ->set('tokenType', 'api')
        ->call('createToken')
        ->assertSet('showNewTokenModal', true);

    $token = $user->tokens()->first();

    expect($token)->not->toBeNull()
        ->and($token->name)->toBe('CLI Token')
        ->and($token->abilities)->toBe(['*']);
});

test('can create an mcp token with the mcp ability', function (): void {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::settings.api-tokens')
        ->set('tokenName', 'Cursor MCP')
        ->set('tokenType', 'mcp')
        ->call('createToken')
        ->assertSet('showNewTokenModal', true);

    $token = $user->tokens()->first();

    expect($token)->not->toBeNull()
        ->and($token->name)->toBe('Cursor MCP')
        ->and($token->abilities)->toBe(['mcp']);
});

test('token list shows abilities', function (): void {
    $user = User::factory()->create();
    $user->createToken('MCP Token', ['mcp']);

    Livewire::actingAs($user)
        ->test('pages::settings.api-tokens')
        ->assertSee('MCP Token')
        ->assertSee('mcp');
});
