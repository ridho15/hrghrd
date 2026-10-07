<?php

namespace App\Http\Requests\Auth;

use App\Support\Access;
use Illuminate\Foundation\Http\FormRequest;

class PasswordUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (! auth()->check()) {
            return false;
        }

        $targetId = (int) ($this->input('target_user_id') ?: auth()->id());
        if ($targetId !== auth()->id() && ! Access::admin()) {
            return false;
        }

        return true;
    }

    public function rules(): array
    {
        $targetId = (int) ($this->input('target_user_id') ?: auth()->id());
        $isSelf = $targetId === auth()->id();

        return [
            'target_user_id' => 'nullable|exists:users,id',
            'current_password' => $isSelf ? ['required', 'current_password'] : ['nullable'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    public function messages(): array
    {
        return [
            'current_password.required' => 'Kata sandi saat ini wajib diisi.',
            'current_password.current_password' => 'Kata sandi saat ini tidak cocok.',
            'password.required' => 'Kata sandi baru wajib diisi.',
            'password.min' => 'Kata sandi baru minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi kata sandi baru tidak cocok.',
        ];
    }
}
