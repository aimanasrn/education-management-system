<?php

namespace App\Http\Requests;

use App\FoundationAccess;
use Illuminate\Foundation\Http\FormRequest;

class SaveFoundationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return FoundationAccess::canWrite($this->user(), $this->route('resource'), $this->isMethod('POST'));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('code'))) {
            $this->merge(['code' => strtoupper(trim($this->input('code')))]);
        }
    }
}
