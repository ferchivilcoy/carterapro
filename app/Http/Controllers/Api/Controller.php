<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller as BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

abstract class Controller extends BaseController
{
    /**
     * Respuesta de éxito consistente para la API.
     */
    protected function success(mixed $data = null, string $message = '', int $code = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $code);
    }

    /**
     * Respuesta de error consistente para la API.
     */
    protected function error(string $message, int $code = 400, mixed $errors = null): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], $code);
    }

    /**
     * Convierte una ValidationException en respuesta JSON consistente.
     */
    protected function invalid(ValidationException $e): JsonResponse
    {
        return $this->error(
            'Error de validación',
            422,
            $e->errors(),
        );
    }
}
