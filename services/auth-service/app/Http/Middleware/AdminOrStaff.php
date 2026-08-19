<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;

class AdminOrStaff extends RoleMiddleware
{

    // Xử lý dữ liệu theo nghiệp vụ của hàm.
    public function handle(Request $request, Closure $next): mixed
    {
        return $this->authorize($request, $next, [
            User::ROLE_ADMIN,
            User::ROLE_STAFF,
        ]);
    }
}
