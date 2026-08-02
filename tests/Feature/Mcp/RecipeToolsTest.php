<?php

use App\Mcp\Servers\McpServer;
use App\Mcp\Tools\CreateRecipeTool;
use App\Mcp\Tools\GetRecipeTool;
use App\Mcp\Tools\ListRecipesTool;
use App\Mcp\Tools\RenderRecipeTool;
use App\Mcp\Tools\UpdateRecipeMarkupTool;
use App\Mcp\Tools\UpdateRecipeSettingsTool;
use App\Models\Plugin;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use OffloadProject\Toggle\Facades\Toggle;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    Toggle::enable('mcp');
});

test('list recipes returns only the authenticated users recipes', function (): void {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $recipe = Plugin::factory()->for($user)->create(['name' => 'My Recipe']);
    Plugin::factory()->for($otherUser)->create(['name' => 'Other Recipe']);
    Plugin::factory()->for($user)->imageWebhook()->create(['name' => 'Native Plugin']);

    McpServer::actingAs($user)
        ->tool(ListRecipesTool::class)
        ->assertOk()
        ->assertSee((string) $recipe->id)
        ->assertSee('My Recipe')
        ->assertDontSee('Other Recipe');
});

test('get recipe returns full recipe details for the owner', function (): void {
    $user = User::factory()->create();
    $recipe = Plugin::factory()->for($user)->create([
        'render_markup' => '<div>Full</div>',
        'render_markup_shared' => '{% comment %}shared{% endcomment %}',
        'markup_language' => 'liquid',
        'framework_version' => '3.1.0',
    ]);

    McpServer::actingAs($user)
        ->tool(GetRecipeTool::class, ['id' => $recipe->id])
        ->assertOk()
        ->assertSee('"markup"')
        ->assertSee('<div>Full</div>')
        ->assertSee('{% comment %}shared{% endcomment %}')
        ->assertSee('liquid')
        ->assertSee('3.1.0')
        ->assertSee(route('plugins.recipe', ['plugin' => $recipe->id]));
});

test('get recipe resolves markup from render_markup_view when inline markup is empty', function (): void {
    $user = User::factory()->create();
    $recipe = Plugin::factory()->for($user)->create([
        'render_markup' => null,
        'render_markup_view' => 'recipes.zen',
        'markup_language' => 'liquid',
    ]);

    McpServer::actingAs($user)
        ->tool(GetRecipeTool::class, ['id' => $recipe->id])
        ->assertOk()
        ->assertSee('recipes.zen')
        ->assertSee('markup')
        ->assertDontSee('"full":null');
});

test('get recipe returns data payload for the owner', function (): void {
    $user = User::factory()->create();
    $recipe = Plugin::factory()->for($user)->create([
        'data_strategy' => 'static',
        'data_payload' => ['temperature' => 72, 'unit' => 'F'],
    ]);

    McpServer::actingAs($user)
        ->tool(GetRecipeTool::class, ['id' => $recipe->id])
        ->assertOk()
        ->assertSee('"data_payload"')
        ->assertSee('temperature')
        ->assertSee('72');
});

test('get recipe returns an error for another users recipe', function (): void {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $recipe = Plugin::factory()->for($otherUser)->create();

    McpServer::actingAs($user)
        ->tool(GetRecipeTool::class, ['id' => $recipe->id])
        ->assertHasErrors()
        ->assertSee('Recipe not found.');
});

test('get recipe returns an error for native plugins', function (): void {
    $user = User::factory()->create();
    $plugin = Plugin::factory()->for($user)->imageWebhook()->create();

    McpServer::actingAs($user)
        ->tool(GetRecipeTool::class, ['id' => $plugin->id])
        ->assertHasErrors()
        ->assertSee('Recipe not found.');
});

test('create recipe creates a recipe plugin for the user', function (): void {
    $user = User::factory()->create();

    McpServer::actingAs($user)
        ->tool(CreateRecipeTool::class, ['name' => 'New Recipe'])
        ->assertOk()
        ->assertSee('New Recipe');

    expect(Plugin::query()->where('user_id', $user->id)->where('name', 'New Recipe')->exists())->toBeTrue();
});

test('create recipe returns id and browser url', function (): void {
    $user = User::factory()->create();

    $response = McpServer::actingAs($user)
        ->tool(CreateRecipeTool::class, ['name' => 'Redirect Recipe']);

    $response
        ->assertOk()
        ->assertSee('"id"')
        ->assertSee('"url"');

    $recipe = Plugin::query()->where('user_id', $user->id)->where('name', 'Redirect Recipe')->first();

    expect($recipe)->not->toBeNull();

    $response
        ->assertSee((string) $recipe->id)
        ->assertSee(route('plugins.recipe', ['plugin' => $recipe->id]));
});

