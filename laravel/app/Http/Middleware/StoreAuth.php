<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth as Author;

class StoreAuth extends Auth
{
    public function handle(Request $request, Closure $next): Response
    {
        $this->check();
        $user = Author::user();
        if ($user->type != 'store') {
            return response()->json(['code' => 401,'message' => '权限不足'])->setStatusCode(401);
        }
        return $next($request);
    }
}
