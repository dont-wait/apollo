<?php

namespace App\Support\Moderation;

use Illuminate\Contracts\Support\Arrayable;

/** @implements Arrayable<string, int|string|null> */
class ToxicWordMatch implements Arrayable
{
    public function __construct(
        public readonly int $termId,
        public readonly string $matched,
        public readonly ?int $start = null,
        public readonly ?int $end = null,
    ) {}

    /** @return array{term_id: int, matched: string, start: int|null, end: int|null} */
    public function toArray(): array
    {
        return [
            'term_id' => $this->termId,
            'matched' => $this->matched,
            'start' => $this->start,
            'end' => $this->end,
        ];
    }
}
