<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Support\Access;
use Illuminate\Http\Request;

class AuditController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(Access::admin(), 403);
        $query = AuditLog::with('actor')
            ->when($request->query('search'), function ($q, $search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('reason', 'like', "%{$search}%")
                        ->orWhere('actor_name', 'like', "%{$search}%")
                        ->orWhere('subject_id', 'like', "%{$search}%");
                });
            })
            ->when($request->query('subject_type'), fn ($q, $t) => $q->where('subject_type', $t))
            ->when($request->query('action'), fn ($q, $a) => $q->where('action', $a))
            ->latest('id');

        $events = $query->paginate(10)->withQueryString();

        return view('audit', compact('events'));
    }
}

