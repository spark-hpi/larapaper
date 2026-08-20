<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use OffloadProject\Toggle\Facades\Toggle;
use Symfony\Component\HttpFoundation\Response;

class EnsureToggleIsActive
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $toggle): Response
    {
        if (! Toggle::active($toggle)) {
            abort(404);
        }

        return $next($request);
    }
}
