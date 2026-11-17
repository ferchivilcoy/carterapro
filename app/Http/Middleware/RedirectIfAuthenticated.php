<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\RedirectIfAuthenticated as BaseRedirectIfAuthenticated;
use Illuminate\Http\Request;

/**
 * Same contract as the framework middleware, but the destination is the
 * home of the authenticated account (User::homeRoute) so a Cliente never
 * gets bounced to the dashboard and its 403.
 */
class RedirectIfAuthenticated extends BaseRedirectIfAuthenticated
{
    protected function redirectTo(Request $request): ?string
    {
        return $request->user()?->homeRoute() ?? parent::redirectTo($request);
    }
}
