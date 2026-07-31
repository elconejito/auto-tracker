<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiErrorResponse;
use Illuminate\Http\JsonResponse;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;
use PHPOpenSourceSaver\JWTAuth\JWT;
use Symfony\Component\HttpFoundation\Response;

final class RefreshController extends Controller
{
    public function __invoke(): JsonResponse
    {
        try {
            $guard = auth('api');
            $token = $guard->refresh();
            $user = $guard->setToken($token)->userOrFail();
            app(JWT::class)->unsetToken();
        } catch (JWTException) {
            return $this->forgetAuthenticationCookies(ApiErrorResponse::make(
                status: Response::HTTP_UNAUTHORIZED,
                code: 'refresh_failed',
                detail: 'Your authentication session has expired. Please sign in again.',
            ));
        }

        return $this->withAuthenticationCookies(
            $this->respondWithUser($user),
            $token,
        );
    }
}
