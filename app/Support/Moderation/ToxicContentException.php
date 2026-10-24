<?php

namespace App\Support\Moderation;

use DomainException;
use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\MessageBag;

class ToxicContentException extends DomainException implements Responsable, ShouldntReport
{
    /**
     * @param  array<string, list<string>>  $errors
     * @param  array<string, mixed>  $moderation
     */
    public function __construct(
        public readonly array $errors,
        public readonly array $moderation = [],
        public readonly bool $withModeration = true,
        public readonly int $status = 422,
        string $message = 'Nội dung chứa từ ngữ không phù hợp. Vui lòng chỉnh sửa.',
    ) {
        parent::__construct($message);
    }

    /** @param array<string, mixed> $moderation */
    public static function fromMatches(
        array $errors,
        array $moderation,
        bool $withModeration = true,
        int $status = 422,
    ): self {
        $message = collect($errors)->flatten()->first() ?? 'Nội dung chứa từ ngữ không phù hợp. Vui lòng chỉnh sửa.';

        return new self($errors, $moderation, $withModeration, $status, $message);
    }

    public function render(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            $payload = [
                'message' => $this->getMessage(),
                'errors' => $this->errors,
            ];

            if ($this->withModeration) {
                $payload['moderation'] = $this->moderation;
            }

            return response()->json($payload, $this->status);
        }

        $redirect = back()
            ->withInput($request->except([
                '_token',
                'password',
                'password_confirmation',
                'current_password',
            ]))
            ->withErrors(new MessageBag($this->errors));

        if ($this->withModeration) {
            $redirect->with('moderation', $this->moderation);
        }

        return $redirect;
    }

    public function toResponse($request): JsonResponse|RedirectResponse
    {
        return $this->render($request);
    }
}
