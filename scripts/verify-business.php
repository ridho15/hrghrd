<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (!app()->environment(['local', 'testing'])) throw new RuntimeException('Verification requires a local/test environment.');

use App\Services\PayrollCalculator;
use App\Support\Rules;
use Illuminate\Support\Facades\DB;

function check(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }

foreach ([0=>0,15=>0,16=>1,30=>1,31=>2,45=>2,46=>3,60=>3] as $minutes=>$expected)
    check(Rules::lateUnits($minutes)===$expected, "Incorrect late units at $minutes minutes");

DB::beginTransaction();
try {
    $branch=DB::table('branches')->insertGetId(['code'=>'VERIFY','name'=>'Cabang Uji',
        'latitude'=>-6.175392,'longitude'=>106.827153,'radius_m'=>100,'qr_secret'=>'fixture',
        'created_at'=>now(),'updated_at'=>now()]);
    $user=DB::table('users')->insertGetId(['name'=>'Karyawan Uji','email'=>'verification@example.test',
        'password'=>'unused','role'=>'employee','branch_id'=>$branch,'hired_at'=>'2026-09-10',
        'ended_at'=>'2026-09-20','base_salary'=>3000000,'active'=>false,
        'created_at'=>now(),'updated_at'=>now()]);
    $shift=DB::table('shifts')->insertGetId(['user_id'=>$user,'branch_id'=>$branch,
        'start_at'=>'2026-09-11 09:00:00','end_at'=>'2026-09-11 17:00:00',
        'status'=>'approved','created_at'=>now(),'updated_at'=>now()]);
    DB::table('attendances')->insert(['shift_id'=>$shift,'user_id'=>$user,
        'checkin_at'=>'2026-09-11 09:31:00','checkout_at'=>'2026-09-11 19:30:00',
        'status'=>'late','late_minutes'=>31,'late_units'=>2,'overtime_minutes'=>60,
        'overtime_approved_by'=>$user,'created_at'=>now(),'updated_at'=>now()]);
    $leave=DB::table('leave_requests')->insertGetId(['user_id'=>$user,'created_by'=>$user,
        'type'=>'leave','start_date'=>'2026-09-12','end_date'=>'2026-09-12',
        'reason'=>'Synthetic verification','status'=>'approved','created_at'=>now(),'updated_at'=>now()]);
    DB::table('leave_days')->insert(['leave_request_id'=>$leave,'date'=>'2026-09-12','status'=>'approved','paid'=>false]);
    $detail=(new PayrollCalculator())->calculate(DB::table('users')->find($user),'2026-09');
    check($detail['employed_days']===11,'Prorated employment days');
    check($detail['daily_rate']===100000.0,'Calendar daily rate');
    check($detail['hourly_rate']===4166.67,'Hourly rate');
    check($detail['unpaid_dates']===['2026-09-12'],'Unpaid day source');
    check($detail['late_deduction']===20000,'Late deduction');
    check($detail['overtime_pay']===4167,'Approved overtime after threshold');
    check($detail['net']===984167,'Final payroll total');
    echo "Business rules verified: late boundaries, prorata, unpaid day, approved overtime, total.\n";
} finally { DB::rollBack(); }
