<?php

namespace App\AiWeekly;

use RuntimeException;

class SourceRequestException extends RuntimeException
{
    public function __construct(public readonly bool $retryable, public readonly ?int $retryAfterSeconds = null, public readonly ?int $statusCode = null)
    {
        parent::__construct('Source request failed.');
    }
}
