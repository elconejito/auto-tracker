<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Password;

final class ForgotPasswordController extends Controller
{
    public function __invoke(ForgotPasswordRequest $request): Response
    {
        Password::sendResetLink($request->safe()->only('email'));

        return response()->noContent(202);
    }
}
