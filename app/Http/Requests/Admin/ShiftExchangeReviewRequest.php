<?php

namespace App\Http\Requests\Admin;

use App\Support\Access;
use Illuminate\Foundation\Http\FormRequest;

class ShiftExchangeReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Access::manager();
    }

    public function rules(): array
    {
        return [
            'decision' => 'required|in:approve,reject',
            'note'     => 'nullable|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'decision.required' => 'Keputusan manajerial (setujui/tolak) wajib dipilih.',
            'decision.in'       => 'Keputusan harus berupa approve atau reject.',
            'note.max'          => 'Catatan maksimal 500 karakter.',
        ];
    }
}
