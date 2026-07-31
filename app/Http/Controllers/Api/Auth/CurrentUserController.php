<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CurrentUserController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        return $this->respondWithUser($request->user('api'));
    }
}
