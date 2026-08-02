<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\ResolvesUserRecipes;
use Exception;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Keepsuit\Liquid\Exceptions\LiquidException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Render a recipe for a given layout size. Returns the rendered HTML on success, or an error message on failure.')]
#[IsReadOnly]
class RenderRecipeTool extends Tool
{
    use ResolvesUserRecipes;

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'id' => 'required|integer',
            'size' => 'nullable|string|in:full,half_horizontal,half_vertical,quadrant',
        ]);

        $plugin = $this->findUserRecipeOrFail($request, $validated['id']);

        if ($plugin instanceof Response) {
            return $plugin;
        }

        $size = $validated['size'] ?? 'full';

        try {
            $html = $plugin->render($size);

            return Response::json([
                'ok' => true,
                'html' => $html,
            ]);
        } catch (LiquidException $exception) {
            return Response::json([
                'ok' => false,
                'error' => $exception->toLiquidErrorMessage(),
            ]);
        } catch (Exception $exception) {
            return Response::json([
                'ok' => false,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('The recipe ID.')->required(),
            'size' => $schema->string()->description('Layout size to render: full, half_horizontal, half_vertical, or quadrant.'),
        ];
    }
}
