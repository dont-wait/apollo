<?php

namespace App\Support\Moderation;

class ToxicWordFilter
{
    public function __construct(private ToxicWordMatcher $matcher) {}

    /** @return list<ToxicWordMatch> */
    public function find(string $text, string $scope): array
    {
        return $this->matcher->find($text, $scope);
    }

    public function passes(string $text, string $scope): bool
    {
        return $this->find($text, $scope) === [];
    }
}
