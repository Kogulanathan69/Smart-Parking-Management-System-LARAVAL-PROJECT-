<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Routing\Router;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Read-only permission matrix generated from the live route table. Permissions are fixed in code
 * (route `role:` middleware), so this page reports them; it cannot and does not edit them.
 */
class RoleMatrixController extends Controller
{
    public function __invoke(Router $router): View
    {
        $rows = [];
        foreach ($router->getRoutes() as $route) {
            $uri = $route->uri();
            if (! str_starts_with($uri, 'api/v1/') || str_starts_with($uri, 'api/v1/anpr/device')) {
                continue;
            }
            $roles = null;
            foreach ($route->gatherMiddleware() as $m) {
                if (is_string($m) && str_starts_with($m, 'role:')) {
                    $roles = explode(',', substr($m, 5));
                }
            }
            $methods = array_values(array_diff($route->methods(), ['HEAD']));
            $parts = explode('/', substr($uri, 7));
            $rows[] = [
                'area' => Str::headline($parts[0]),
                'endpoint' => implode('|', $methods).' /'.$uri,
                'writes' => (bool) array_diff($methods, ['GET']),
                'roles' => $roles ?? User::ROLES, // no role middleware = any signed-in role
                'any' => $roles === null,
            ];
        }
        usort($rows, fn ($a, $b) => [$a['area'], $a['endpoint']] <=> [$b['area'], $b['endpoint']]);

        return view('pages.manage.roles', ['rows' => $rows, 'roles' => User::ROLES]);
    }
}
