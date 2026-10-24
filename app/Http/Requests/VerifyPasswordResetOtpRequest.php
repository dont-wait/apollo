<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VerifyPasswordResetOtpRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return (bool) (
            $this->session()->get('password_reset.challenge')
            || $this->session()->get('password_reset.flow_id')
            || $this->session()->get('password_change.flow_id')
        );
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ['otp' => ['required', 'digits:6']];
    }
}
