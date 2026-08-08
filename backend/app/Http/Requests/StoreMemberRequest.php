<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Auth is not implemented yet — always allow (owner mode)
        return true;
    }

    public function rules(): array
    {
        $gymId = $this->route('gymId') ?? 1; // default gym_id=1 in owner mode

        return [
            'full_name' => ['required', 'string', 'max:100'],

            'date_of_birth' => ['required', 'date', 'before:today'],

            'gender' => ['required', 'string', Rule::in(['male', 'female', 'other'])],

            // Max 15 digits, no spaces/dashes enforced at DB level
            'phone' => [
                'required',
                'string',
                'max:15',
                'regex:/^\+?[0-9]{7,15}$/',
                // Duplicate phone within the same gym triggers a warning (Req 3.9).
                // Uniqueness is a *soft* check — confirmed via a separate flag.
                // Hard unique constraint is NOT applied here; MemberService handles it.
            ],

            'emergency_contact_name'  => ['required', 'string', 'max:100'],
            'emergency_contact_phone' => ['required', 'string', 'max:15', 'regex:/^\+?[0-9]{7,15}$/'],

            // Photo is optional at creation time (Req 3.3)
            'photo' => [
                'nullable',
                'file',
                'mimes:jpeg,jpg,png',
                'max:5120', // 5 MB in kilobytes
            ],

            'registration_date' => ['nullable', 'date'],

            // Explicit confirmation that the caller accepts a duplicate phone (Req 3.9)
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
