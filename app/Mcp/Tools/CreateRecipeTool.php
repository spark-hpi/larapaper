<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\ResolvesUserRecipes;
use App\Models\Plugin;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Create a new recipe plugin for the authenticated user.')]
class CreateRecipeTool extends Tool
{
    use ResolvesUserRecipes;

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'markup_language' => 'nullable|string|in:blade,liquid',
            'data_strategy' => 'nullable|string|in:polling,webhook,static',
            'polling_url' => 'nullable|string',
            'data_stale_minutes' => 'nullable|integer|min:1',
        ]);

        $markupLanguage = $validated['markup_language'] ?? 'blade';
        $dataStrategy = $validated['data_strategy'] ?? 'polling';
        $pollingUrl = $validated['polling_url'] ?? null;

        $plugin = new Plugin([
            'data_strategy' => $dataStrategy,
            'polling_url' => $pollingUrl,
            'configuration' => [],
        ]);

        if ($error = $this->validatePollingConfiguration(
            $plugin,
            $dataStrategy,
            $pollingUrl,
            array_key_exists('data_strategy', $validated) && $dataStrategy === 'polling',
        )) {
            return $error;
        }

        $user = $request->user();

        if (! $user instanceof User) {
            return Response::error('Unauthenticated.');
        }

        $plugin = Plugin::query()->create([
            'uuid' => Str::uuid(),
            'user_id' => $user->id,
            'name' => $validated['name'],
            'plugin_type' => 'recipe',
            'markup_language' => $markupLanguage,
            'data_strategy' => $dataStrategy,
            'data_stale_minutes' => $validated['data_stale_minutes'] ?? 60,
            'polling_url' => $pollingUrl,
            'polling_verb' => 'get',
        ]);

        return Response::json([
            'id' => $plugin->id,
            'name' => $plugin->name,
            'url' => $this->recipeBrowserUrl($plugin),
            'markup_language' => $plugin->markup_language,
            ...$this->recipeDataSettings($plugin),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->description('Display name for the recipe.')->required(),
            'markup_language' => $schema->string()->description('Markup language: blade (default) or liquid. Data payload is available as `$data.` (blade) or `{{ data }}` (liquid).'),
            'data_strategy' => $schema->string()->description('Data strategy: polling, webhook, or static.'),
            'polling_url' => $schema->string()->description('URL to poll when data strategy is polling.'),
            'data_stale_minutes' => $schema->integer()->description('Minutes before polled data is considered stale.'),
        ];
    }
}
