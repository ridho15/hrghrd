<?php

namespace App\Http\Requests\Admin;

use App\Support\Access;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;

class ShiftStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (Access::admin()) {
            return true;
        }

        if (Access::manager()) {
            $branchId = (int) $this->input('branch_id');
            if (! $branchId && $this->filled('user_id')) {
                $userBranch = (int) DB::table('users')->where('id', $this->input('user_id'))->value('branch_id');
                return Access::branch($userBranch);
            }
            return Access::branch($branchId);
        }

        return false;
    }

    protected function prepareForValidation(): void
    {
        $mode = $this->input('mode') ?: ($this->has('start_date') ? 'recurring' : 'single');

        if ($mode === 'single' && $this->filled('user_id')) {
            $userBranch = DB::table('users')->where('id', $this->input('user_id'))->value('branch_id');
            if ($userBranch) {
                $this->merge([
                    'branch_id' => (int) $userBranch,
                ]);
            }
        }
    }

    public function rules(): array
    {
        $mode = $this->input('mode');
        if (! $mode) {
            $mode = $this->has('start_date') ? 'recurring' : 'single';
        }

        $base = [
            'mode'        => 'nullable|in:single,recurring',
            'branch_id'   => 'required|exists:branches,id',
            'start_time'  => 'required|date_format:H:i',
            'end_time'    => 'required|date_format:H:i',
        ];

        if ($mode === 'recurring') {
            return array_merge($base, [
                'start_date'        => 'required|date',
                'end_date'          => 'required|date|after_or_equal:start_date',
                'days_of_week'      => 'required|array|min:1',
                'days_of_week.*'    => 'integer|between:1,7',
                'select_all_branch' => 'nullable|boolean',
                'user_ids'          => 'required_without_all:user_id,select_all_branch|array|min:1',
                'user_ids.*'        => 'integer|exists:users,id',
                'user_id'           => 'nullable|exists:users,id',
                'auto_approve'      => 'nullable|boolean',
            ]);
        }

        return array_merge($base, [
            'is_open_slot' => 'nullable|boolean',
            'position_id'  => 'nullable|exists:positions,id',
            'user_id'      => 'required_unless:is_open_slot,1|nullable|exists:users,id',
            'date'         => 'required|date',
            'auto_approve' => 'nullable|boolean',
        ]);
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $branchId = (int) $this->input('branch_id');
            $mode = $this->input('mode') ?: ($this->has('start_date') ? 'recurring' : 'single');

            if ($mode === 'recurring') {
                $startDate = $this->input('start_date');
                $endDate = $this->input('end_date');

                if ($startDate && $endDate && strtotime($endDate) >= strtotime($startDate)) {
                    $start = \Carbon\Carbon::parse($startDate);
                    $end = \Carbon\Carbon::parse($endDate);
                    if ($start->diffInDays($end) > 90) {
                        $validator->errors()->add('end_date', 'Rentang tanggal maksimal adalah 90 hari.');
                    }
                }

                $selectAll = (bool) $this->input('select_all_branch');
                $userIds = $selectAll
                    ? DB::table('users')->where('branch_id', $branchId)->where('active', true)->whereIn('role', ['employee', 'manager'])->pluck('id')->all()
                    : (array) ($this->input('user_ids') ?: [$this->input('user_id')]);

                $userIds = array_values(array_filter(array_map('intval', $userIds)));

                if (empty($userIds)) {
                    $validator->errors()->add('user_ids', 'Pilih setidaknya satu karyawan aktif di cabang ini.');
                    return;
                }

                $invalidCount = DB::table('users')->whereIn('id', $userIds)
                    ->where(function ($q) use ($branchId) {
                        $q->whereNull('branch_id')->orWhere('branch_id', '!=', $branchId);
                    })->count();

                if ($invalidCount > 0) {
                    $validator->errors()->add('shift', 'Seluruh karyawan yang dipilih harus terdaftar di cabang shift ini.');
                }
            } else {
                if ($this->filled('user_id')) {
                    $userBranch = DB::table('users')->where('id', $this->input('user_id'))->value('branch_id');
                    if ($userBranch && (int) $userBranch !== $branchId) {
                        $validator->errors()->add('shift', 'Karyawan harus berada di cabang shift.');
                    }
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'user_id.required'              => 'Karyawan wajib dipilih.',
            'user_id.exists'                => 'Karyawan tidak ditemukan.',
            'user_ids.required_without_all' => 'Pilih setidaknya satu karyawan untuk dijadwalkan.',
            'user_ids.min'                  => 'Pilih setidaknya satu karyawan untuk dijadwalkan.',
            'branch_id.required'            => 'Cabang wajib dipilih.',
            'branch_id.exists'              => 'Cabang tidak ditemukan.',
            'date.required'                 => 'Tanggal shift wajib diisi.',
            'start_date.required'           => 'Tanggal awal rentang shift wajib diisi.',
            'end_date.required'             => 'Tanggal akhir rentang shift wajib diisi.',
            'end_date.after_or_equal'       => 'Tanggal akhir harus sama dengan atau setelah tanggal awal.',
            'days_of_week.required'         => 'Pilih setidaknya satu hari kerja aktif dalam sepekan.',
            'days_of_week.min'              => 'Pilih setidaknya satu hari kerja aktif dalam sepekan.',
            'start_time.required'           => 'Jam mulai shift wajib diisi.',
            'start_time.date_format'        => 'Format jam mulai harus JJ:MM (contoh: 09:00).',
            'end_time.required'             => 'Jam selesai shift wajib diisi.',
            'end_time.date_format'          => 'Format jam selesai harus JJ:MM (contoh: 17:00).',
        ];
    }
}
