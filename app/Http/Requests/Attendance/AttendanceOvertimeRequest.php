<?php

namespace App\Http\Requests\Attendance;

use App\Support\Access;
use Illuminate\Foundation\Http\FormRequest;

class AttendanceOvertimeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Access::manager();
    }

    public function rules(): array
    {
        return [
            'reason' => 'required|string|min:5|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'Alasan persetujuan lembur wajib diisi.',
            'reason.min' => 'Alasan persetujuan lembur minimal 5 karakter.',
            'reason.max' => 'Alasan persetujuan lembur maksimal 500 karakter.',
        ];
    }
}
