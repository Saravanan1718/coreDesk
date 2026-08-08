<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // owner mode
    }

    public function rules(): array
    {
        return [
            // All fields are optional on PATCH — only supplied fields are validated
            'full_name' => ['sometimes', 'string', 'max:100'],

            'date_of_birth' => ['sometimes', 'date', 'before:today'],

            'gender' => ['sometimes', 'string', Rule::in(['male', 'female', 'other'])],

            'phone' => [
                'sometimes',
                'string',
                'max:15',
                'regex:/^\+?[0-9]{7,15}$/',
            ],

            'emergency_contact_name'  => ['sometimes', 'string', 'max:100'],
            'emergency_contact_phone' => ['sometimes', 'string', 'max:15', 'regex:/^\+?[0-9]{7,15}$/'],

            // Photo replacement (Req 3.10)
            'photo' => [
                'nullable',
                'file',
                'mimes:jpeg,jpg,png',
                'max:5120',
            ],

            // Explicit duplicate phone confirmation (Req 3.9)
            'confirm_duplicate' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.regex'                   => 'Phone must be 7–15 digits, optionally prefixed with +.',
            'emergency_contact_phone.regex' => 'Emergency contact phone must be 7–15 digits, optionally prefixed with +.',
            'photo.mimes'                   => 'Profile photo must be a JPEG or PNG file.',
            'photo.max'                     => 'Profile photo must not exceed 5 MB.',
            'date_of_birth.before'          => 'Date of birth must be in the past.',
        ];
    }
}
