<?php

namespace App\Http\Requests\Admin;

use Illuminate\Validation\Rule;

class UpdateAiSourceRequest extends StoreAiSourceRequest
{
    public function rules(): array
    {
        $rules = parent::rules();
        $rules['name'] = ['required', 'string', 'max:150', Rule::unique('ai_sources', 'name')->ignore($this->route('source'))];

        return $rules;
    }
}
