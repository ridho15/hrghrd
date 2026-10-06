<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BranchResource;
use App\Http\Resources\UserResource;
use App\Models\Branch;
use App\Models\User;
use App\Support\Access;
use Illuminate\Http\Request;

/**
 * @group Administrasi Master Data
 *
 * Endpoint data master karyawan dan cabang untuk manajemen mobile.
 */
class AdminApiController extends Controller
{
    /**
     * Daftar Karyawan
     *
     * Menampilkan daftar karyawan dalam cabang (manager) atau semua karyawan (admin).
     *
     * Query: `search`, `branch_id`, `active` (1|0), `page`, `per_page`
     */
    public function employees(Request $request)
    {
        if (! Access::manager()) {
            return response()->json(['success' => false, 'message' => 'Diperlukan role Manager.'], 403);
        }

        $user = $request->user();
        $query = User::with(['branch', 'position'])
            ->when(! Access::admin(), fn ($q) => $q->where('branch_id', $user->branch_id))
            ->when($request->query('branch_id') && Access::admin(), fn ($q) => $q->where('branch_id', $request->query('branch_id')))
            ->when($request->has('active'), fn ($q) => $q->where('active', filter_var($request->query('active'), FILTER_VALIDATE_BOOLEAN)))
            ->when($request->query('search'), function ($q, $search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy('name');

        $perPage = min((int) ($request->query('per_page', 20)), 100);
        $paginator = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data'    => UserResource::collection($paginator->items()),
            'meta'    => [
                'current_page' => $paginator->currentPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'last_page'    => $paginator->lastPage(),
            ],
        ]);
    }

    /**
     * Daftar Cabang
     *
     * Menampilkan daftar cabang aktif.
     * Hanya dapat diakses oleh Admin atau Manager.
     */
    public function branches(Request $request)
    {
        if (! Access::manager()) {
            return response()->json(['success' => false, 'message' => 'Diperlukan role Manager.'], 403);
        }

        $branches = Branch::where('active', true)
            ->when(! Access::admin(), fn ($q) => $q->where('id', $request->user()->branch_id))
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => BranchResource::collection($branches),
        ]);
    }
}
