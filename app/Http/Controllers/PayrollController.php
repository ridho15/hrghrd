<?php

namespace App\Http\Controllers;

use App\Services\PayrollCalculator;
use App\Support\Access;
use App\Support\Audit;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PayrollController extends Controller
{
    private function admin(): void { abort_unless(Access::admin(),403); }

    public function index(Request $request, PayrollCalculator $calculator)
    {
        $this->admin();
        $branches=DB::table('branches')->orderBy('name')->get();
        $month=$request->query('month',now('Asia/Jakarta')->format('Y-m'));
        abort_unless(preg_match('/^\d{4}-(0[1-9]|1[0-2])$/',$month),422);
        $branchId=(int)$request->query('branch_id', $branches->first()?->id);
        $branch=$branches->firstWhere('id',$branchId); abort_unless($branch,404);
        $run=DB::table('payroll_runs')->where('branch_id',$branchId)->where('month',$month)->first();
        $employees=DB::table('users')->where('branch_id',$branchId)->whereIn('role',['employee','manager'])
            ->where('hired_at','<=',Carbon::createFromFormat('!Y-m',$month,'Asia/Jakarta')->endOfMonth()->toDateString())
            ->where(fn($q)=>$q->whereNull('ended_at')->orWhere('ended_at','>=',$month.'-01'))
            ->orderBy('name')->get();
        $lines=[];
        foreach ($employees as $employee) {
            $saved=$run && in_array($run->status,['approved','locked'],true) ? DB::table('payroll_lines')->where('payroll_run_id',$run->id)->where('user_id',$employee->id)->first() : null;
            $lines[]=['employee'=>$employee,'detail'=>$saved ? json_decode($saved->breakdown,true) : $calculator->calculate($employee,$month)];
        }
        $blockers=$this->blockers($branchId,$month);
        return view('payroll',compact('branches','branch','branchId','month','run','lines','blockers'));
    }

    public function generate(Request $request, PayrollCalculator $calculator)
    {
        $this->admin(); $data=$this->selection($request);
        $run=DB::table('payroll_runs')->where('branch_id',$data['branch_id'])->where('month',$data['month'])->first();
        abort_if($run && $run->status!=='draft',409,'Periode sudah disetujui atau dikunci.');
        DB::transaction(function () use ($data,$calculator,$run) {
            $runId=$run?->id ?? DB::table('payroll_runs')->insertGetId(['branch_id'=>$data['branch_id'],
                'month'=>$data['month'],'status'=>'draft','created_at'=>now(),'updated_at'=>now()]);
            $end=Carbon::createFromFormat('!Y-m',$data['month'],'Asia/Jakarta')->endOfMonth()->toDateString();
            $employees=DB::table('users')->where('branch_id',$data['branch_id'])->whereIn('role',['employee','manager'])
                ->where('hired_at','<=',$end)->where(fn($q)=>$q->whereNull('ended_at')->orWhere('ended_at','>=',$data['month'].'-01'))->get();
            DB::table('payroll_lines')->where('payroll_run_id',$runId)->delete();
            foreach ($employees as $employee) {
                $detail=$calculator->calculate($employee,$data['month']);
                DB::table('payroll_lines')->insert(['payroll_run_id'=>$runId,'user_id'=>$employee->id,
                    'breakdown'=>json_encode($detail),'net'=>$detail['net'],'created_at'=>now(),'updated_at'=>now()]);
            }
            Audit::record('payroll_run',$runId,'generate',null,null,['employee_count'=>$employees->count()]);
        });
        return redirect()->route('payroll.index',$data)->with('ok','Pratinjau payroll disimpan sebagai draf.');
    }

    public function approve(Request $request, PayrollCalculator $calculator)
    {
        $this->admin(); $data=$this->selection($request);
        $run=DB::table('payroll_runs')->where('branch_id',$data['branch_id'])->where('month',$data['month'])->first();
        abort_unless($run && $run->status==='draft',409,'Buat draf dulu.');
        abort_if(now('Asia/Jakarta')->lte(Carbon::createFromFormat('!Y-m',$data['month'],'Asia/Jakarta')->endOfMonth()),409,'Bulan payroll belum berakhir.');
        $blockers=$this->blockers($data['branch_id'],$data['month']);
        abort_if($blockers,409,'Selesaikan shift, pengajuan, dan pengecualian yang tertunda sebelum persetujuan.');
        $saved=DB::table('payroll_lines')->where('payroll_run_id',$run->id)->get()->keyBy('user_id');
        $end=Carbon::createFromFormat('!Y-m',$data['month'],'Asia/Jakarta')->endOfMonth()->toDateString();
        $employees=DB::table('users')->where('branch_id',$data['branch_id'])->whereIn('role',['employee','manager'])
            ->where('hired_at','<=',$end)->where(fn($q)=>$q->whereNull('ended_at')->orWhere('ended_at','>=',$data['month'].'-01'))->get();
        abort_if($saved->count()!==$employees->count(),409,'Draf payroll berubah. Buat ulang dan periksa kembali.');
        foreach ($employees as $employee)
            abort_if(($saved->get($employee->id)?->breakdown ?? '')!==json_encode($calculator->calculate($employee,$data['month'])),
                409,'Draf payroll berubah. Buat ulang dan periksa kembali.');
        DB::table('payroll_runs')->where('id',$run->id)->update(['status'=>'approved','approved_by'=>auth()->id(),
            'approved_at'=>now(),'updated_at'=>now()]);
        Audit::record('payroll_run',$run->id,'approve');
        return back()->with('ok','Payroll disetujui.');
    }

    public function lock(Request $request)
    {
        $this->admin(); $data=$this->selection($request);
        $run=DB::table('payroll_runs')->where('branch_id',$data['branch_id'])->where('month',$data['month'])->first();
        abort_unless($run && $run->status==='approved',409,'Setujui payroll dulu.');
        $last=Carbon::createFromFormat('!Y-m',$data['month'],'Asia/Jakarta')->endOfMonth();
        abort_if(now('Asia/Jakarta')->lte($last),409,'Bulan payroll belum berakhir.');
        abort_if($this->blockers($data['branch_id'],$data['month']),409,'Masih ada pekerjaan tertunda.');
        DB::table('payroll_runs')->where('id',$run->id)->update(['status'=>'locked','locked_by'=>auth()->id(),
            'locked_at'=>now(),'updated_at'=>now()]);
        Audit::record('payroll_run',$run->id,'lock');
        return back()->with('ok','Payroll dikunci.');
    }

    public function adjustment(Request $request)
    {
        $this->admin();
        $data=$request->validate(['user_id'=>'required|exists:users,id','month'=>'required|date_format:Y-m',
            'amount'=>'required|integer|between:-100000000,100000000','reason'=>'required|string|min:10|max:500']);
        $employee=DB::table('users')->find($data['user_id']);
        abort_if(DB::table('payroll_runs')->where('branch_id',$employee->branch_id)->where('month',$data['month'])
            ->where('status','!=','draft')->exists(),409,'Periode telah disetujui.');
        $id=DB::table('payroll_adjustments')->insertGetId($data+['created_by'=>auth()->id(),'created_at'=>now(),'updated_at'=>now()]);
        Audit::record('payroll_adjustment',$id,'create',$data['reason'],null,['amount'=>$data['amount']]);
        return back()->with('ok','Koreksi manual ditambahkan. Buat ulang draf.');
    }

    public function export(Request $request)
    {
        $this->admin(); $data=$this->selection($request);
        $run=DB::table('payroll_runs')->where('branch_id',$data['branch_id'])->where('month',$data['month'])->first();
        abort_unless($run && in_array($run->status,['approved','locked'],true),409);
        $rows=DB::table('payroll_lines as l')->join('users as u','u.id','=','l.user_id')
            ->where('l.payroll_run_id',$run->id)->orderBy('u.name')->select('u.name','u.email','l.breakdown')->get();
        return response()->streamDownload(function () use ($rows) {
            $out=fopen('php://output','w'); fputcsv($out,['Nama','Email','Gaji prorata','Hari tidak dibayar','Potongan absen','Lembur','Koreksi','Jumlah akhir']);
            foreach ($rows as $row) { $d=json_decode($row->breakdown,true);
                fputcsv($out,[$this->safeCsv($row->name),$this->safeCsv($row->email),$d['prorated_base'],$d['unpaid_deduction'],
                    $d['late_deduction'],$d['overtime_pay'],$d['manual_total'],$d['net']]); }
            fclose($out);
        },'payroll-'.$data['month'].'-cabang-'.$data['branch_id'].'.csv',['Content-Type'=>'text/csv; charset=UTF-8']);
    }

    private function selection(Request $request): array
    {
        return $request->validate(['branch_id'=>'required|exists:branches,id','month'=>'required|date_format:Y-m']);
    }

    private function safeCsv(string $value): string
    {
        return preg_match('/^[\s]*[=+\-@]/u',$value) ? "'".$value : $value;
    }

    private function blockers(int $branchId,string $month): array
    {
        $first=$month.'-01'; $last=Carbon::createFromFormat('!Y-m',$month,'Asia/Jakarta')->endOfMonth()->toDateString();
        $blockers=[];
        if (DB::table('users')->where('branch_id',$branchId)->whereIn('role',['employee','manager'])->where('active',0)
            ->whereNull('ended_at')->exists()) $blockers[]='Karyawan nonaktif tanpa tanggal akhir kerja';
        if (DB::table('shifts')->where('branch_id',$branchId)->where('start_at','>=',$first)
            ->where('start_at','<=',$last.' 23:59:59')->where('status','draft')->exists()) $blockers[]='Shift draf';
        if (DB::table('shifts')->where('branch_id',$branchId)->where('start_at','>=',$first)
            ->where('start_at','<=',$last.' 23:59:59')->where('end_at','>',now('Asia/Jakarta'))->exists()) $blockers[]='Shift belum selesai';
        if (DB::table('attendances as a')->join('shifts as s','s.id','=','a.shift_id')
            ->where('s.branch_id',$branchId)->where('s.start_at','>=',$first)->where('s.start_at','<=',$last.' 23:59:59')
            ->whereNotNull('a.checkin_at')->whereNull('a.checkout_at')->exists()) $blockers[]='Absensi belum check-out';
        if (DB::table('attendances as a')->join('shifts as s','s.id','=','a.shift_id')
            ->where('s.branch_id',$branchId)->where('s.start_at','>=',$first)->where('s.start_at','<=',$last.' 23:59:59')
            ->where('a.overtime_minutes','>',0)->whereNull('a.overtime_approved_by')->exists()) $blockers[]='Lembur belum diputuskan';
        if (DB::table('leave_requests as r')->join('users as u','u.id','=','r.user_id')
            ->where('u.branch_id',$branchId)->where('r.start_date','<=',$last)->where('r.end_date','>=',$first)
            ->where('r.status','pending')->exists()) $blockers[]='Pengajuan tertunda';
        if (DB::table('attendance_exceptions as e')->join('shifts as s','s.id','=','e.shift_id')
            ->where('s.branch_id',$branchId)->where('s.start_at','>=',$first)->where('s.start_at','<=',$last.' 23:59:59')
            ->where('e.status','pending')->exists()) $blockers[]='Pengecualian absensi tertunda';
        return $blockers;
    }
}
