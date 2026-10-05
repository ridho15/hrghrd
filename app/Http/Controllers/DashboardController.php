<?php

namespace App\Http\Controllers;

use App\Support\Access;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $user=auth()->user(); abort_unless($user->active,403);
        $today=now('Asia/Jakarta');
        $shifts=DB::table('shifts as s')->join('branches as b','b.id','=','s.branch_id')
            ->leftJoin('attendances as a','a.shift_id','=','s.id')
            ->where('s.user_id',$user->id)
            ->whereBetween('s.start_at',[$today->copy()->subDay()->startOfDay(),$today->copy()->addDays(14)->endOfDay()])
            ->orderBy('s.start_at')->select('s.*','b.name as branch_name','a.checkin_at','a.checkout_at','a.status as attendance_status')->get();
        $leaves=DB::table('leave_requests')->where('user_id',$user->id)->latest()->limit(5)->get();
        $pending=Access::manager() ? DB::table('leave_requests as r')->join('users as u','u.id','=','r.user_id')
            ->when(!Access::admin(),fn($q)=>$q->where('u.branch_id',$user->branch_id))
            ->where('r.status','pending')->count() : 0;
        $challenge=(string)random_int(100,999); session(['attendance_challenge'=>$challenge]);
        return view('dashboard',compact('shifts','leaves','pending','challenge'));
    }
}