test('create recipe can set data strategy and polling url', function (): void {
    $user = User::factory()->create();

    McpServer::actingAs($user)
        ->tool(CreateRecipeTool::class, [
            'name' => 'Polling Recipe',
            'data_strategy' => 'polling',
            'polling_url' => 'https://example.com/data.json',
        ])
        ->assertOk()
        ->assertSee('https://example.com/data.json');

    $recipe = Plugin::query()->where('user_id', $user->id)->where('name', 'Polling Recipe')->first();

    expect($recipe)->not->toBeNull()
        ->and($recipe->data_strategy)->toBe('polling')
        ->and($recipe->polling_url)->toBe('https://example.com/data.json');
});

test('create recipe requires polling url when strategy is polling', function (): void {
    $user = User::factory()->create();

    McpServer::actingAs($user)
        ->tool(CreateRecipeTool::class, [
            'name' => 'Invalid Polling Recipe',
            'data_strategy' => 'polling',
        ])
        ->assertHasErrors()
        ->assertSee('Polling URL is required');
});

test('create recipe defaults to blade markup language', function (): void {
    $user = User::factory()->create();

    McpServer::actingAs($user)
        ->tool(CreateRecipeTool::class, ['name' => 'Blade Recipe'])
        ->assertOk()
        ->assertSee('blade');

    $recipe = Plugin::query()->where('user_id', $user->id)->where('name', 'Blade Recipe')->first();

    expect($recipe)->not->toBeNull()
        ->and($recipe->markup_language)->toBe('blade');
});

test('create recipe can set liquid markup language', function (): void {
    $user = User::factory()->create();

    McpServer::actingAs($user)
        ->tool(CreateRecipeTool::class, [
            'name' => 'Liquid Recipe',
            'markup_language' => 'liquid',
        ])
        ->assertOk()
        ->assertSee('liquid');

    $recipe = Plugin::query()->where('user_id', $user->id)->where('name', 'Liquid Recipe')->first();

    expect($recipe)->not->toBeNull()
        ->and($recipe->markup_language)->toBe('liquid');
});

test('update recipe markup updates layout columns', function (): void {
    $user = User::factory()->create();
    $recipe = Plugin::factory()->for($user)->create();

    McpServer::actingAs($user)
        ->tool(UpdateRecipeMarkupTool::class, [
            'id' => $recipe->id,
            'markup' => [
                'full' => '<div>Updated</div>',
                'shared' => 'shared markup',
            ],
        ])
        ->assertOk()
        ->assertSee('full')
        ->assertSee('shared');

    $recipe->refresh();

    expect($recipe->render_markup)->toBe('<div>Updated</div>')
        ->and($recipe->render_markup_shared)->toBe('shared markup');
});

test('update recipe markup can set markup language', function (): void {
    $user = User::factory()->create();
    $recipe = Plugin::factory()->for($user)->create();

    McpServer::actingAs($user)
        ->tool(UpdateRecipeMarkupTool::class, [
            'id' => $recipe->id,
            'markup_language' => 'liquid',
            'markup' => [
                'full' => '{% comment %}liquid{% endcomment %}',
            ],
        ])
        ->assertOk()
        ->assertSee('liquid');

    $recipe->refresh();

    expect($recipe->markup_language)->toBe('liquid')
        ->and($recipe->render_markup)->toBe('{% comment %}liquid{% endcomment %}');
});

test('get recipe returns webhook url when data strategy is webhook', function (): void {
    $user = User::factory()->create();
    $recipe = Plugin::factory()->for($user)->create([
        'data_strategy' => 'webhook',
    ]);

    $expectedUrl = route('api.custom_plugins.webhook', ['plugin' => $recipe->uuid]);

    McpServer::actingAs($user)
        ->tool(GetRecipeTool::class, ['id' => $recipe->id])
        ->assertOk()
        ->assertSee('webhook')
        ->assertSee($expectedUrl);
});

test('update recipe settings can set polling url', function (): void {
    $user = User::factory()->create();
    $recipe = Plugin::factory()->for($user)->create([
        'data_strategy' => 'polling',
        'polling_url' => null,
    ]);

    McpServer::actingAs($user)
        ->tool(UpdateRecipeSettingsTool::class, [
            'id' => $recipe->id,
            'data_strategy' => 'polling',
            'polling_url' => 'https://example.com/data.json',
        ])
        ->assertOk()
        ->assertSee('https://example.com/data.json');

    $recipe->refresh();

    expect($recipe->data_strategy)->toBe('polling')
        ->and($recipe->polling_url)->toBe('https://example.com/data.json');
});

