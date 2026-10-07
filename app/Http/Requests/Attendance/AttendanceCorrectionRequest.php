<?php

namespace App\Http\Requests\Attendance;

use App\Support\Access;
use Illuminate\Foundation\Http\FormRequest;

class AttendanceCorrectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Access::manager();
    }

    public function rules(): array
    {
        return [
            'status' => 'required|in:present,late,absent,corrected,early_checkout',
            'checkin_at' => 'nullable|date',
            'checkout_at' => 'nullable|date|after_or_equal:checkin_at',
            'reason' => 'required|string|min:10|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'Status kehadiran koreksi wajib dipilih.',
            'status.in' => 'Status kehadiran tidak valid.',
            'checkout_at.after_or_equal' => 'Waktu pulang harus sama atau setelah waktu masuk.',
            'reason.required' => 'Alasan koreksi data presensi wajib diisi.',
            'reason.min' => 'Alasan koreksi minimal 10 karakter.',
        ];
    }
}
