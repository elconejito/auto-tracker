<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Responses\ApiErrorResponse;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class LoginController extends Controller
{
    public function __invoke(LoginRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $token = auth('api')->attempt([
            'email' => strtolower($validated['email']),
            'password' => $validated['password'],
        ]);

        if (! is_string($token)) {
            return ApiErrorResponse::make(
                status: Response::HTTP_UNPROCESSABLE_ENTITY,
                code: 'invalid_credentials',
                detail: 'The provided credentials are incorrect.',
            );
        }

        return $this->withAuthenticationCookies(
            $this->respondWithUser(auth('api')->userOrFail()),
            $token,
        );
    }
}
