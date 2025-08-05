<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth as Author;

class AdminAuth extends Auth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $res =  parent::handle($request, $next);
        $user = Author::user();
        if ($user->type != 'admin') {
            throw new \Exception("权限不足", 401);
        }
        return $res;
    }
}
