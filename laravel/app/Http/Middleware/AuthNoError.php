<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\User;
use Illuminate\Support\Facades\Auth as Author;
use Laravel\Sanctum\Sanctum;

class AuthNoError
{
    public function handle(Request $request, Closure $next): Response
    {
        $this->check();
        return $next($request);
    }

    protected function check()
    {
        $user = Author::guard('sanctum')->user();
        if ($user && $user->tokenCan('server:update')) {
            Author::loginUsingId($user->id);
        }
    }
}
