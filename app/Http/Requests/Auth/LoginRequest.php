<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'login' => 'required_without:email|nullable|string',
            'email' => 'nullable|string',
            'password' => 'required|string',
        ];
    }

    public function messages(): array
    {
        return [
            'login.required_without' => 'Username atau email wajib diisi.',
            'password.required' => 'Kata sandi wajib diisi.',
        ];
    }
}
