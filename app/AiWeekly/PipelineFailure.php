<?php

namespace App\AiWeekly;

use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class PipelineFailure
{
    public static function message(Throwable $exception): string
    {
        $reason = match (true) {
            $exception instanceof ValidationException => 'Invalid fields: '.implode(', ', array_keys($exception->errors())),
            $exception::class === RuntimeException::class => $exception->getMessage(),
            default => 'Operation failed; inspect the exception type for the failing dependency.',
        };

        return mb_substr($exception::class.': '.$reason, 0, 2000);
    }
}
