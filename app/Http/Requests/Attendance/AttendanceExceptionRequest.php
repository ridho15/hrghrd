<?php

namespace App\Http\Requests\Attendance;

use Illuminate\Foundation\Http\FormRequest;

class AttendanceExceptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && (bool) auth()->user()->active;
    }

    public function rules(): array
    {
        return [
            'action' => 'required|in:in,out',
            'claimed_time' => 'nullable|date_format:H:i',
            'reason' => 'required|string|min:10|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'action.required' => 'Aksi presensi wajib ditentukan.',
            'action.in' => 'Tindakan presensi tidak valid.',
            'claimed_time.date_format' => 'Format jam kehadiran klaim harus JJ:MM (contoh: 08:30).',
            'reason.required' => 'Alasan kendala presensi wajib diisi.',
            'reason.min' => 'Alasan kendala minimal 10 karakter.',
            'reason.max' => 'Alasan kendala maksimal 1.000 karakter.',
        ];
    }
}
