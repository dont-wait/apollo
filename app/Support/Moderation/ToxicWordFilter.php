<?php

namespace App\Support\Moderation;

use Throwable;

class ToxicWordFilter
{
    public function __construct(private ToxicWordMatcher $matcher) {}

    /** @return list<ToxicWordMatch> */
    public function find(string $text, string $scope): array
    {
        try {
            return $this->matcher->find($text, $scope);
        } catch (Throwable $exception) {
            report($exception);

            return [ToxicWordMatch::failure()];
        }
    }

    public function passes(string $text, string $scope): bool
    {
        return $this->find($text, $scope) === [];
    }
}
