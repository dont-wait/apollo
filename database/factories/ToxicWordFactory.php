<?php

namespace Database\Factories;

use App\Models\ToxicWord;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ToxicWord> */
class ToxicWordFactory extends Factory
{
    protected $model = ToxicWord::class;

    public function definition(): array
    {
        $term = fake()->unique()->lexify('badword????');

        return [
            'term' => $term,
            'normalized_term' => $term,
            'scopes' => ['post', 'tag', 'category', 'comment', 'user_name'],
            'match_mode' => ToxicWord::MATCH_WORD,
            'fold_diacritics' => false,
            'is_active' => true,
            'note' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }

    public function loose(): static
    {
        return $this->state(fn (): array => ['match_mode' => ToxicWord::MATCH_LOOSE]);
    }

    public function folded(): static
    {
        return $this->state(fn (): array => ['fold_diacritics' => true]);
    }
}
