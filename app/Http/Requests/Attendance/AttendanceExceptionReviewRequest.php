<?php

namespace App\Http\Requests\Attendance;

use App\Support\Access;
use Illuminate\Foundation\Http\FormRequest;

class AttendanceExceptionReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Access::manager();
    }

    public function rules(): array
    {
        return [
            'decision' => 'required|in:approved,rejected',
            'review_note' => 'required|string|min:5|max:1000',
            'actual_time' => 'nullable|date_format:H:i',
        ];
    }

    public function messages(): array
    {
        return [
            'decision.required' => 'Keputusan persetujuan wajib ditentukan.',
            'decision.in' => 'Keputusan harus disetujui atau ditolak.',
            'review_note.required' => 'Catatan peninjauan wajib diisi.',
            'review_note.min' => 'Catatan peninjauan minimal 5 karakter.',
            'actual_time.date_format' => 'Format jam aktual harus JJ:MM (contoh: 08:00).',
        ];
    }
}
