<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth as Author;

class SellerAuth extends Auth
{
    public function handle(Request $request, Closure $next): Response
    {
        $this->check();
        $user = Author::user();
        if (!in_array($user->type, ['seller', 'store'])) {
            return response()->json(['code' => 401,'message' => '权限不足'])->setStatusCode(401);
        }
        $store_id =   \App\Classes\Store::getId();
        return $next($request);
    }
}
