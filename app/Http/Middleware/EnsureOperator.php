<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureOperator
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless(optional($request->user())->isOperator(), 403, 'Operator access only.');

        return $next($request);
    }
}
