<?php

namespace App\Supports;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;

class TheOneResponse implements Responsable
{
    protected int $httpCode;

    protected array $data;

    protected string $errorMessage;

    protected ?string $inertiaView;

    protected ?string $redirectRoute;

    protected ?string $redirectParam;

    public function __construct(
        int $httpCode,
        array $data = [],
        string $errorMessage = '',
        ?string $inertiaView = null,
        ?string $redirectRoute = null,
        ?string $redirectParam = null
    ) {
        $this->httpCode = $httpCode;
        $this->data = $data;
        $this->errorMessage = $errorMessage;
        $this->inertiaView = $inertiaView;
        $this->redirectRoute = $redirectRoute;
        $this->redirectParam = $redirectParam;
    }

    public static function toApiResponse($code, $data)
    {
        $message = $data['message'] ?? 'ok';

        $payload = match ($code) {
            $code >= 500 => ['message' => 'Server error'],
            $code >= 400 => ['message' => $message],
            $code >= 200 => $data,
            default => $data,
        };

        return response()->json(
            data: $payload,
            status: $code,
            options: JSON_UNESCAPED_UNICODE
        );
    }

    public function toResponse($request)
    {
        if ($request->expectsJson()) {
            return $this->toJsonResponse();
        }

        if ($this->inertiaView) {
            return $this->toInertiaResponse();
        }

        if ($this->redirectRoute) {
            return $this->toRedirectResponse();
        }

        return $this->toJsonResponse();
    }

    protected function toJsonResponse(): JsonResponse
    {
        $payload = match (true) {
            $this->httpCode >= 500 => ['message' => 'Server error'],
            $this->httpCode >= 400 => ['message' => $this->errorMessage],
            $this->httpCode >= 200 => $this->data,
        };

        return response()->json(
            data: $payload,
            status: $this->httpCode,
            options: JSON_UNESCAPED_UNICODE
        );
    }

    protected function toInertiaResponse()
    {
        return Inertia::render($this->inertiaView, $this->data);
    }

    protected function toRedirectResponse()
    {
        $redirect = redirect()->route($this->redirectRoute, $this->redirectParam);

        if (isset($this->data['message'])) {
            $redirect->with('message', $this->data['message']);
        }

        return $redirect;
    }

    public static function ok(array $data, ?string $inertiaView = null, ?string $redirectRoute = null, ?string $redirectParam = null)
    {
        return new static(200, $data, inertiaView: $inertiaView, redirectRoute: $redirectRoute, redirectParam: $redirectParam);
    }

    public static function created(array $data, ?string $inertiaView = null, ?string $redirectRoute = null)
    {
        return new static(201, $data, inertiaView: $inertiaView, redirectRoute: $redirectRoute);
    }

    public static function notFound(string $errorMessage = 'Item not found', ?string $redirectRoute = null)
    {
        Inertia::flash('toast', ['type' => 'error', 'message' => __($errorMessage)]);

        return new static(404, errorMessage: $errorMessage, redirectRoute: $redirectRoute);
    }

    public static function unprocessable(string $errorMessage = 'Resource Invalid', ?string $redirectRoute = null)
    {
        return new static(422, errorMessage: $errorMessage, redirectRoute: $redirectRoute);
    }
}
