<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

final class RegisterController extends Controller
{
    public function __invoke(RegisterRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = User::query()->create([
            'name' => $validated['name'],
            'email' => Str::lower($validated['email']),
            'password' => $validated['password'],
            'timezone' => $validated['timezone'] ?? config('app.timezone'),
        ]);

        $token = auth('api')->login($user);

        return $this->withAuthenticationCookies(
            $this->respondWithUser($user, 201),
            $token,
        );
    }
}
