<?php

namespace App\Http\Requests\Leave;

use App\Support\Access;
use Illuminate\Foundation\Http\FormRequest;

class LeaveReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Access::manager();
    }

    public function rules(): array
    {
        return [
            'approved_dates' => 'nullable|array',
            'approved_dates.*' => 'date',
            'paid_dates' => 'nullable|array',
            'paid_dates.*' => 'date',
            'review_note' => 'required|string|min:5|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'review_note.required' => 'Catatan peninjauan izin wajib diisi.',
            'review_note.min' => 'Catatan peninjauan minimal 5 karakter.',
        ];
    }
}
