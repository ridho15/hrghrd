<?php

namespace App\Http\Requests\Admin;

use App\Support\Access;
use Illuminate\Foundation\Http\FormRequest;

class SettingsSaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Access::admin();
    }

    public function rules(): array
    {
        return [
            'late_grace_minutes' => 'required|integer|min:0|max:120',
            'late_unit_minutes' => 'required|integer|min:1|max:120',
            'late_penalty_per_unit' => 'required|integer|min:0',
            'late_reject_minutes' => 'required|integer|min:1|max:240',
            'checkin_early_minutes' => 'required|integer|min:0|max:240',
            'checkout_late_hours' => 'required|integer|min:1|max:24',
            'overtime_threshold_minutes' => 'required|integer|min:0|max:480',
            'overtime_calculation_basis' => 'sometimes|required|in:per_hour,per_minute',
            'overtime_rate_scheme' => 'sometimes|required|in:fixed_amount,hourly_prorata,statutory_pp35',
            'overtime_unit_minutes' => 'sometimes|required|integer|min:1|max:480',
            'overtime_rate_per_unit' => 'sometimes|required|integer|min:0',
            'overtime_max_daily_minutes' => 'sometimes|required|integer|min:15|max:720',
            'overtime_rounding_mode' => 'sometimes|required|in:floor,round,ceil',
            'min_work_duration_minutes' => 'sometimes|integer|min:0|max:480',
            'leave_notice_days' => 'required|integer|min:0|max:90',
            'sick_paid_days_per_case' => 'required|integer|min:0|max:30',
            'hourly_divisor' => 'required|integer|min:1|max:240',
            'daily_divisor' => 'required|in:calendar,fixed_30',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $lateReject = (int) $this->input('late_reject_minutes');
            $lateGrace = (int) $this->input('late_grace_minutes');

            if ($lateReject <= $lateGrace) {
                $validator->errors()->add('late_reject_minutes', 'Batas alfa harus lebih besar dari toleransi keterlambatan.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'daily_divisor.in' => 'Skema pembagi harian harus antara kalender atau tetap 30 hari.',
        ];
    }
}
