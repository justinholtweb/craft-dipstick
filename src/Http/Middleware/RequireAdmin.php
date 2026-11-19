<?php

declare(strict_types=1);

namespace JustinHoltWeb\Dipstick\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

use function CraftCms\Cms\currentUser;

/**
 * Dipstick's pages are for admins only: reports name files and classes in
 * the project. No user permission can open them.
 */
class RequireAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(currentUser()?->isAdmin() === true, 403);

        return $next($request);
    }
}
