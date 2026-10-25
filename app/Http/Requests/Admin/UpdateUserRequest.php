<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\ValidatesToxicContent;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    use ValidatesToxicContent;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array{scope: string, label: string}> */
    protected function toxicContentFields(): array
    {
        return [
            'name' => ['scope' => 'user_name', 'label' => 'Tên hiển thị'],
        ];
    }

    public function rules(): array
    {
        $user = $this->route('user');
        $isEditingSelf = $user instanceof User && $this->user()?->is($user);
        $allowedRoles = $isEditingSelf ? [User::ROLE_ADMIN] : User::ROLES;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class, 'email')->ignore($user),
            ],
            'password' => ['nullable', 'string', 'confirmed', Password::min(8)],
            'role' => ['required', Rule::in($allowedRoles)],
            'status' => ['required', Rule::in(User::STATUSES)],
        ];
    }
}
