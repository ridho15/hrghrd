<?php

namespace App\Http\Requests\Admin;

use App\Support\Access;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;

class ShiftStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Access::admin();
    }

    public function rules(): array
    {
        return [
            'user_id' => 'required|exists:users,id',
            'branch_id' => 'required|exists:branches,id',
            'date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $userBranch = DB::table('users')->where('id', $this->input('user_id'))->value('branch_id');
            if ($userBranch && (int) $userBranch !== (int) $this->input('branch_id')) {
                $validator->errors()->add('shift', 'Karyawan harus berada di cabang shift.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'user_id.required' => 'Karyawan wajib dipilih.',
            'user_id.exists' => 'Karyawan tidak ditemukan.',
            'branch_id.required' => 'Cabang wajib dipilih.',
            'branch_id.exists' => 'Cabang tidak ditemukan.',
            'date.required' => 'Tanggal shift wajib diisi.',
            'start_time.required' => 'Jam mulai shift wajib diisi.',
            'start_time.date_format' => 'Format jam mulai harus JJ:MM (contoh: 09:00).',
            'end_time.required' => 'Jam selesai shift wajib diisi.',
            'end_time.date_format' => 'Format jam selesai harus JJ:MM (contoh: 17:00).',
        ];
    }
}
