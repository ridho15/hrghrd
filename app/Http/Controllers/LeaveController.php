<?php

namespace App\Http\Controllers;

use App\Support\Access;
use App\Support\Audit;
use App\Support\Period;
use App\Support\Rules;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class LeaveController extends Controller
{
    public function index()
    {
        $user=auth()->user();
        $requests=DB::table('leave_requests as r')->join('users as u','u.id','=','r.user_id')
            ->where(fn($q)=>Access::manager() ? $q->when(!Access::admin(),fn($x)=>$x->where('u.branch_id',$user->branch_id)) : $q->where('r.user_id',$user->id))
            ->orderByDesc('r.id')->select('r.*','u.name as employee_name','u.branch_id')->limit(100)->get();
        $people=Access::manager() ? DB::table('users')->where('active',1)
            ->when(!Access::admin(),fn($q)=>$q->where('branch_id',$user->branch_id))->orderBy('name')->get() : collect();
        $days=DB::table('leave_days')->whereIn('leave_request_id',$requests->pluck('id')->all() ?: [-1])->orderBy('date')->get()->groupBy('leave_request_id');
        return view('leave',compact('requests','people','days'));
    }

    public function store(Request $request)
    {
        $data=$request->validate(['user_id'=>'nullable|exists:users,id','type'=>'required|in:leave,sick',
            'start_date'=>'required|date','end_date'=>'required|date|after_or_equal:start_date',
            'reason'=>'required|string|min:10|max:1000',
            'certificate'=>'required_if:type,sick|nullable|file|max:5120|mimes:pdf,jpg,jpeg,png']);
        $userId=(int)($data['user_id'] ?? auth()->id()); abort_unless(Access::employee($userId),403);
        if ($userId!==auth()->id()) abort_unless(Access::manager(),403);
        $start=Carbon::parse($data['start_date'],'Asia/Jakarta'); $end=Carbon::parse($data['end_date'],'Asia/Jakarta');
        abort_if($start->diffInDays($end)>30,422,'Maksimal 31 hari per pengajuan.');
        if ($data['type']==='leave' && $start->lt(now('Asia/Jakarta')->startOfDay()->addDays(Rules::int('leave_notice_days'))))
            return back()->withErrors(['start_date'=>'Izin biasa harus diajukan minimal H-'.Rules::int('leave_notice_days').'.']);
        $employee=DB::table('users')->find($userId);
        foreach ($start->copy()->daysUntil($end) as $date) Period::writable($employee->branch_id,$date->toDateString());
        $overlap=DB::table('leave_days as d')->join('leave_requests as r','r.id','=','d.leave_request_id')
            ->where('r.user_id',$userId)->whereBetween('d.date',[$start->toDateString(),$end->toDateString()])
            ->whereIn('d.status',['pending','approved'])->exists();
        abort_if($overlap,409,'Tanggal pengajuan bertumpang tindih.');
        $path=null;
        if ($request->hasFile('certificate')) $path=$request->file('certificate')->store('certificates','local');
        $id=DB::table('leave_requests')->insertGetId(['user_id'=>$userId,'created_by'=>auth()->id(),
            'type'=>$data['type'],'start_date'=>$start->toDateString(),'end_date'=>$end->toDateString(),
            'reason'=>$data['reason'],'certificate_path'=>$path,
            'certificate_name'=>$path ? basename($request->file('certificate')->getClientOriginalName()) : null,
            'created_at'=>now('Asia/Jakarta'),'updated_at'=>now('Asia/Jakarta')]);
        foreach ($start->copy()->daysUntil($end) as $date)
            DB::table('leave_days')->insert(['leave_request_id'=>$id,'date'=>$date->toDateString()]);
        Audit::record('leave_request',$id,'create',$data['reason']);
        return back()->with('ok','Pengajuan dikirim untuk persetujuan.');
    }

    public function review(Request $request, int $id)
    {
        abort_unless(Access::manager(),403);
        $data=$request->validate(['approved_dates'=>'nullable|array','approved_dates.*'=>'date',
            'paid_dates'=>'nullable|array','paid_dates.*'=>'date','review_note'=>'required|string|min:5|max:1000']);
        $leave=DB::table('leave_requests')->find($id); abort_unless($leave && $leave->status==='pending',409);
        $employee=DB::table('users')->find($leave->user_id);
        abort_unless(Access::branch($employee->branch_id),403);
        abort_if((int)$leave->created_by===auth()->id(),403,'Pembuat pengajuan tidak boleh menyetujui sendiri.');
        $approved=$data['approved_dates'] ?? []; $paid=$data['paid_dates'] ?? [];
        $all=DB::table('leave_days')->where('leave_request_id',$id)->orderBy('date')->get();
        foreach ($all as $day) Period::writable($employee->branch_id,$day->date);
        abort_if(array_diff($approved,$all->pluck('date')->all()),422,'Tanggal persetujuan tidak valid.');
        DB::transaction(function () use ($id,$leave,$data,$all,$approved,$paid) {
            $paidSick=0;
            foreach ($all as $day) {
                $isApproved=in_array($day->date,$approved,true);
                $isPaid=false;
                if ($isApproved && $leave->type==='sick') $isPaid=$paidSick++<Rules::int('sick_paid_days_per_case');
                if ($isApproved && $leave->type==='leave') $isPaid=in_array($day->date,$paid,true);
                DB::table('leave_days')->where('id',$day->id)->update(['status'=>$isApproved?'approved':'rejected','paid'=>$isPaid]);
            }
            $status=count($approved)===0 ? 'rejected' : (count($approved)===$all->count() ? 'approved' : 'partial');
            DB::table('leave_requests')->where('id',$id)->update(['status'=>$status,'reviewed_by'=>auth()->id(),
                'review_note'=>$data['review_note'],'reviewed_at'=>now('Asia/Jakarta'),'updated_at'=>now('Asia/Jakarta')]);
            Audit::record('leave_request',$id,'review',$data['review_note'],null,['status'=>$status,'approved_dates'=>$approved,'paid_dates'=>$paid]);
        });
        return back()->with('ok','Pengajuan diputuskan per tanggal.');
    }

    public function certificate(int $id)
    {
        $leave=DB::table('leave_requests')->find($id); abort_unless($leave && $leave->certificate_path,404);
        abort_unless(Access::employee($leave->user_id),403);
        return Storage::disk('local')->download($leave->certificate_path,$leave->certificate_name ?: 'surat-sakit');
    }
}
