<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\ResolvesUserRecipes;
use App\Models\Plugin;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Update recipe settings: render options, data strategy, and polling URL. Returns the webhook URL when strategy is webhook.')]
class UpdateRecipeSettingsTool extends Tool
{
    use ResolvesUserRecipes;

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'id' => 'required|integer',
            'markup_language' => 'nullable|string|in:blade,liquid',
            'preferred_renderer' => 'nullable|string|in:trmnl-liquid',
            'framework_version' => 'nullable|string',
            'data_strategy' => 'nullable|string|in:polling,webhook,static',
            'polling_url' => 'nullable|string',
            'polling_verb' => 'nullable|string|in:get,post',
            'data_stale_minutes' => 'nullable|integer|min:1',
        ]);

        $plugin = $this->findUserRecipeOrFail($request, $validated['id']);

        if ($plugin instanceof Response) {
            return $plugin;
        }

        if (array_key_exists('framework_version', $validated) && $validated['framework_version'] !== null) {
            try {
                Plugin::validateFrameworkVersion($validated['framework_version'], function (string $message): void {
                    throw ValidationException::withMessages([
                        'framework_version' => $message,
                    ]);
                });
            } catch (ValidationException $exception) {
                $message = $exception->errors()['framework_version'][0] ?? $exception->getMessage();

                return Response::error($message);
            }
        }

        $effectiveStrategy = $validated['data_strategy'] ?? $plugin->data_strategy;
        $effectivePollingUrl = array_key_exists('polling_url', $validated)
            ? $validated['polling_url']
            : $plugin->polling_url;

        if ($error = $this->validatePollingConfiguration($plugin, $effectiveStrategy, $effectivePollingUrl)) {
            return $error;
        }

        $updates = Arr::only($validated, [
            'markup_language',
            'preferred_renderer',
            'framework_version',
            'data_strategy',
            'polling_url',
            'polling_verb',
            'data_stale_minutes',
        ]);

        if ($updates === []) {
            return Response::error('No settings were provided to update.');
        }

        $plugin->update($updates);
        $plugin->refresh();

        return Response::json($this->recipeSettingsPayload($plugin));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('The recipe ID.')->required(),
            'markup_language' => $schema->string()->description('Markup language: blade or liquid.'),
            'preferred_renderer' => $schema->string()->description('Preferred renderer. Use trmnl-liquid for the external renderer, or omit for the default.'),
            'framework_version' => $schema->string()->description('TRMNL framework version in X.Y.Z format. Leave blank for the latest version (default).'),
            'data_strategy' => $schema->string()->description('Data strategy: polling, webhook, or static.'),
            'polling_url' => $schema->string()->description('URL to poll when data strategy is polling.'),
            'polling_verb' => $schema->string()->description('HTTP verb for polling: get or post.'),
            'data_stale_minutes' => $schema->integer()->description('Minutes before polled data is considered stale.'),
        ];
    }
}
