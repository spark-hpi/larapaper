<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\ResolvesUserRecipes;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Get full details for a recipe plugin, including markup, render settings, data strategy, and data payload.')]
#[IsReadOnly]
class GetRecipeTool extends Tool
{
    use ResolvesUserRecipes;

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'id' => 'required|integer',
        ]);

        $plugin = $this->findUserRecipeOrFail($request, $validated['id']);

        if ($plugin instanceof Response) {
            return $plugin;
        }

        return Response::json($this->recipeDetailPayload($plugin));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('The recipe ID.')->required(),
        ];
    }
}
