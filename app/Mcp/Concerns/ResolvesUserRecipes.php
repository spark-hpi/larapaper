<?php

namespace App\Mcp\Concerns;

use App\Models\Plugin;
use App\Models\User;
use Exception;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;

trait ResolvesUserRecipes
{
    /**
     * @var array<string, string>
     */
    protected const RECIPE_LAYOUT_COLUMNS = [
        'full' => 'render_markup',
        'half_horizontal' => 'render_markup_half_horizontal',
        'half_vertical' => 'render_markup_half_vertical',
        'quadrant' => 'render_markup_quadrant',
        'shared' => 'render_markup_shared',
    ];

    protected function userRecipesQuery(User $user)
    {
        return Plugin::query()
            ->where('user_id', $user->id)
            ->where('plugin_type', 'recipe');
    }

    protected function findUserRecipe(Request $request, int $id): ?Plugin
    {
        return $this->userRecipesQuery($request->user())
            ->whereKey($id)
            ->first();
    }

    protected function findUserRecipeOrFail(Request $request, int $id): Plugin|Response
    {
        return $this->findUserRecipe($request, $id)
            ?? Response::error('Recipe not found.');
    }

    protected function recipeBrowserUrl(Plugin $plugin): string
    {
        return route('plugins.recipe', ['plugin' => $plugin->id]);
    }

    protected function recipeWebhookUrl(Plugin $plugin): string
    {
        return route('api.custom_plugins.webhook', ['plugin' => $plugin->uuid]);
    }

    protected function validatePollingConfiguration(Plugin $plugin, string $dataStrategy, ?string $pollingUrl, bool $requirePollingUrl = true): ?Response
    {
        if ($dataStrategy === 'polling' && empty($pollingUrl)) {
            if ($requirePollingUrl) {
                return Response::error('Polling URL is required when data strategy is polling.');
            }

            return null;
        }

        if ($dataStrategy !== 'polling' || empty($pollingUrl)) {
            return null;
        }

        try {
            $resolvedUrl = $plugin->resolveLiquidVariables($pollingUrl);

            if (! filter_var($resolvedUrl, FILTER_VALIDATE_URL)) {
                return Response::error('The polling URL must be a valid URL after resolving configuration variables.');
            }
        } catch (Exception $exception) {
            return Response::error('Error resolving Liquid variables in polling URL: '.$exception->getMessage());
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function recipeDataSettings(Plugin $plugin): array
    {
        $settings = [
            'data_strategy' => $plugin->data_strategy,
            'data_stale_minutes' => $plugin->data_stale_minutes,
            'polling_url' => $plugin->polling_url,
            'polling_verb' => $plugin->polling_verb,
        ];

        if ($plugin->data_strategy === 'webhook') {
            $settings['webhook_url'] = $this->recipeWebhookUrl($plugin);
        }

        return $settings;
    }

    /**
     * @return array<string, string|null>
     */
    protected function recipeMarkupLayouts(Plugin $plugin): array
    {
        $layouts = collect(self::RECIPE_LAYOUT_COLUMNS)
            ->mapWithKeys(fn (string $column, string $layout): array => [$layout => $plugin->{$column}])
            ->all();

        if (empty($layouts['full']) && $plugin->render_markup_view) {
            $layouts['full'] = $this->resolveMarkupViewContent($plugin);
        }

        return $layouts;
    }

    protected function resolveMarkupViewContent(Plugin $plugin): ?string
    {
        if (! $plugin->render_markup_view) {
            return null;
        }

        $basePath = resource_path('views/'.str_replace('.', '/', $plugin->render_markup_view));

        foreach ([$basePath.'.blade.php', $basePath.'.liquid'] as $path) {
            if (file_exists($path)) {
                return file_get_contents($path) ?: null;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function recipeSummaryPayload(Plugin $plugin): array
    {
        return [
            'id' => $plugin->id,
            'name' => $plugin->name,
            'url' => $this->recipeBrowserUrl($plugin),
            'data_strategy' => $plugin->data_strategy,
            'markup_language' => $plugin->markup_language,
            'preferred_renderer' => $plugin->preferred_renderer,
            'framework_version' => $plugin->framework_version,
            'updated_at' => $plugin->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function recipeDetailPayload(Plugin $plugin): array
    {
        $payload = [
            ...$this->recipeSettingsPayload($plugin),
            'markup' => $this->recipeMarkupLayouts($plugin),
            'data_payload' => $plugin->data_payload,
            'data_payload_updated_at' => $plugin->data_payload_updated_at?->toIso8601String(),
        ];

        if ($plugin->render_markup_view) {
            $payload['render_markup_view'] = $plugin->render_markup_view;
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    protected function recipeSettingsPayload(Plugin $plugin): array
    {
        return [
            ...$this->recipeSummaryPayload($plugin),
            ...array_diff_key($this->recipeDataSettings($plugin), ['data_strategy' => true]),
        ];
    }
}
