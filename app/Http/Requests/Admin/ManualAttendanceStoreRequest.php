<?php

namespace App\Http\Requests\Admin;

use App\Support\Access;
use Illuminate\Foundation\Http\FormRequest;

class ManualAttendanceStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Access::admin();
    }

    public function rules(): array
    {
        return [
            'status' => 'required|in:present,late,absent,early_checkout,corrected',
            'checkin_at' => 'nullable|date',
            'checkout_at' => 'nullable|date|after_or_equal:checkin_at',
            'reason' => 'required|string|min:5|max:1000',
            'force_override_period' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'Status kehadiran wajib dipilih.',
            'status.in' => 'Status kehadiran tidak valid.',
            'checkout_at.after_or_equal' => 'Waktu pulang harus sama atau setelah waktu masuk.',
            'reason.required' => 'Alasan entri presensi manual wajib diisi.',
            'reason.min' => 'Alasan entri manual minimal 5 karakter.',
        ];
    }
}
