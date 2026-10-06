<?php

namespace App\Http\Controllers;

use App\Http\Requests\Admin\BranchStoreRequest;
use App\Http\Requests\Admin\BranchUpdateRequest;
use App\Http\Requests\Admin\ImportCommitRequest;
use App\Http\Requests\Admin\ImportPreviewRequest;
use App\Http\Requests\Admin\PersonStoreRequest;
use App\Http\Requests\Admin\PersonUpdateRequest;
use App\Http\Requests\Admin\PositionStoreRequest;
use App\Http\Requests\Admin\SettingsSaveRequest;
use App\Http\Requests\Admin\ShiftStoreRequest;
use App\Http\Requests\Admin\ShiftUpdateRequest;
use App\Models\Branch;
use App\Models\Position;
use App\Models\Setting;
use App\Models\Shift;
use App\Models\User;
use App\Services\EmployeeImport;
use App\Services\ShiftService;
use App\Support\Access;
use App\Support\Audit;
use App\Support\Rules;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AdminController extends Controller
{
    private function admin(): void
    {
        abort_unless(Access::admin(), 403);
    }

    public function people(Request $request)
    {
        $this->admin();
        $branches = Branch::orderBy('name')->get();
        $positions = Position::orderBy('name')->get();

        $query = User::with(['branch', 'position'])
            ->when($request->query('search'), function ($q, $search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($request->query('branch_id'), fn ($q, $b) => $q->where('branch_id', $b))
            ->when($request->query('role'), fn ($q, $r) => $q->where('role', $r))
            ->when($request->query('position_id'), fn ($q, $p) => $q->where('position_id', $p))
            ->when($request->has('status') && $request->query('status') !== '', fn ($q) => $q->where('active', (int) $request->query('status')))
            ->orderBy('name');

        $people = $query->paginate(10)->withQueryString();

        return view('people', compact('branches', 'positions', 'people'));
    }

    public function branch(BranchStoreRequest $request)
    {
        $data = $request->validated();
        $branch = Branch::create($data + ['qr_secret' => Str::random(64)]);
        Audit::record('branch', $branch->id, 'create');

        return back()->with('ok', 'Cabang dibuat.');
    }

    public function branchUpdate(BranchUpdateRequest $request, int $id)
    {
        $branch = Branch::findOrFail($id);
        $data = $request->validated();
        $before = ['latitude' => $branch->latitude, 'longitude' => $branch->longitude, 'radius_m' => $branch->radius_m];
        $branch->update($data);
        Audit::record('branch', $branch->id, 'update', 'Area absensi diperbarui', $before, $data);

        return back()->with('ok', 'Lokasi cabang diperbarui.');
    }

    public function person(PersonStoreRequest $request)
    {
        $data = $request->validated();
        $data['password'] = Hash::make($data['password']);
        $data['active'] = true;
        $user = User::create($data);
        Audit::record('user', $user->id, 'create');

        return back()->with('ok', 'Karyawan dibuat.');
    }

    public function position(PositionStoreRequest $request)
    {
        $data = $request->validated();
        $position = Position::create($data);
        Audit::record('position', $position->id, 'create');

        return back()->with('ok', 'Jabatan dibuat.');
    }

    public function personUpdate(PersonUpdateRequest $request, int $id)
    {
        $person = User::findOrFail($id);
        $data = $request->validated();

        if (! empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        if (! empty($data['reset_device'])) {
            $data['device_hash'] = null;
        }
        unset($data['reset_device']);

        $person->update($data);
        Audit::record('user', $person->id, 'update', 'Data HR diperbarui; nilai gaji tidak disimpan dalam log');

        return back()->with('ok', 'Data karyawan diperbarui.');
    }

    public function shifts(Request $request)
    {
        abort_unless(Access::manager(), 403);
        $branches = Branch::orderBy('name')->get();
        $people = User::active()->whereIn('role', ['employee', 'manager'])
            ->when(! Access::admin(), fn ($q) => $q->where('branch_id', auth()->user()->branch_id))
            ->orderBy('name')->get();

        $query = Shift::with(['user', 'branch'])
            ->when(! Access::admin(), fn ($q) => $q->where('branch_id', auth()->user()->branch_id))
            ->when($request->query('branch_id') && Access::admin(), fn ($q, $b) => $q->where('branch_id', $b))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('search'), function ($q, $search) {
                $q->whereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%"));
            })
            ->when($request->query('date_from'), fn ($q, $d) => $q->whereDate('start_at', '>=', $d))
            ->when($request->query('date_to'), fn ($q, $d) => $q->whereDate('start_at', '<=', $d))
            ->orderBy('start_at', 'desc');

        $shifts = $query->paginate(10)->withQueryString();

        return view('shifts', compact('branches', 'people', 'shifts'));
    }

    public function shift(ShiftStoreRequest $request, ShiftService $service)
    {
        $data = $request->validated();
        [$start, $end] = $this->times($data);
        $this->noOverlap($data['user_id'], $start, $end);
        $service->create($data);

        return back()->with('ok', 'Shift draf dibuat.');
    }

    public function shiftUpdate(ShiftUpdateRequest $request, int $id, ShiftService $service)
    {
        $shift = Shift::findOrFail($id);
        $data = $request->validated();
        [$start, $end] = $this->times($data);
        $this->noOverlap($shift->user_id, $start, $end, $id);
        abort_if($shift->attendance()->exists(), 409, 'Shift dengan absensi harus dikoreksi melalui alur koreksi.');

        $before = ['start_at' => $shift->start_at->toDateTimeString(), 'end_at' => $shift->end_at->toDateTimeString()];
        $service->update($shift, $data);
        Audit::record('shift', $shift->id, 'revision', $data['reason'], $before, ['start_at' => $start, 'end_at' => $end]);

        return back()->with('ok', 'Perubahan shift tercatat dan menunggu persetujuan ulang.');
    }

    public function shiftApprove(int $id, ShiftService $service)
    {
        $this->admin();
        $shift = Shift::findOrFail($id);
        abort_unless($shift->status === 'draft', 409);
        $service->approve($shift, auth()->user());

        return back()->with('ok', 'Shift disetujui.');
    }

    private function times(array $data): array
    {
        $start = Carbon::parse($data['date'] . ' ' . $data['start_time'], 'Asia/Jakarta');
        $end = Carbon::parse($data['date'] . ' ' . $data['end_time'], 'Asia/Jakarta');
        if ($end->lte($start)) {
            $end->addDay();
        }
        abort_if($end->diffInHours($start) > 24, 422, 'Durasi shift tidak valid.');

        return [$start->toDateTimeString(), $end->toDateTimeString()];
    }

    private function noOverlap(int $userId, string $start, string $end, ?int $except = null): void
    {
        $conflict = Shift::where('user_id', $userId)
            ->where('start_at', '<', $end)
            ->where('end_at', '>', $start)
            ->when($except, fn ($q) => $q->where('id', '!=', $except))
            ->exists();
        abort_if($conflict, 409, 'Shift karyawan bertumpang tindih.');
    }

    public function settings()
    {
        $this->admin();
        $settings = [];
        foreach (Rules::DEFAULTS as $key => $default) {
            $settings[$key] = Rules::get($key);
        }

        return view('settings', compact('settings'));
    }

    public function settingsSave(SettingsSaveRequest $request)
    {
        $data = $request->validated();
        foreach ($data as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => (string) $value]);
        }
        Audit::record('settings', 1, 'update', null, null, $data);

        return back()->with('ok', 'Aturan diperbarui.');
    }

    public function importForm()
    {
        $this->admin();

        return view('import');
    }

    public function importPreview(ImportPreviewRequest $request, EmployeeImport $import)
    {
        $file = $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension());
        abort_unless(in_array($ext, ['csv', 'xlsx'], true), 422);
        $path = $file->store('imports', 'local');

        try {
            $rows = $import->read(Storage::disk('local')->path($path), $ext);
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($path);

            return back()->withErrors(['file' => $e->getMessage()]);
        }

        if (count($rows) < 2 || count($rows) > 2001) {
            Storage::disk('local')->delete($path);

            return back()->withErrors(['file' => 'File harus berisi 1–2000 baris data.']);
        }

        session(['import_path' => $path, 'import_ext' => $ext]);
        $headers = $rows[0];
        $sample = array_slice($rows, 1, 5);

        return view('import', compact('headers', 'sample'));
    }

    public function importCommit(ImportCommitRequest $request, EmployeeImport $import)
    {
        $path = session('import_path');
        $ext = session('import_ext');
        abort_unless($path && Storage::disk('local')->exists($path), 409, 'Unggah file lagi.');

        $map = $request->validated();
        $rows = $import->read(Storage::disk('local')->path($path), $ext);
        $result = $import->run($rows, $map);
        Storage::disk('local')->delete($path);
        session()->forget(['import_path', 'import_ext']);
        Audit::record('import', 0, 'complete', null, null, ['imported' => $result['imported'], 'failed' => $result['failed']]);

        return view('import', compact('result'));
    }
}

