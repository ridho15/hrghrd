<?php

namespace App\Services;

use App\Support\Audit;
use App\Support\Period;
use App\Support\Rules;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AttendanceService
{
    public static function qr(object $branch, ?int $slot = null): string
    {
        $slot ??= intdiv(now()->timestamp,30);
        return strtoupper(substr(hash_hmac('sha256', $branch->id.'|'.$slot, $branch->qr_secret),0,8));
    }

    public function act(object $user, int $shiftId, string $action, array $input, string $deviceToken): void
    {
        abort_unless(in_array($action,['in','out'],true),404);
        try {
        $lateError = DB::transaction(function () use ($user,$shiftId,$action,$input,$deviceToken) {
            $shift = DB::table('shifts')->where('id',$shiftId)->where('user_id',$user->id)->first();
            $now = now('Asia/Jakarta');
            if (!$shift || $shift->status !== 'approved') $this->reject($user->id,$shiftId,$action,'Shift tidak disetujui',[], $now);
            Period::writable($shift->branch_id,$shift->start_at);
            $branch = DB::table('branches')->find($shift->branch_id);
            $start = Carbon::parse($shift->start_at,'Asia/Jakarta');
            $end = Carbon::parse($shift->end_at,'Asia/Jakarta');
            $attendance = DB::table('attendances')->where('shift_id',$shiftId)->first();
            $evidence = ['server_at'=>$now->toIso8601String(),'branch_id'=>$shift->branch_id,
                'latitude'=>isset($input['latitude']) ? round((float)$input['latitude'],5) : null,
                'longitude'=>isset($input['longitude']) ? round((float)$input['longitude'],5) : null,
                'accuracy_m'=>isset($input['accuracy']) ? round((float)$input['accuracy']) : null,
                'device_hash'=>hash('sha256',$deviceToken),'ip'=>request()->ip()];
            if ($action === 'in') {
                if ($attendance) $this->reject($user->id,$shiftId,$action,'Check-in sudah tercatat',$evidence,$now);
                if ($now->lt($start->copy()->subMinutes(Rules::int('checkin_early_minutes'))))
                    $this->reject($user->id,$shiftId,$action,'Terlalu awal',$evidence,$now);
                $late = max(0,(int)$start->diffInMinutes($now,false));
                if ($late > Rules::int('late_reject_minutes')) {
                    $absentId=DB::table('attendances')->insertGetId(['shift_id'=>$shiftId,'user_id'=>$user->id,'status'=>'absent',
                        'late_minutes'=>$late,'created_at'=>$now,'updated_at'=>$now]);
                    $this->attempt($user->id,$shiftId,$action,'rejected','Lebih dari batas terlambat',$evidence,$now);
                    Audit::record('attendance',$absentId,'auto_absent','Terlambat '.$late.' menit');
                    return 'Terlambat lebih dari batas; ditandai alfa. Ajukan pengecualian bila ada kendala.';
                }
                if ($now->gt($end)) $this->reject($user->id,$shiftId,$action,'Shift telah selesai',$evidence,$now);
            } else {
                if (!$attendance || !$attendance->checkin_at || $attendance->checkout_at)
                    $this->reject($user->id,$shiftId,$action,'Check-out tidak sah atau ganda',$evidence,$now);
                if ($now->lt(Carbon::parse($attendance->checkin_at,'Asia/Jakarta')) ||
                    $now->gt($end->copy()->addHours(Rules::int('checkout_late_hours'))))
                    $this->reject($user->id,$shiftId,$action,'Di luar jendela check-out',$evidence,$now);
            }
            if (!$deviceToken || ($user->device_hash && !hash_equals($user->device_hash,$evidence['device_hash'])))
                $this->reject($user->id,$shiftId,$action,'Perangkat berbeda',$evidence,$now);
            if (($input['challenge'] ?? '') !== session('attendance_challenge'))
                $this->reject($user->id,$shiftId,$action,'Tantangan tidak cocok',$evidence,$now);
            session()->forget('attendance_challenge');
            if (!hash_equals(self::qr($branch),(string)($input['qr_code'] ?? '')))
                $this->reject($user->id,$shiftId,$action,'Kode cabang kedaluwarsa atau salah',$evidence,$now);
            if ($branch->latitude === null || $branch->longitude === null || !isset($input['latitude'],$input['longitude'],$input['accuracy']))
                $this->reject($user->id,$shiftId,$action,'Lokasi cabang atau GPS tidak tersedia',$evidence,$now);
            $distance = self::distance((float)$branch->latitude,(float)$branch->longitude,(float)$input['latitude'],(float)$input['longitude']);
            $evidence['distance_m'] = round($distance);
            if ((float)$input['accuracy'] > 100 || $distance + (float)$input['accuracy'] > $branch->radius_m)
                $this->reject($user->id,$shiftId,$action,'Lokasi di luar area atau akurasi GPS rendah',$evidence,$now);
            if (!$user->device_hash) DB::table('users')->where('id',$user->id)->update(['device_hash'=>$evidence['device_hash']]);
            if ($action === 'in') {
                $late = max(0,(int)$start->diffInMinutes($now,false));
                $units = Rules::lateUnits($late);
                $prior = DB::table('attendance_attempts')->where('user_id',$user->id)->where('result','accepted')
                    ->where('server_at','>=',$now->copy()->subHour())->where('shift_id','!=',$shiftId)->exists();
                DB::table('attendances')->insert(['shift_id'=>$shiftId,'user_id'=>$user->id,'checkin_at'=>$now,
                    'status'=>$units ? 'late' : 'present','late_minutes'=>$late,'late_units'=>$units,
                    'checkin_evidence'=>json_encode($evidence),'flags'=>json_encode($prior ? ['Beberapa absensi dalam satu jam'] : []),
                    'created_at'=>$now,'updated_at'=>$now]);
            } else {
                $extra = max(0,(int)$end->diffInMinutes($now,false));
                $overtime = max(0,$extra-Rules::int('overtime_threshold_minutes'));
                DB::table('attendances')->where('id',$attendance->id)->update([
                    'checkout_at'=>$now,'checkout_evidence'=>json_encode($evidence),
                    'overtime_minutes'=>$overtime,'updated_at'=>$now]);
            }
            $this->attempt($user->id,$shiftId,$action,'accepted',null,$evidence,$now);
            Audit::record('attendance',(int)DB::table('attendances')->where('shift_id',$shiftId)->value('id'),$action === 'in' ? 'checkin' : 'checkout');
            return null;
        });
        } catch (ValidationException $e) {
            $shift=DB::table('shifts')->find($shiftId);
            $branch=$shift ? DB::table('branches')->find($shift->branch_id) : null;
            $distance=$branch && isset($input['latitude'],$input['longitude'])
                ? round(self::distance((float)$branch->latitude,(float)$branch->longitude,(float)$input['latitude'],(float)$input['longitude'])) : null;
            $this->attempt($user->id,$shiftId,$action,'rejected',$e->errors()['attendance'][0] ?? 'Ditolak',
                ['latitude'=>$input['latitude'] ?? null,'longitude'=>$input['longitude'] ?? null,
                    'accuracy_m'=>$input['accuracy'] ?? null,'distance_m'=>$distance,
                    'qr_valid'=>$branch ? hash_equals(self::qr($branch),(string)($input['qr_code'] ?? '')) : false,
                    'device_matches'=>!$user->device_hash || hash_equals($user->device_hash,hash('sha256',$deviceToken)),
                    'ip'=>request()->ip()],now('Asia/Jakarta'));
            throw $e;
        }
        if ($lateError) throw ValidationException::withMessages(['attendance'=>$lateError]);
    }

    private function reject(int $userId, ?int $shiftId, string $action, string $reason, array $evidence, Carbon $now): never
    {
        throw ValidationException::withMessages(['attendance'=>$reason.'. Ajukan pengecualian jika perlu.']);
    }

    private function attempt(int $userId, ?int $shiftId, string $action, string $result, ?string $reason, array $evidence, Carbon $now): void
    {
        DB::table('attendance_attempts')->insert(['user_id'=>$userId,'shift_id'=>$shiftId,'action'=>$action,
            'result'=>$result,'reason'=>$reason,'evidence'=>json_encode($evidence),'server_at'=>$now]);
    }

    private static function distance(float $a,float $b,float $c,float $d): float
    {
        $r=6371000; $lat=deg2rad($c-$a); $lon=deg2rad($d-$b);
        $x=sin($lat/2)**2+cos(deg2rad($a))*cos(deg2rad($c))*sin($lon/2)**2;
        return 2*$r*asin(min(1,sqrt($x)));
    }
}
