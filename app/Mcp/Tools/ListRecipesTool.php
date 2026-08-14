<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\ResolvesUserRecipes;
use App\Models\Plugin;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('List all recipe plugins owned by the authenticated user.')]
#[IsReadOnly]
class ListRecipesTool extends Tool
{
    use ResolvesUserRecipes;

    public function handle(Request $request): Response
    {
        $recipes = $this->userRecipesQuery($request->user())
            ->orderBy('name')
            ->get()
            ->map(fn (Plugin $plugin): array => $this->recipeSummaryPayload($plugin))
            ->values()
            ->all();

        return Response::json(['recipes' => $recipes]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
