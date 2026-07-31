<?php

use App\Http\Middleware\VerifyDoubleSubmitCsrfToken;
use App\Http\Responses\ApiErrorResponse;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: [
            'autotracker_access_token',
            'XSRF-TOKEN',
        ]);

        $middleware->alias([
            'csrf.double-submit' => VerifyDoubleSubmitCsrfToken::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        $exceptions->render(
            fn (ValidationException $exception, Request $request) => $request->is('api/*')
                ? ApiErrorResponse::validation($exception)
                : null,
        );

        $exceptions->respond(function (Response $response): Response {
            if (! request()->is('api/*')) {
                return $response;
            }

            if ($response->getStatusCode() < Response::HTTP_BAD_REQUEST) {
                return $response;
            }

            if ($response instanceof JsonResponse) {
                $payload = $response->getData(true);

                if (isset($payload['errors']) && is_array($payload['errors'])) {
                    return $response;
                }
            }

            return ApiErrorResponse::fromResponse($response);
        });
    })
    ->create();
