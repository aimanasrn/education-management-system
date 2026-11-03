<?php

namespace App\Http\Requests;

use App\Rules\BcryptPasswordLength;
use App\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveUserAccessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('users.manage');
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:254', Rule::unique('users')->ignore($this->route('id'))],
            'password' => [$this->isMethod('POST') ? 'required' : 'prohibited', 'string', 'min:12', new BcryptPasswordLength],
            'roles' => ['required', 'array', 'min:1', 'max:8'],
            'roles.*' => ['required', 'distinct', Rule::enum(UserRole::class)],
            'campus_ids' => ['present', 'array', 'max:100'],
            'campus_ids.*' => ['required', 'integer', 'distinct', Rule::exists('campuses', 'id')],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
