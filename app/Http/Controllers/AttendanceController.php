<?php

namespace App\Http\Controllers;

use App\Services\AttendanceService;
use App\Support\Access;
use App\Support\Audit;
use App\Support\Period;
use App\Support\Rules;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AttendanceController extends Controller
{
    public function act(Request $request, int $shiftId, AttendanceService $service)
    {
        $data=$request->validate(['action'=>'required|in:in,out','qr_code'=>'nullable|string|max:20',
            'challenge'=>'nullable|string','latitude'=>'nullable|numeric|between:-90,90',
            'longitude'=>'nullable|numeric|between:-180,180','accuracy'=>'nullable|numeric|min:0|max:10000']);
        $data['qr_code']=strtoupper(trim($data['qr_code'] ?? ''));
        $service->act($request->user(),$shiftId,$data['action'],$data,(string)$request->cookie('hrd_device'));
        return back()->with('ok',$data['action']==='in' ? 'Check-in tercatat.' : 'Check-out tercatat.');
    }

    public function qrPage(Request $request)
    {
        abort_unless(Access::manager(),403);
        $branches=Access::admin() ? DB::table('branches')->orderBy('name')->get() : DB::table('branches')->where('id',auth()->user()->branch_id)->get();
        $branch=$branches->firstWhere('id',(int)$request->query('branch_id')) ?: $branches->first();
        abort_unless($branch,404);
        return view('qr',compact('branch','branches'));
    }

    public function qr(Request $request)
    {
        abort_unless(Access::manager(),403);
        $branchId=(int)($request->query('branch_id') ?: auth()->user()->branch_id);
        abort_unless(Access::branch($branchId),403);
        $branch=DB::table('branches')->find($branchId); abort_unless($branch,404);
        return response()->json(['code'=>AttendanceService::qr($branch),'expires_at'=>(intdiv(now()->timestamp,30)+1)*30]);
    }

    public function exception(Request $request, int $shiftId)
    {
        $data=$request->validate(['action'=>'required|in:in,out','reason'=>'required|string|min:10|max:1000']);
        $shift=DB::table('shifts')->where('id',$shiftId)->where('user_id',auth()->id())->first();
        abort_unless($shift && $shift->status==='approved',403);
        Period::writable($shift->branch_id,$shift->start_at);
        abort_if(DB::table('attendance_exceptions')->where('shift_id',$shiftId)->where('action',$data['action'])->where('status','pending')->exists(),409,'Pengecualian sudah menunggu keputusan.');
        $id=DB::table('attendance_exceptions')->insertGetId(['user_id'=>auth()->id(),'shift_id'=>$shiftId,
            'action'=>$data['action'],'reason'=>$data['reason'],'created_at'=>now('Asia/Jakarta'),'updated_at'=>now('Asia/Jakarta')]);
        Audit::record('attendance_exception',$id,'request',$data['reason']);
        return back()->with('ok','Pengecualian dikirim ke manager. Belum dihitung sebagai absensi.');
    }

    public function review()
    {
        abort_unless(Access::manager(),403);
        $branch=auth()->user()->branch_id;
        $exceptions=DB::table('attendance_exceptions as e')->join('shifts as s','s.id','=','e.shift_id')
            ->join('users as u','u.id','=','e.user_id')
            ->when(!Access::admin(),fn($q)=>$q->where('s.branch_id',$branch))->where('e.status','pending')
            ->select('e.*','u.name','s.start_at','s.branch_id')->orderBy('e.created_at')->get();
        $attempts=DB::table('attendance_attempts as t')->join('users as u','u.id','=','t.user_id')
            ->when(!Access::admin(),fn($q)=>$q->where('u.branch_id',$branch))
            ->where('t.server_at','>=',now('Asia/Jakarta')->subDays(7))->orderByDesc('t.id')->limit(80)
            ->select('t.*','u.name')->get();
        $attendances=DB::table('attendances as a')->join('shifts as s','s.id','=','a.shift_id')
            ->join('users as u','u.id','=','a.user_id')
            ->when(!Access::admin(),fn($q)=>$q->where('s.branch_id',$branch))
            ->where('s.start_at','>=',now('Asia/Jakarta')->subDays(35))
            ->orderByDesc('s.start_at')->limit(100)
            ->select('a.*','u.name','s.start_at','s.end_at','s.branch_id')->get();
        return view('attendance-review',compact('exceptions','attempts','attendances'));
    }

    public function reviewException(Request $request, int $id)
    {
        abort_unless(Access::manager(),403);
        $data=$request->validate(['decision'=>'required|in:approved,rejected','review_note'=>'required|string|min:5|max:1000']);
        DB::transaction(function () use ($id,$data) {
            $ex=DB::table('attendance_exceptions')->where('id',$id)->first(); abort_unless($ex && $ex->status==='pending',409);
            $shift=DB::table('shifts')->find($ex->shift_id); abort_unless(Access::branch($shift->branch_id),403);
            Period::writable($shift->branch_id,$shift->start_at);
            if ($data['decision']==='approved') {
                $attendance=DB::table('attendances')->where('shift_id',$shift->id)->first();
                if ($ex->action==='in') {
                    abort_if($attendance && $attendance->checkin_at,409,'Sudah check-in.');
                    $claimed=Carbon::parse($ex->created_at,'Asia/Jakarta');
                    $late=max(0,(int)Carbon::parse($shift->start_at,'Asia/Jakarta')->diffInMinutes($claimed,false));
                    $values=['checkin_at'=>$claimed,'status'=>'corrected','late_minutes'=>$late,
                        'late_units'=>Rules::lateUnits($late),'checkin_evidence'=>json_encode(['exception_id'=>$ex->id,'approved_by'=>auth()->id()]),
                        'updated_at'=>now('Asia/Jakarta')];
                    if ($attendance) DB::table('attendances')->where('id',$attendance->id)->update($values);
                    else DB::table('attendances')->insert($values+['shift_id'=>$shift->id,'user_id'=>$ex->user_id,'created_at'=>now('Asia/Jakarta')]);
                } else {
                    abort_unless($attendance && $attendance->checkin_at && !$attendance->checkout_at,409,'Check-in belum ada atau sudah check-out.');
                    $claimed=Carbon::parse($ex->created_at,'Asia/Jakarta');
                    abort_if($claimed->lt(Carbon::parse($attendance->checkin_at,'Asia/Jakarta')),409);
                    $extra=max(0,(int)Carbon::parse($shift->end_at,'Asia/Jakarta')->diffInMinutes($claimed,false));
                    DB::table('attendances')->where('id',$attendance->id)->update(['checkout_at'=>$claimed,
                        'checkout_evidence'=>json_encode(['exception_id'=>$ex->id,'approved_by'=>auth()->id()]),
                        'overtime_minutes'=>max(0,$extra-Rules::int('overtime_threshold_minutes')),'updated_at'=>now('Asia/Jakarta')]);
                }
            }
            DB::table('attendance_exceptions')->where('id',$id)->update(['status'=>$data['decision'],
                'review_note'=>$data['review_note'],'reviewed_by'=>auth()->id(),'reviewed_at'=>now('Asia/Jakarta'),'updated_at'=>now('Asia/Jakarta')]);
            Audit::record('attendance_exception',$id,$data['decision'],$data['review_note']);
        });
        return back()->with('ok','Pengecualian diproses.');
    }

    public function correction(Request $request, int $id)
    {
        abort_unless(Access::manager(),403);
        $data=$request->validate(['status'=>'required|in:present,late,absent,corrected',
            'checkin_at'=>'nullable|date','checkout_at'=>'nullable|date|after_or_equal:checkin_at',
            'reason'=>'required|string|min:10|max:1000']);
        $attendance=DB::table('attendances')->find($id); abort_unless($attendance,404);
        $shift=DB::table('shifts')->find($attendance->shift_id); abort_unless(Access::branch($shift->branch_id),403);
        Period::writable($shift->branch_id,$shift->start_at);
        $checkin=$data['checkin_at'] ?? $attendance->checkin_at;
        $checkout=$data['checkout_at'] ?? $attendance->checkout_at;
        if ($data['status']==='absent') { $checkin=null; $checkout=null; }
        $late=$checkin ? max(0,(int)Carbon::parse($shift->start_at,'Asia/Jakarta')->diffInMinutes(Carbon::parse($checkin,'Asia/Jakarta'),false)) : 0;
        $extra=$checkout ? max(0,(int)Carbon::parse($shift->end_at,'Asia/Jakarta')->diffInMinutes(Carbon::parse($checkout,'Asia/Jakarta'),false)) : 0;
        $values=['status'=>$data['status'],'checkin_at'=>$checkin,'checkout_at'=>$checkout,
            'late_minutes'=>$late,'late_units'=>Rules::lateUnits($late),
            'overtime_minutes'=>max(0,$extra-Rules::int('overtime_threshold_minutes')),
            'overtime_approved_by'=>null,'updated_at'=>now('Asia/Jakarta')];
        DB::table('attendances')->where('id',$id)->update($values);
        Audit::record('attendance',$id,'correction',$data['reason'],(array)$attendance,$values);
        return back()->with('ok','Koreksi tercatat. Persetujuan lembur direset.');
    }

    public function overtime(Request $request, int $id)
    {
        abort_unless(Access::manager(),403);
        $data=$request->validate(['reason'=>'required|string|min:5|max:500']);
        $a=DB::table('attendances')->find($id); abort_unless($a,404);
        $shift=DB::table('shifts')->find($a->shift_id); abort_unless(Access::branch($shift->branch_id),403);
        Period::writable($shift->branch_id,$shift->start_at);
        abort_unless($a->checkout_at && $a->overtime_minutes>0,409);
        DB::table('attendances')->where('id',$id)->update(['overtime_approved_by'=>auth()->id(),'updated_at'=>now('Asia/Jakarta')]);
        Audit::record('attendance',$id,'overtime_approve',$data['reason'],null,['minutes'=>$a->overtime_minutes]);
        return back()->with('ok','Lembur disetujui.');
    }
}
