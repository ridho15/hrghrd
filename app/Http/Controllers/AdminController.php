<?php

namespace App\Http\Controllers;

use App\Services\EmployeeImport;
use App\Support\Access;
use App\Support\Audit;
use App\Support\Period;
use App\Support\Rules;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AdminController extends Controller
{
    private function admin(): void { abort_unless(Access::admin(),403); }

    public function people()
    {
        $this->admin();
        $branches=DB::table('branches')->orderBy('name')->get();
        $positions=DB::table('positions')->orderBy('name')->get();
        $people=DB::table('users as u')->leftJoin('branches as b','b.id','=','u.branch_id')
            ->leftJoin('positions as p','p.id','=','u.position_id')
            ->select('u.*','b.name as branch_name','p.name as position_name')->orderBy('u.name')->get();
        return view('people',compact('branches','positions','people'));
    }

    public function branch(Request $request)
    {
        $this->admin();
        $data=$request->validate(['code'=>'required|string|max:20|unique:branches,code','name'=>'required|string|max:100',
            'latitude'=>'required|numeric|between:-90,90','longitude'=>'required|numeric|between:-180,180',
            'radius_m'=>'required|integer|between:20,1000']);
        $id=DB::table('branches')->insertGetId($data+['qr_secret'=>Str::random(64),'created_at'=>now(),'updated_at'=>now()]);
        Audit::record('branch',$id,'create');
        return back()->with('ok','Cabang dibuat.');
    }

    public function branchUpdate(Request $request, int $id)
    {
        $this->admin(); $branch=DB::table('branches')->find($id); abort_unless($branch,404);
        $data=$request->validate(['name'=>'required|string|max:100',
            'latitude'=>'required|numeric|between:-90,90','longitude'=>'required|numeric|between:-180,180',
            'radius_m'=>'required|integer|between:20,1000']);
        DB::table('branches')->where('id',$id)->update($data+['updated_at'=>now()]);
        Audit::record('branch',$id,'update','Area absensi diperbarui',
            ['latitude'=>$branch->latitude,'longitude'=>$branch->longitude,'radius_m'=>$branch->radius_m],$data);
        return back()->with('ok','Lokasi cabang diperbarui.');
    }

    public function person(Request $request)
    {
        $this->admin();
        $data=$request->validate(['name'=>'required|string|max:120','email'=>'required|email|unique:users,email',
            'password'=>'required|string|min:10','role'=>'required|in:admin,manager,employee',
            'branch_id'=>'nullable|required_unless:role,admin|exists:branches,id','position_id'=>'nullable|exists:positions,id',
            'hired_at'=>'required|date','ended_at'=>'nullable|date|after_or_equal:hired_at','base_salary'=>'required|integer|min:0']);
        $data['password']=Hash::make($data['password']);
        $id=DB::table('users')->insertGetId($data+['active'=>true,'created_at'=>now(),'updated_at'=>now()]);
        Audit::record('user',$id,'create');
        return back()->with('ok','Karyawan dibuat.');
    }

    public function position(Request $request)
    {
        $this->admin(); $data=$request->validate(['name'=>'required|string|max:100|unique:positions,name']);
        $id=DB::table('positions')->insertGetId($data+['created_at'=>now(),'updated_at'=>now()]);
        Audit::record('position',$id,'create');
        return back()->with('ok','Jabatan dibuat.');
    }

    public function personUpdate(Request $request, int $id)
    {
        $this->admin(); $person=DB::table('users')->find($id); abort_unless($person,404);
        $data=$request->validate(['name'=>'required|string|max:120','email'=>'required|email|unique:users,email,'.$id,
            'role'=>'required|in:admin,manager,employee','branch_id'=>'nullable|required_unless:role,admin|exists:branches,id','position_id'=>'nullable|exists:positions,id',
            'hired_at'=>'required|date','ended_at'=>'nullable|date|after_or_equal:hired_at','base_salary'=>'required|integer|min:0','active'=>'required|boolean',
            'password'=>'nullable|string|min:10','reset_device'=>'nullable|boolean']);
        abort_if((int)$id===auth()->id() && ($data['role']!=='admin' || !$data['active']),422,'Akun admin sendiri tidak boleh dinonaktifkan atau diturunkan.');
        if (!empty($data['password'])) $data['password']=Hash::make($data['password']); else unset($data['password']);
        if (!empty($data['reset_device'])) $data['device_hash']=null;
        unset($data['reset_device']); $data['updated_at']=now();
        DB::table('users')->where('id',$id)->update($data);
        Audit::record('user',$id,'update','Data HR diperbarui; nilai gaji tidak disimpan dalam log');
        return back()->with('ok','Data karyawan diperbarui.');
    }

    public function shifts()
    {
        abort_unless(Access::manager(),403);
        $branches=DB::table('branches')->orderBy('name')->get();
        $people=DB::table('users')->where('active',1)->whereIn('role',['employee','manager'])
            ->when(!Access::admin(),fn($q)=>$q->where('branch_id',auth()->user()->branch_id))->orderBy('name')->get();
        $shifts=DB::table('shifts as s')->join('users as u','u.id','=','s.user_id')
            ->join('branches as b','b.id','=','s.branch_id')
            ->when(!Access::admin(),fn($q)=>$q->where('s.branch_id',auth()->user()->branch_id))
            ->where('s.start_at','>=',now('Asia/Jakarta')->subDays(14))->orderBy('s.start_at')->limit(150)
            ->select('s.*','u.name as employee_name','b.name as branch_name')->get();
        return view('shifts',compact('branches','people','shifts'));
    }

    public function shift(Request $request)
    {
        $this->admin();
        $data=$request->validate(['user_id'=>'required|exists:users,id','branch_id'=>'required|exists:branches,id',
            'date'=>'required|date','start_time'=>'required|date_format:H:i','end_time'=>'required|date_format:H:i']);
        $employee=DB::table('users')->find($data['user_id']);
        if ((int)$employee->branch_id !== (int)$data['branch_id']) return back()->withErrors(['shift'=>'Karyawan harus berada di cabang shift.']);
        [$start,$end]=$this->times($data);
        Period::writable($data['branch_id'],$start);
        $this->noOverlap($data['user_id'],$start,$end);
        $id=DB::table('shifts')->insertGetId(['user_id'=>$data['user_id'],'branch_id'=>$data['branch_id'],
            'start_at'=>$start,'end_at'=>$end,'status'=>'draft','created_at'=>now(),'updated_at'=>now()]);
        Audit::record('shift',$id,'create');
        return back()->with('ok','Shift draf dibuat.');
    }

    public function shiftUpdate(Request $request, int $id)
    {
        $this->admin(); $shift=DB::table('shifts')->find($id); abort_unless($shift,404);
        $data=$request->validate(['date'=>'required|date','start_time'=>'required|date_format:H:i','end_time'=>'required|date_format:H:i',
            'reason'=>'required|string|min:5|max:500']);
        Period::writable($shift->branch_id,$shift->start_at);
        [$start,$end]=$this->times($data); $this->noOverlap($shift->user_id,$start,$end,$id);
        abort_if(DB::table('attendances')->where('shift_id',$id)->exists(),409,'Shift dengan absensi harus dikoreksi melalui alur koreksi.');
        DB::table('shifts')->where('id',$id)->update(['start_at'=>$start,'end_at'=>$end,'status'=>'draft',
            'approved_at'=>null,'approved_by'=>null,'version'=>$shift->version+1,'updated_at'=>now()]);
        Audit::record('shift',$id,'revision',$data['reason'],['start_at'=>$shift->start_at,'end_at'=>$shift->end_at],['start_at'=>$start,'end_at'=>$end]);
        return back()->with('ok','Perubahan shift tercatat dan menunggu persetujuan ulang.');
    }

    public function shiftApprove(int $id)
    {
        $this->admin(); $shift=DB::table('shifts')->find($id); abort_unless($shift,404);
        Period::writable($shift->branch_id,$shift->start_at);
        abort_unless($shift->status==='draft',409);
        DB::table('shifts')->where('id',$id)->update(['status'=>'approved','approved_by'=>auth()->id(),
            'approved_at'=>now(),'updated_at'=>now()]);
        Audit::record('shift',$id,'approve');
        return back()->with('ok','Shift disetujui.');
    }

    private function times(array $data): array
    {
        $start=Carbon::parse($data['date'].' '.$data['start_time'],'Asia/Jakarta');
        $end=Carbon::parse($data['date'].' '.$data['end_time'],'Asia/Jakarta');
        if ($end->lte($start)) $end->addDay();
        abort_if($end->diffInHours($start)>24,422,'Durasi shift tidak valid.');
        return [$start->toDateTimeString(),$end->toDateTimeString()];
    }

    private function noOverlap(int $userId,string $start,string $end,?int $except=null): void
    {
        $conflict=DB::table('shifts')->where('user_id',$userId)->where('start_at','<',$end)->where('end_at','>',$start)
            ->when($except,fn($q)=>$q->where('id','!=',$except))->exists();
        abort_if($conflict,409,'Shift karyawan bertumpang tindih.');
    }

    public function settings()
    {
        $this->admin(); $settings=[];
        foreach (Rules::DEFAULTS as $key=>$default) $settings[$key]=Rules::get($key);
        return view('settings',compact('settings'));
    }

    public function settingsSave(Request $request)
    {
        $this->admin();
        $data=$request->validate(['late_grace_minutes'=>'required|integer|min:0|max:120',
            'late_unit_minutes'=>'required|integer|min:1|max:120','late_penalty_per_unit'=>'required|integer|min:0',
            'late_reject_minutes'=>'required|integer|min:1|max:240','checkin_early_minutes'=>'required|integer|min:0|max:240',
            'checkout_late_hours'=>'required|integer|min:1|max:24','overtime_threshold_minutes'=>'required|integer|min:0|max:480',
            'leave_notice_days'=>'required|integer|min:0|max:90','sick_paid_days_per_case'=>'required|integer|min:0|max:30',
            'hourly_divisor'=>'required|integer|min:1|max:240','daily_divisor'=>'required|in:calendar,fixed_30']);
        abort_if($data['late_reject_minutes']<=$data['late_grace_minutes'],422,'Batas alfa harus lebih besar dari toleransi.');
        foreach ($data as $key=>$value) DB::table('settings')->updateOrInsert(['key'=>$key],['value'=>(string)$value,'updated_at'=>now()]);
        Audit::record('settings',1,'update',null,null,$data);
        return back()->with('ok','Aturan diperbarui.');
    }

    public function importForm() { $this->admin(); return view('import'); }

    public function importPreview(Request $request, EmployeeImport $import)
    {
        $this->admin(); $request->validate(['file'=>'required|file|max:5120|mimes:csv,txt,xlsx']);
        $file=$request->file('file'); $ext=strtolower($file->getClientOriginalExtension());
        abort_unless(in_array($ext,['csv','xlsx'],true),422);
        $path=$file->store('imports','local');
        try { $rows=$import->read(Storage::disk('local')->path($path),$ext); }
        catch (\Throwable $e) { Storage::disk('local')->delete($path); return back()->withErrors(['file'=>$e->getMessage()]); }
        if (count($rows)<2 || count($rows)>2001) { Storage::disk('local')->delete($path); return back()->withErrors(['file'=>'File harus berisi 1–2000 baris data.']); }
        session(['import_path'=>$path,'import_ext'=>$ext]);
        $headers=$rows[0]; $sample=array_slice($rows,1,5);
        return view('import',compact('headers','sample'));
    }

    public function importCommit(Request $request, EmployeeImport $import)
    {
        $this->admin(); $path=session('import_path'); $ext=session('import_ext');
        abort_unless($path && Storage::disk('local')->exists($path),409,'Unggah file lagi.');
        $fields=['name','email','branch','position','hired_at','base_salary','active'];
        $map=$request->validate(array_fill_keys($fields,'required|integer|min:0|max:100'));
        abort_if(count(array_unique(array_values($map)))!==count($map),422,'Satu kolom tidak boleh dipakai untuk dua field.');
        $rows=$import->read(Storage::disk('local')->path($path),$ext);
        $result=$import->run($rows,$map);
        Storage::disk('local')->delete($path); session()->forget(['import_path','import_ext']);
        Audit::record('import',0,'complete',null,null,['imported'=>$result['imported'],'failed'=>$result['failed']]);
        return view('import',compact('result'));
    }
}
