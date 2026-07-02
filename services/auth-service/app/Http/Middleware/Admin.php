<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;

class Admin extends RoleMiddleware
{
    public function handle(Request $request, Closure $next): mixed
    {
        return $this->authorize($request, $next, [User::ROLE_ADMIN]);
    }
}
