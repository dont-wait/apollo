<?php

namespace App\Http\Requests\Concerns;

use App\Support\Moderation\ToxicContentInspector;
use Illuminate\Contracts\Validation\Validator;

trait ValidatesToxicContent
{
    /** @return array<string, array{scope: string, label: string, markdown?: bool}> */
    abstract protected function toxicContentFields(): array;

    protected function toxicContentWithModeration(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $exception = app(ToxicContentInspector::class)->inspect(
                    $this->all(),
                    $this->toxicContentFields(),
                    $this->toxicContentWithModeration(),
                );

                if ($exception !== null) {
                    throw $exception;
                }
            },
        ];
    }
}
