<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\ResolvesUserRecipes;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Update one or more recipe layout markups (full, half_horizontal, half_vertical, quadrant, shared).')]
class UpdateRecipeMarkupTool extends Tool
{
    use ResolvesUserRecipes;

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'id' => 'required|integer',
            'markup_language' => 'nullable|string|in:blade,liquid',
            'markup' => 'required|array',
            'markup.full' => 'nullable|string',
            'markup.half_horizontal' => 'nullable|string',
            'markup.half_vertical' => 'nullable|string',
            'markup.quadrant' => 'nullable|string',
            'markup.shared' => 'nullable|string',
        ]);

        $plugin = $this->findUserRecipeOrFail($request, $validated['id']);

        if ($plugin instanceof Response) {
            return $plugin;
        }

        $markup = $request->array('markup');
        $updates = [];
        $updatedLayouts = [];

        foreach (self::RECIPE_LAYOUT_COLUMNS as $layout => $column) {
            if (! array_key_exists($layout, $markup)) {
                continue;
            }

            $value = $markup[$layout];
            $updates[$column] = is_string($value) && $value !== '' ? $value : null;
            $updatedLayouts[] = $layout;
        }

        if ($updates === [] && ! isset($validated['markup_language'])) {
            return Response::error('No markup layouts were provided to update.');
        }

        if (isset($validated['markup_language'])) {
            $updates['markup_language'] = $validated['markup_language'];
        }

        $plugin->update($updates);
        $plugin->refresh();

        return Response::json([
            'id' => $plugin->id,
            'url' => $this->recipeBrowserUrl($plugin),
            'markup_language' => $plugin->markup_language,
            'updated_layouts' => $updatedLayouts,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('The recipe ID.')->required(),
            'markup_language' => $schema->string()->description('Markup language: blade (default) or liquid.'),
            'markup' => $schema->object([
                'full' => $schema->string()->description('Full layout markup.'),
                'half_horizontal' => $schema->string()->description('Half horizontal layout markup.'),
                'half_vertical' => $schema->string()->description('Half vertical layout markup.'),
                'quadrant' => $schema->string()->description('Quadrant layout markup.'),
                'shared' => $schema->string()->description('Shared markup prepended to all layouts.'),
            ])->description('Markup content keyed by layout name.')->required(),
        ];
    }
}
