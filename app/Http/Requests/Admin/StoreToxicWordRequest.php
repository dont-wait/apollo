<?php

namespace App\Http\Requests\Admin;

use App\Models\ToxicWord;
use App\Support\Moderation\ToxicWordNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreToxicWordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('term'))) {
            $this->merge(['term' => trim($this->input('term'))]);
        }
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'term' => ['required', 'string', 'min:2', 'max:100'],
            'scopes' => ['required', 'array', 'min:1'],
            'scopes.*' => ['string', Rule::in(config('toxic_words.scopes'))],
            'match_mode' => ['required', Rule::in(ToxicWord::MATCH_MODES)],
            'fold_diacritics' => ['boolean'],
            'is_active' => ['boolean'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $normalizer = app(ToxicWordNormalizer::class);
                $normalized = $normalizer->normalize((string) $this->input('term'));

                if ($normalized === '') {
                    $validator->errors()->add('term', 'Từ không hợp lệ.');

                    return;
                }

                if (! preg_match('/[\p{L}\p{N}]/u', $normalized)) {
                    $validator->errors()->add('term', 'Từ phải chứa ít nhất một chữ cái hoặc chữ số.');

                    return;
                }

                if (ToxicWord::query()->where('normalized_term', $normalized)->exists()) {
                    $validator->errors()->add('term', 'Từ này đã có trong từ điển.');
                }

                if ($this->input('match_mode') === ToxicWord::MATCH_LOOSE && mb_strlen(preg_replace('/[^\p{L}\p{N}]+/u', '', $normalized) ?? '') < 4) {
                    $validator->errors()->add('match_mode', 'Chế độ LOOSE chỉ áp dụng cho từ hoặc cụm từ từ 4 ký tự.');
                }
            },
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'term.required' => 'Nhập từ hoặc cụm từ cần chặn.',
            'term.min' => 'Từ phải có ít nhất 2 ký tự.',
            'term.max' => 'Từ không được dài quá 100 ký tự.',
            'scopes.required' => 'Chọn ít nhất một vị trí áp dụng.',
            'match_mode.required' => 'Chọn chế độ so khớp.',
        ];
    }
}
