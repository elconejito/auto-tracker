<?php

namespace App\Http\Controllers;

use App\Transformers\UserTransformer;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use League\Fractal\TransformerAbstract;
use Symfony\Component\HttpFoundation\Cookie as SymfonyCookie;

abstract class Controller
{
    protected function respondWithItem(
        mixed $resource,
        TransformerAbstract $transformer,
        int $status = 200,
    ): JsonResponse {
        return fractal($resource, $transformer)
            ->item($resource)
            ->respond($status);
    }

    protected function respondWithUser(
        mixed $user,
        int $status = 200,
    ): JsonResponse {
        return $this->respondWithItem($user, new UserTransformer, $status);
    }

    protected function withAuthenticationCookies(JsonResponse $response, string $token): JsonResponse
    {
        return $response
            ->withCookie($this->jwtCookie($token))
            ->withCookie(self::makeCsrfCookie());
    }

    protected function forgetAuthenticationCookies(JsonResponse $response): JsonResponse
    {
        $path = config('jwt.cookies.path');
        $domain = config('jwt.cookies.domain');

        return $response
            ->withCookie(Cookie::forget(config('jwt.cookie_key_name'), $path, $domain))
            ->withCookie(Cookie::forget(config('jwt.csrf.cookie_name'), $path, $domain));
    }

    public static function makeCsrfCookie(): SymfonyCookie
    {
        return Cookie::make(
            name: config('jwt.csrf.cookie_name'),
            value: Str::random(64),
            minutes: config('jwt.refresh_ttl'),
            path: config('jwt.cookies.path'),
            domain: config('jwt.cookies.domain'),
            secure: config('jwt.cookies.secure'),
            httpOnly: false,
            sameSite: config('jwt.cookies.same_site'),
        );
    }

    private function jwtCookie(string $token): SymfonyCookie
    {
        return Cookie::make(
            name: config('jwt.cookie_key_name'),
            value: $token,
            minutes: config('jwt.refresh_ttl'),
            path: config('jwt.cookies.path'),
            domain: config('jwt.cookies.domain'),
            secure: config('jwt.cookies.secure'),
            httpOnly: true,
            sameSite: config('jwt.cookies.same_site'),
        );
    }
}