test('update recipe settings can switch to webhook and returns webhook url', function (): void {
    $user = User::factory()->create();
    $recipe = Plugin::factory()->for($user)->create([
        'data_strategy' => 'polling',
        'polling_url' => 'https://example.com/data.json',
    ]);

    $expectedUrl = route('api.custom_plugins.webhook', ['plugin' => $recipe->uuid]);

    McpServer::actingAs($user)
        ->tool(UpdateRecipeSettingsTool::class, [
            'id' => $recipe->id,
            'data_strategy' => 'webhook',
        ])
        ->assertOk()
        ->assertSee('webhook')
        ->assertSee($expectedUrl);

    $recipe->refresh();

    expect($recipe->data_strategy)->toBe('webhook');
});

test('update recipe settings updates render settings', function (): void {
    $user = User::factory()->create();
    $recipe = Plugin::factory()->for($user)->create();

    McpServer::actingAs($user)
        ->tool(UpdateRecipeSettingsTool::class, [
            'id' => $recipe->id,
            'markup_language' => 'liquid',
            'framework_version' => '3.1.0',
            'preferred_renderer' => 'trmnl-liquid',
        ])
        ->assertOk()
        ->assertSee('liquid')
        ->assertSee('3.1.0')
        ->assertSee('trmnl-liquid');

    $recipe->refresh();

    expect($recipe->markup_language)->toBe('liquid')
        ->and($recipe->framework_version)->toBe('3.1.0')
        ->and($recipe->preferred_renderer)->toBe('trmnl-liquid');
});

test('update recipe settings requires polling url when strategy is polling', function (): void {
    $user = User::factory()->create();
    $recipe = Plugin::factory()->for($user)->create([
        'data_strategy' => 'webhook',
        'polling_url' => null,
    ]);

    McpServer::actingAs($user)
        ->tool(UpdateRecipeSettingsTool::class, [
            'id' => $recipe->id,
            'data_strategy' => 'polling',
        ])
        ->assertHasErrors()
        ->assertSee('Polling URL is required');
});

test('update recipe settings rejects invalid framework versions', function (): void {
    $user = User::factory()->create();
    $recipe = Plugin::factory()->for($user)->create();

    McpServer::actingAs($user)
        ->tool(UpdateRecipeSettingsTool::class, [
            'id' => $recipe->id,
            'framework_version' => '1.0.0',
        ])
        ->assertHasErrors()
        ->assertSee('Framework version must be at least 2.0.0.');
});

test('render recipe returns ok when markup renders', function (): void {
    $user = User::factory()->create();
    $recipe = Plugin::factory()->for($user)->create([
        'render_markup' => '<div>Hello</div>',
        'markup_language' => 'blade',
    ]);

    McpServer::actingAs($user)
        ->tool(RenderRecipeTool::class, ['id' => $recipe->id])
        ->assertOk()
        ->assertSee('"ok":true')
        ->assertSee('<div>Hello</div>');
});

test('render recipe returns an error message when liquid markup is invalid', function (): void {
    $user = User::factory()->create();
    $recipe = Plugin::factory()->for($user)->create([
        'render_markup' => '{% if %}',
        'markup_language' => 'liquid',
    ]);

    McpServer::actingAs($user)
        ->tool(RenderRecipeTool::class, ['id' => $recipe->id])
        ->assertOk()
        ->assertSee('"ok":false')
        ->assertSee('error');
});

test('mcp endpoint rejects unauthenticated requests', function (): void {
    $this->postJson('/mcp', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'initialize',
        'params' => [
            'protocolVersion' => '2024-11-05',
            'capabilities' => new stdClass,
            'clientInfo' => [
                'name' => 'test',
                'version' => '1.0.0',
            ],
        ],
    ])->assertUnauthorized();
});

test('mcp endpoint returns not found when the mcp toggle is disabled', function (): void {
    Toggle::disable('mcp');

    $user = User::factory()->create();

    Sanctum::actingAs($user, ['mcp']);

    $this->postJson('/mcp', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'initialize',
        'params' => [
            'protocolVersion' => '2024-11-05',
            'capabilities' => new stdClass,
            'clientInfo' => [
                'name' => 'test',
                'version' => '1.0.0',
            ],
        ],
    ])->assertNotFound();
});

test('mcp endpoint rejects tokens without the mcp ability', function (): void {
    $user = User::factory()->create();

    Sanctum::actingAs($user, ['update-screen']);

    $this->postJson('/mcp', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'initialize',
        'params' => [
            'protocolVersion' => '2024-11-05',
            'capabilities' => new stdClass,
            'clientInfo' => [
                'name' => 'test',
                'version' => '1.0.0',
            ],
        ],
    ])->assertForbidden();
});

test('mcp endpoint accepts tokens with the mcp ability', function (): void {
    $user = User::factory()->create();

    Sanctum::actingAs($user, ['mcp']);

    $this->postJson('/mcp', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'initialize',
        'params' => [
            'protocolVersion' => '2024-11-05',
            'capabilities' => new stdClass,
            'clientInfo' => [
                'name' => 'test',
                'version' => '1.0.0',
            ],
        ],
    ])->assertOk();
});
