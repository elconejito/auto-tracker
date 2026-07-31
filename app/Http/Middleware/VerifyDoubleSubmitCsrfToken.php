<?php

namespace App\Http\Middleware;

use App\Http\Responses\ApiErrorResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyDoubleSubmitCsrfToken
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return $next($request);
        }

        $cookieToken = $request->cookie(config('jwt.csrf.cookie_name'));
        $headerToken = $request->header(config('jwt.csrf.header_name'));

        if (! is_string($cookieToken) || ! is_string($headerToken) || ! hash_equals($cookieToken, $headerToken)) {
            return ApiErrorResponse::make(
                status: 419,
                code: 'csrf_token_mismatch',
                detail: 'The CSRF token is missing or invalid.',
            );
        }

        return $next($request);
    }
}
