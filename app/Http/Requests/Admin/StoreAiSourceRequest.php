<?php

namespace App\Http\Requests\Admin;

use App\AiWeekly\SourceFetcher;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use RuntimeException;

class StoreAiSourceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150', Rule::unique('ai_sources', 'name')],
            'source_type' => ['required', Rule::in(['RSS', 'API', 'WEBSITE'])],
            'site_url' => ['required', 'url:http,https', 'max:500', $this->publicUrlRule()],
            'feed_url' => [Rule::requiredIf(in_array($this->input('source_type'), ['RSS', 'API'], true)), 'nullable', 'url:http,https', 'max:1000', $this->publicUrlRule()],
            'adapter_key' => [Rule::requiredIf($this->input('source_type') === 'API'), 'nullable', Rule::in(array_keys(config('ai_weekly.api_adapters')))],
            'trust_score' => ['required', 'integer', 'between:1,10'],
            'priority' => ['required', 'integer', 'between:1,10'],
            'status' => ['required', Rule::in(['ACTIVE', 'INACTIVE'])],
        ];
    }

    protected function publicUrlRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_string($value) || ! filter_var($value, FILTER_VALIDATE_URL)) {
                return;
            }

            try {
                app(SourceFetcher::class)->publicIp($value);
            } catch (RuntimeException) {
                $fail('URL phải trỏ tới địa chỉ công khai.');
            }
        };
    }
}
