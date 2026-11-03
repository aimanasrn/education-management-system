<?php

namespace App\Http\Requests;

use App\Models\Student;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SaveStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $values = [];
        if (is_string($this->input('student_number'))) {
            $values['student_number'] = Str::upper(trim($this->input('student_number')));
        }
        if (is_string($this->input('email'))) {
            $values['email'] = Str::lower(trim($this->input('email')));
        }
        $this->merge($values);
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        $student = $this->route('student');

        return [
            'student_number' => [
                'required', 'string', 'max:30', 'regex:/\A[A-Z0-9][A-Z0-9_-]*\z/',
                Rule::unique('students', 'student_number')->ignore($student instanceof Student ? $student->id : null),
            ],
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'email', 'max:254'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/\A[0-9+().\s-]+\z/'],
            'enrollment_date' => ['required', 'date_format:Y-m-d'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'student_number.unique' => 'This student number is already in use, including archived records.',
            'student_number.regex' => 'Use letters, numbers, hyphens, or underscores for the student number.',
            'phone.regex' => 'Enter a valid phone number.',
        ];
    }
}
