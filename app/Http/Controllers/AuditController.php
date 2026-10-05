<?php

namespace App\Http\Controllers;

use App\Support\Access;
use Illuminate\Support\Facades\DB;

class AuditController extends Controller
{
    public function index()
    {
        abort_unless(Access::admin(),403);
        $events=DB::table('audit_logs as a')->leftJoin('users as u','u.id','=','a.actor_id')
            ->orderByDesc('a.id')->limit(200)->select('a.*','u.name as actor_name')->get();
        return view('audit',compact('events'));
    }
}
