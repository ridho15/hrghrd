<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Periode payroll berbasis tanggal cut-off.
 *
 * Dengan cut-off 20, periode "2026-12" (Desember) mencakup 21 November 2026 s.d.
 * 20 Desember 2026: periode diberi nama sesuai bulan tanggal akhirnya. Cut-off 0
 * berarti bulan kalender penuh (perilaku lama).
 *
 * Masa transisi: periode yang sudah dibuat sebelum aturan cut-off berlaku tidak
 * menyimpan tanggal periode dan dianggap bulan kalender. Awal periode berikutnya
 * otomatis dimulai sehari setelah akhir periode sebelumnya, sehingga tidak ada
 * tanggal yang dibayar dua kali.
 */
final class PayrollPeriod
{
    public const TZ = 'Asia/Jakarta';

    public static function cutoffDay(): int
    {
        $day = Rules::int('payroll_cutoff_day');

        return ($day >= 1 && $day <= 28) ? $day : 0;
    }

    /**
     * Rentang nominal periode tanpa memperhitungkan periode yang sudah tersimpan.
     *
     * @return array{start: Carbon, end: Carbon}
     */
    public static function nominal(string $month): array
    {
        $anchor = Carbon::createFromFormat('!Y-m', $month, self::TZ);
        $cutoff = self::cutoffDay();

        if ($cutoff === 0) {
            return ['start' => $anchor->copy()->startOfMonth(), 'end' => $anchor->copy()->endOfMonth()->startOfDay()];
        }

        return [
            'start' => $anchor->copy()->subMonthNoOverflow()->day($cutoff)->addDay(),
            'end' => $anchor->copy()->day($cutoff),
        ];
    }

    /**
     * Rentang efektif periode untuk sebuah cabang.
     *
     * Periode yang sudah disetujui atau dikunci memakai tanggal yang tersimpan;
     * periode lain dimulai paling cepat sehari setelah akhir periode sebelumnya.
     *
     * @return array{start: Carbon, end: Carbon}
     */
    public static function range(string $month, ?int $branchId = null): array
    {
        $range = self::nominal($month);

        if ($branchId === null) {
            return $range;
        }

        $run = DB::table('payroll_runs')->where('branch_id', $branchId)->where('month', $month)->first();
        if ($run && in_array($run->status, ['approved', 'locked'], true)) {
            return self::stored($run);
        }

        $previousMonth = Carbon::createFromFormat('!Y-m', $month, self::TZ)->subMonthNoOverflow()->format('Y-m');
        $previous = DB::table('payroll_runs')->where('branch_id', $branchId)->where('month', $previousMonth)->first();
        if ($previous && in_array($previous->status, ['approved', 'locked'], true)) {
            $previousEnd = self::stored($previous)['end'];
            if ($previousEnd->greaterThanOrEqualTo($range['start'])) {
                $range['start'] = $previousEnd->copy()->addDay();
            }
        }

        return $range;
    }

    /**
     * Rentang periode dari baris payroll_runs; data lama tanpa tanggal = bulan kalender.
     *
     * @return array{start: Carbon, end: Carbon}
     */
    public static function stored(object $run): array
    {
        if (! empty($run->period_start) && ! empty($run->period_end)) {
            return [
                'start' => Carbon::parse($run->period_start, self::TZ)->startOfDay(),
                'end' => Carbon::parse($run->period_end, self::TZ)->startOfDay(),
            ];
        }

        $anchor = Carbon::createFromFormat('!Y-m', $run->month, self::TZ);

        return ['start' => $anchor->copy()->startOfMonth(), 'end' => $anchor->copy()->endOfMonth()->startOfDay()];
    }

    /**
     * Nama periode (YYYY-MM) yang memuat tanggal tertentu menurut aturan cut-off.
     */
    public static function monthFor(Carbon|string $date): string
    {
        $day = $date instanceof Carbon ? $date->copy()->setTimezone(self::TZ) : Carbon::parse($date, self::TZ);
        $cutoff = self::cutoffDay();

        if ($cutoff !== 0 && $day->day > $cutoff) {
            return $day->copy()->startOfMonth()->addMonthNoOverflow()->format('Y-m');
        }

        return $day->format('Y-m');
    }

    public static function current(): string
    {
        return self::monthFor(now(self::TZ));
    }

    /**
     * Label periode untuk tampilan, misal "Desember 2026 (21 Nov 2026 - 20 Des 2026)".
     */
    public static function label(string $month, ?int $branchId = null): string
    {
        $range = self::range($month, $branchId);
        $name = Carbon::createFromFormat('!Y-m', $month, self::TZ)->locale('id')->translatedFormat('F Y');

        return $name . ' (' . self::dates($range) . ')';
    }

    /**
     * @param  array{start: Carbon, end: Carbon}  $range
     */
    public static function dates(array $range): string
    {
        return $range['start']->copy()->locale('id')->translatedFormat('j M Y') . ' - '
            . $range['end']->copy()->locale('id')->translatedFormat('j M Y');
    }
}
