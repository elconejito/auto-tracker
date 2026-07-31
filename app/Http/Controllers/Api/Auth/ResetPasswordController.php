<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Responses\ApiErrorResponse;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class ResetPasswordController extends Controller
{
    public function __invoke(ResetPasswordRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = null;

        $status = Password::reset(
            $validated,
            function (User $resetUser, string $password) use (&$user): void {
                $resetUser->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                $user = $resetUser;
            },
        );

        if ($status !== Password::PASSWORD_RESET || ! $user instanceof User) {
            return ApiErrorResponse::make(
                status: Response::HTTP_UNPROCESSABLE_ENTITY,
                code: 'password_reset_failed',
                detail: 'The password reset request is invalid or has expired.',
            );
        }

        return $this->respondWithUser($user);
    }
}
