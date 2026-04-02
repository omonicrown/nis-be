<?php

namespace App\Concerns;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

trait UseCustomResponse
{
    /**
     * @param  string  $message
     * @param  mixed  $data
     * @param  int  $statusCode
     * @return JsonResponse
     */
    public function success(string $message, $data = null, int $statusCode = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $data,
            'message' => $message,
        ], $statusCode);
    }

    /**
     * @param  Exception  $exception
     * @return JsonResponse
     */
    public function exception(Exception $exception): JsonResponse
    {
        // if (config('app.debug') === false) {
        //     return $this->error('Server Error');
        // }

        if (app()->isProduction()) {
            Log::error($exception);
        }

        return $this->error($exception->getMessage(), 500, [
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            // 'trace' => $exception->getTrace(),
        ]);
    }

    /**
     * @param  string  $message
     * @param  int  $code
     * @param  array  $errors
     * @param  array  $errorData
     * @return JsonResponse
     */
    public function error(string $message, int $code = 500, array $errors = [], array $errorData = []): JsonResponse
    {
        $data = [
            'success' => false,
            'message' => $message,
        ];

        if (count($errors)) {
            $data['errors'] = $errors;
        }

        if (count($errorData)) {
            $data['data'] = $errorData;
        }

        return response()->json($data, $code);
    }



    /**
     * @param  array  $errors
     * @param  int  $code
     * @param  string  $message
     * @return JsonResponse
     */
    public function formErrors(array $errors, int $code = 422, string $message = 'Invalid form data'): JsonResponse
    {
        return $this->error($message, $code, $errors);
    }

    /**
     * @param  callable  $callback
     * @return JsonResponse
     */
    public function withExceptionHandling(callable $callback): JsonResponse
    {
        try {
            return $callback();
        } catch (Exception $e) {
            return $this->exception($e);
        }
    }
}
