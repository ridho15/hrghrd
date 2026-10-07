<?php

namespace App\Http\Requests\Admin;

use App\Support\Access;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;

class ShiftUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (Access::admin()) {
            return true;
        }

        if (Access::manager()) {
            $shiftId = (int) $this->route('id');
            $shiftBranchId = (int) DB::table('shifts')->where('id', $shiftId)->value('branch_id');
            return Access::branch($shiftBranchId);
        }

        return false;
    }

    public function rules(): array
    {
        return [
            'date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i',
            'reason' => 'required|string|min:5|max:500',
            'expected_version' => 'nullable|integer',
        ];
    }

    public function messages(): array
    {
        return [
            'date.required' => 'Tanggal shift wajib diisi.',
            'start_time.required' => 'Jam mulai shift wajib diisi.',
            'start_time.date_format' => 'Format jam mulai harus JJ:MM.',
            'end_time.required' => 'Jam selesai shift wajib diisi.',
            'end_time.date_format' => 'Format jam selesai harus JJ:MM.',
            'reason.required' => 'Alasan revisi jadwal shift wajib diisi.',
            'reason.min' => 'Alasan revisi minimal 5 karakter.',
            'reason.max' => 'Alasan revisi maksimal 500 karakter.',
        ];
    }
}
