<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ShiftExchangePeerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'action' => 'required|in:accept,reject',
            'note'   => 'nullable|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'action.required' => 'Keputusan tindakan (terima/tolak) wajib dipilih.',
            'action.in'       => 'Tindakan harus berupa accept atau reject.',
            'note.max'        => 'Catatan maksimal 500 karakter.',
        ];
    }
}
