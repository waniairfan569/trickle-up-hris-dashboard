<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** Owner-only operator actions (plans, pricing, suspend/cancel, managing operators). */
class EnsureOperatorOwner
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless(optional($request->user())->isOperatorOwner(), 403, 'Platform owner access only.');

        return $next($request);
    }
}
