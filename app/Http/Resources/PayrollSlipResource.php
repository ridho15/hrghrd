<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PayrollSlipResource extends JsonResource
{
    public function toArray($request): array
    {
        $detail = $this['detail'];
        $branch = $this['branch'];
        $employee = $this['employee'];

        // bonus_total/kasbon_total dihitung per baris penyesuaian oleh
        // PayrollCalculator (bukan dari netto manual_total), supaya keduanya
        // tidak saling menutupi kalau terjadi di bulan yang sama. Fallback ke
        // perhitungan netto lama untuk breakdown yang sudah dikunci sebelum
        // field ini ada.
        $bonusTotal = $detail['bonus_total'] ?? max(0, $detail['manual_total']);
        $kasbonTotal = $detail['kasbon_total'] ?? abs(min(0, $detail['manual_total']));

        $grossEarnings = $detail['prorated_base']
            + $detail['overtime_pay']
            + $bonusTotal;

        $totalDeductions = $detail['unpaid_deduction']
            + $detail['late_deduction']
            + $kasbonTotal;

        return [
            'ref_number'       => $this['ref_number'] ?? ('SLIP-' . ($branch->code ?? 'BR') . '-' . $employee->id . '-' . str_replace('-', '', $this['month'])),
            'period'           => $this['month'],
            // 'draft': estimasi berjalan, dihitung real-time dan bisa berubah.
            // 'approved'/'locked': sudah final, diambil dari payroll_run_lines yang disimpan.
            'status'           => $this['status'] ?? 'draft',
            'employee'         => [
                'id'        => $employee->id,
                'name'      => $employee->name,
                'email'     => $employee->email,
                'position'  => $employee->position?->name,
                'hired_at'  => $employee->hired_at?->toDateString(),
                'role'      => $employee->role,
            ],
            'branch'           => [
                'id'   => $branch->id,
                'name' => $branch->name,
                'code' => $branch->code,
            ],
            'employment'       => [
                'employed_days' => $detail['employed_days'],
                'calendar_days' => $detail['calendar_days'],
                'monthly_salary'=> $detail['monthly_salary'],
                'daily_rate'    => $detail['daily_rate'],
                'hourly_rate'   => $detail['hourly_rate'],
            ],
            'earnings'         => [
                'prorated_base'  => $detail['prorated_base'],
                'overtime_pay'   => $detail['overtime_pay'],
                'overtime_minutes'=> $detail['overtime_minutes'],
                'bonus'          => $bonusTotal,
                'gross_total'    => $grossEarnings,
            ],
            'deductions'       => [
                'unpaid_deduction' => $detail['unpaid_deduction'],
                'unpaid_days'      => count($detail['unpaid_dates']),
                'unpaid_dates'     => $detail['unpaid_dates'],
                'late_deduction'   => $detail['late_deduction'],
                'late_units'       => $detail['late_units'],
                'kasbon'           => $kasbonTotal,
                'total_deductions' => $totalDeductions,
            ],
            'net_pay'          => $detail['net'],
            'audit'            => [
                'late_sources'      => $detail['late_sources'] ?? [],
                'overtime_sources'  => $detail['overtime_sources'] ?? [],
                'adjustments'       => $detail['adjustments'] ?? [],
            ],
        ];
    }
}
