<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

final class ApiErrorResponse
{
    /**
     * @param  array<string, mixed>  $additional
     */
    public static function make(
        int $status,
        string $code,
        string $detail,
        array $additional = [],
    ): JsonResponse {
        return response()->json([
            'errors' => [
                array_merge([
                    'status' => (string) $status,
                    'code' => $code,
                    'detail' => $detail,
                ], $additional),
            ],
        ], $status);
    }

    public static function validation(ValidationException $exception): JsonResponse
    {
        $errors = [];

        foreach ($exception->errors() as $attribute => $messages) {
            foreach ($messages as $message) {
                $errors[] = [
                    'status' => (string) Response::HTTP_UNPROCESSABLE_ENTITY,
                    'code' => 'validation_failed',
                    'detail' => $message,
                    'source' => [
                        'pointer' => '/data/attributes/'.str_replace('.', '/', $attribute),
                    ],
                ];
            }
        }

        return response()->json(
            ['errors' => $errors],
            Response::HTTP_UNPROCESSABLE_ENTITY,
        );
    }

    public static function fromResponse(Response $response): JsonResponse
    {
        $status = $response->getStatusCode();

        return self::make(
            status: $status,
            code: self::codeForStatus($status),
            detail: self::detailForStatus($status),
        );
    }

    private static function codeForStatus(int $status): string
    {
        return match ($status) {
            Response::HTTP_UNAUTHORIZED => 'unauthenticated',
            Response::HTTP_FORBIDDEN => 'forbidden',
            Response::HTTP_NOT_FOUND => 'not_found',
            Response::HTTP_METHOD_NOT_ALLOWED => 'method_not_allowed',
            Response::HTTP_CONFLICT => 'conflict',
            Response::HTTP_TOO_MANY_REQUESTS => 'rate_limited',
            default => $status >= Response::HTTP_INTERNAL_SERVER_ERROR
                ? 'server_error'
                : 'request_failed',
        };
    }

    private static function detailForStatus(int $status): string
    {
        return match ($status) {
            Response::HTTP_UNAUTHORIZED => 'Authentication is required.',
            Response::HTTP_FORBIDDEN => 'You are not authorized to perform this action.',
            Response::HTTP_NOT_FOUND => 'The requested resource was not found.',
            Response::HTTP_METHOD_NOT_ALLOWED => 'The requested method is not allowed.',
            Response::HTTP_CONFLICT => 'The request conflicts with the current resource state.',
            Response::HTTP_TOO_MANY_REQUESTS => 'Too many requests. Please try again later.',
            default => $status >= Response::HTTP_INTERNAL_SERVER_ERROR
                ? 'An unexpected error occurred.'
                : 'The request could not be completed.',
        };
    }
}
