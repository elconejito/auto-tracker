<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class LogoutController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user('api');

        auth('api')->logout();

        return $this->forgetAuthenticationCookies($this->respondWithUser($user));
    }
}
