<?php

namespace App\Http\Controllers;

use App\Http\Requests\Admin\BranchStoreRequest;
use App\Http\Requests\Admin\BranchUpdateRequest;
use App\Http\Requests\Admin\ImportCommitRequest;
use App\Http\Requests\Admin\ImportPreviewRequest;
use App\Http\Requests\Admin\PersonStoreRequest;
use App\Http\Requests\Admin\PersonUpdateRequest;
use App\Http\Requests\Admin\PositionStoreRequest;
use App\Http\Requests\Admin\PositionUpdateRequest;
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
use Illuminate\Support\Facades\DB;
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

        $search = trim((string) $request->query('search'));
        $searchUsername = ltrim($search, '@');

        $query = User::with([
            'branch',
            'position',
            'shifts' => fn ($q) => $q->latest('start_at')->take(5),
            'leaveRequests' => fn ($q) => $q->latest('id')->take(5),
        ])
            ->when($search !== '', function ($q) use ($search, $searchUsername) {
                $q->where(function ($sub) use ($search, $searchUsername) {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$searchUsername}%");
                });
            })
            ->when($request->filled('branch_id'), fn ($q) => $q->where('branch_id', $request->query('branch_id')))
            ->when($request->filled('role'), fn ($q) => $q->where('role', $request->query('role')))
            ->when($request->filled('position_id'), fn ($q) => $q->where('position_id', $request->query('position_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('active', (int) $request->query('status')))
            ->orderBy('name');

        $people = $query->paginate(10)->withQueryString();

        return view('people', compact('branches', 'positions', 'people'));
    }

    public function branches(Request $request)
    {
        $this->admin();
        $query = Branch::withCount(['users', 'shifts'])
            ->with(['users' => fn ($q) => $q->take(5)])
            ->when($request->query('search'), function ($q, $search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                });
            })
            ->orderBy('name');

        $branches = $query->paginate(10)->withQueryString();

        return view('branches', compact('branches'));
    }

    public function branchCreate()
    {
        $this->admin();

        return view('branches-create');
    }

    public function branchShow(int $id)
    {
        $this->admin();
        $branch = Branch::withCount(['users', 'shifts'])
            ->with([
                'users' => fn ($q) => $q->with('position')->orderBy('name'),
                'shifts' => fn ($q) => $q->with(['user.position', 'attendance'])->latest('start_at')->take(10),
            ])
            ->findOrFail($id);

        return view('branches-show', compact('branch'));
    }

    public function branchEdit(int $id)
    {
        $this->admin();
        $branch = Branch::findOrFail($id);

        return view('branches-edit', compact('branch'));
    }

    public function branch(BranchStoreRequest $request)
    {
        $data = $request->validated();
        $branch = Branch::create($data + ['qr_secret' => Str::random(64)]);
        Audit::record('branch', $branch->id, 'create');

        return redirect()->route('branches.show', $branch->id)->with('ok', "Cabang {$branch->name} berhasil dibuat.");
    }

    public function branchUpdate(BranchUpdateRequest $request, int $id)
    {
        $branch = Branch::findOrFail($id);
        $data = $request->validated();
        $before = ['latitude' => $branch->latitude, 'longitude' => $branch->longitude, 'radius_m' => $branch->radius_m];
        $branch->update($data);
        Audit::record('branch', $branch->id, 'update', 'Area absensi diperbarui', $before, $data);

        return redirect()->route('branches.show', $branch->id)->with('ok', "Data cabang {$branch->name} berhasil diperbarui.");
    }

    public function branchDestroy(int $id)
    {
        $this->admin();
        $branch = Branch::withCount(['users', 'shifts'])->findOrFail($id);
        if ($branch->users_count > 0) {
            return back()->withErrors(['branch' => 'Cabang ' . $branch->name . ' tidak dapat dihapus karena masih menaungi ' . $branch->users_count . ' karyawan aktif. Pindahkan karyawan terlebih dahulu.']);
        }
        if ($branch->shifts_count > 0) {
            return back()->withErrors(['branch' => 'Cabang ' . $branch->name . ' memiliki riwayat jadwal shift dan tidak dapat dihapus demi integritas data audit.']);
        }

        $branchName = $branch->name;
        $branch->delete();
        Audit::record('branch', $id, 'delete', "Cabang {$branchName} dihapus oleh Super Admin.");

        return redirect()->route('branches.index')->with('ok', "Cabang {$branchName} berhasil dihapus.");
    }

    public function positions(Request $request)
    {
        $this->admin();
        $query = Position::withCount('users')
            ->with(['users' => fn ($q) => $q->take(5)])
            ->when($request->query('search'), fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->orderBy('name');

        $positions = $query->paginate(10)->withQueryString();

        return view('positions', compact('positions'));
    }

    public function positionCreate()
    {
        $this->admin();

        return view('positions-create');
    }

    public function positionShow(int $id)
    {
        $this->admin();
        $position = Position::withCount('users')
            ->with([
                'users' => fn ($q) => $q->with('branch')->orderBy('name'),
            ])
            ->findOrFail($id);

        return view('positions-show', compact('position'));
    }

    public function positionEdit(int $id)
    {
        $this->admin();
        $position = Position::findOrFail($id);

        return view('positions-edit', compact('position'));
    }

    public function position(PositionStoreRequest $request)
    {
        $data = $request->validated();
        $position = Position::create($data);
        Audit::record('position', $position->id, 'create');

        return redirect()->route('positions.show', $position->id)->with('ok', "Jabatan {$position->name} berhasil dibuat.");
    }

    public function positionUpdate(PositionUpdateRequest $request, int $id)
    {
        $this->admin();
        $position = Position::findOrFail($id);
        $oldName = $position->name;
        $data = $request->validated();
        $position->update($data);
        Audit::record('position', $position->id, 'update', "Nama jabatan diubah dari {$oldName} menjadi {$position->name}.", ['name' => $oldName], $data);

        return redirect()->route('positions.show', $position->id)->with('ok', "Nama jabatan berhasil diperbarui.");
    }

    public function positionDestroy(int $id)
    {
        $this->admin();
        $position = Position::withCount('users')->findOrFail($id);
        if ($position->users_count > 0) {
            return back()->withErrors(['position' => 'Jabatan ' . $position->name . ' tidak dapat dihapus karena masih digunakan oleh ' . $position->users_count . ' karyawan aktif.']);
        }

        $posName = $position->name;
        $position->delete();
        Audit::record('position', $id, 'delete', "Jabatan {$posName} dihapus oleh Super Admin.");

        return redirect()->route('positions.index')->with('ok', "Jabatan {$posName} berhasil dihapus.");
    }

    public function personCreate()
    {
        $this->admin();
        $branches = Branch::orderBy('name')->get();
        $positions = Position::orderBy('name')->get();

        return view('people-create', compact('branches', 'positions'));
    }

    public function personShow(int $id)
    {
        if (! Access::manager()) {
            abort_unless($id === auth()->id(), 403, 'Anda hanya dapat melihat profil diri sendiri.');
        } elseif (! Access::admin()) {
            abort_unless(Access::employee($id), 403);
        }

        $person = User::with([
            'branch',
            'position',
            'shifts' => fn ($q) => $q->with(['branch', 'attendance'])->latest('start_at')->take(10),
            'leaveRequests' => fn ($q) => $q->latest('id')->take(10),
            'attendances' => fn ($q) => $q->with('shift.branch')->latest('checkin_at')->take(10),
        ])->findOrFail($id);

        return view('people-show', compact('person'));
    }

    public function personEdit(int $id)
    {
        $this->admin();
        $person = User::findOrFail($id);
        $branches = Branch::orderBy('name')->get();
        $positions = Position::orderBy('name')->get();

        return view('people-edit', compact('person', 'branches', 'positions'));
    }

    public function person(PersonStoreRequest $request)
    {
        $data = $request->validated();
        $data['password'] = Hash::make($data['password']);
        $data['active'] = true;
        $user = User::create($data);
        Audit::record('user', $user->id, 'create');

        return redirect()->route('people.show', $user->id)->with('ok', "Karyawan {$user->name} berhasil didaftarkan.");
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

        return redirect()->route('people.show', $person->id)->with('ok', "Data karyawan {$person->name} berhasil diperbarui.");
    }

    public function personToggleStatus(int $id)
    {
        $this->admin();
        $person = User::findOrFail($id);
        if ($person->id === auth()->id()) {
            return back()->withErrors(['person' => 'Anda tidak dapat mengubah status akun Anda sendiri.']);
        }

        $newActive = ! $person->active;
        $person->update([
            'active' => $newActive,
            'ended_at' => $newActive ? null : now('Asia/Jakarta')->toDateString(),
        ]);
        $statusStr = $newActive ? 'diaktifkan' : 'dinonaktifkan';
        Audit::record('user', $person->id, 'status_change', "Status akun karyawan {$person->name} {$statusStr} oleh Super Admin.");

        return back()->with('ok', "Akun {$person->name} berhasil {$statusStr}.");
    }

    public function personResetPassword(int $id)
    {
        $this->admin();
        $person = User::findOrFail($id);
        if ($person->id === auth()->id()) {
            return back()->withErrors(['person' => 'Untuk alasan keamanan, Anda tidak dapat mereset password akun Anda sendiri melalui fitur ini. Silakan gunakan menu ubah kata sandi profil.']);
        }

        $person->update([
            'password' => Hash::make('12345678'),
            'must_change_password' => true,
        ]);

        Audit::record('user', $person->id, 'reset_password', "Password akun {$person->name} direset ke default (12345678) oleh Super Admin.");

        return back()->with('ok', "Password untuk {$person->name} berhasil direset menjadi 12345678. Karyawan wajib mengganti kata sandi saat pertama kali login.");
    }

    public function peopleBulkResetPassword(Request $request)
    {
        $this->admin();
        $validated = $request->validate([
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'integer|exists:users,id',
        ], [
            'user_ids.required' => 'Pilih setidaknya satu karyawan untuk direset.',
            'user_ids.min' => 'Pilih setidaknya satu karyawan untuk direset.',
            'user_ids.*.exists' => 'Data karyawan yang dipilih tidak ditemukan dalam sistem.',
        ]);

        $userIds = array_values(array_filter($validated['user_ids'], fn ($id) => (int) $id !== auth()->id()));

        if (empty($userIds)) {
            return back()->withErrors(['bulk' => 'Tidak ada akun karyawan valid yang dapat direset (akun Anda sendiri dikecualikan).']);
        }

        $users = User::whereIn('id', $userIds)->get();

        DB::transaction(function () use ($userIds, $users) {
            User::whereIn('id', $userIds)->update([
                'password' => Hash::make('12345678'),
                'must_change_password' => true,
            ]);

            foreach ($users as $user) {
                Audit::record('user', $user->id, 'reset_password', "Password akun {$user->name} direset massal ke default (12345678) oleh Super Admin.");
            }
        });

        $count = $users->count();

        return back()->with('ok', "Password untuk {$count} karyawan terpilih berhasil direset menjadi 12345678. Karyawan wajib mengganti kata sandi saat pertama kali login.");
    }

    protected function buildUniqueUsername(User $user): string
    {
        $cleanName = Str::lower(preg_replace('/[^a-zA-Z0-9]/', '', Str::ascii($user->name)));
        if ($cleanName === '') {
            $cleanName = 'karyawan' . $user->id;
        }

        $branch = $user->branch ?: ($user->branch_id ? Branch::find($user->branch_id) : null);
        if ($branch) {
            $branchCode = $branch->code ?: $branch->name;
            $cleanBranch = Str::lower(preg_replace('/[^a-zA-Z0-9]/', '', Str::ascii($branchCode)));
            if ($cleanBranch === '') {
                $cleanBranch = 'cabang';
            }
        } else {
            $cleanBranch = 'pusat';
        }

        $base = "{$cleanName}_{$cleanBranch}";
        $candidate = $base;
        $counter = 1;

        while (User::where('username', $candidate)->where('id', '!=', $user->id)->exists()) {
            $counter++;
            $candidate = "{$base}_{$counter}";
        }

        return $candidate;
    }

    public function personGenerateUsername(int $id)
    {
        $this->admin();
        $person = User::with('branch')->findOrFail($id);
        $oldUsername = $person->username;
        $newUsername = $this->buildUniqueUsername($person);

        $person->update([
            'username' => $newUsername,
        ]);

        Audit::record('user', $person->id, 'generate_username', "Username akun {$person->name} di-generate menjadi '{$newUsername}'.", ['old_username' => $oldUsername], ['new_username' => $newUsername]);

        return back()->with('ok', "Username untuk {$person->name} berhasil di-generate menjadi '{$newUsername}'.");
    }

    public function peopleBulkGenerateUsername(Request $request)
    {
        $this->admin();
        $validated = $request->validate([
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'integer|exists:users,id',
        ], [
            'user_ids.required' => 'Pilih setidaknya satu karyawan untuk di-generate username.',
            'user_ids.min' => 'Pilih setidaknya satu karyawan untuk di-generate username.',
            'user_ids.*.exists' => 'Data karyawan yang dipilih tidak ditemukan dalam sistem.',
        ]);

        $userIds = $validated['user_ids'];
        $users = User::with('branch')->whereIn('id', $userIds)->get();

        DB::transaction(function () use ($users) {
            foreach ($users as $user) {
                $oldUsername = $user->username;
                $newUsername = $this->buildUniqueUsername($user);

                $user->update([
                    'username' => $newUsername,
                ]);

                Audit::record('user', $user->id, 'generate_username', "Username akun {$user->name} di-generate massal menjadi '{$newUsername}'.", ['old_username' => $oldUsername], ['new_username' => $newUsername]);
            }
        });

        $count = $users->count();

        return back()->with('ok', "Username untuk {$count} karyawan terpilih berhasil di-generate / direset ke format standar namakaryawan_cabang.");
    }

    public function personDestroy(int $id)
    {
        $this->admin();
        $person = User::withCount(['attendances', 'payrollLines', 'shifts'])->findOrFail($id);
        if ($person->id === auth()->id()) {
            return back()->withErrors(['person' => 'Anda tidak dapat menghapus akun Anda sendiri.']);
        }

        // Jika memiliki transaksi absensi atau payroll historis, lakukan deaktifasi aman demi integritas audit
        if ($person->attendances_count > 0 || $person->payroll_lines_count > 0) {
            $person->update([
                'active' => false,
                'ended_at' => $person->ended_at ?: now('Asia/Jakarta')->toDateString(),
            ]);
            Audit::record('user', $person->id, 'deactivate', "Akun {$person->name} dinonaktifkan secara aman karena memiliki riwayat audit absensi/payroll.");

            return back()->with('ok', "Karyawan {$person->name} memiliki riwayat data absensi/payroll historis. Akun telah dinonaktifkan secara aman demi integritas audit.");
        }

        $name = $person->name;
        $person->shifts()->delete();
        $person->delete();
        Audit::record('user', $id, 'delete', "Akun karyawan {$name} dihapus secara permanen oleh Super Admin.");

        return back()->with('ok', "Karyawan {$name} berhasil dihapus.");
    }


    public function shifts(Request $request)
    {
        abort_unless(Access::manager(), 403);
        $branches = Branch::orderBy('name')->get();
        $people = User::active()->whereIn('role', ['employee', 'manager'])
            ->when(! Access::admin(), fn ($q) => $q->where('branch_id', auth()->user()->branch_id))
            ->orderBy('name')->get();

        $search = trim((string) $request->query('search'));
        $searchUsername = ltrim($search, '@');

        $query = Shift::with(['user.position', 'user.branch', 'branch', 'attendance'])
            ->when(! Access::admin(), fn ($q) => $q->where('branch_id', auth()->user()->branch_id))
            ->when($request->query('branch_id') && Access::admin(), fn ($q, $b) => $q->where('branch_id', $b))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($search !== '', function ($q) use ($search, $searchUsername) {
                $q->whereHas('user', function ($u) use ($search, $searchUsername) {
                    $u->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$searchUsername}%");
                });
            })
            ->when($request->query('date_from'), fn ($q, $d) => $q->whereDate('start_at', '>=', $d))
            ->when($request->query('date_to'), fn ($q, $d) => $q->whereDate('start_at', '<=', $d))
            ->orderBy('start_at', 'desc');

        $shifts = $query->paginate(10)->withQueryString();

        return view('shifts', compact('branches', 'people', 'shifts'));
    }

    public function shiftCreate()
    {
        abort_unless(Access::manager(), 403);
        $branches = Branch::orderBy('name')->get();
        $people = User::active()->whereIn('role', ['employee', 'manager'])
            ->when(! Access::admin(), fn ($q) => $q->where('branch_id', auth()->user()->branch_id))
            ->orderBy('name')->get();

        return view('shifts-create', compact('branches', 'people'));
    }

    public function shiftShow(int $id)
    {
        abort_unless(Access::manager(), 403);
        $shift = Shift::with(['user.position', 'user.branch', 'branch', 'attendance'])->findOrFail($id);
        if (! Access::admin()) {
            abort_unless($shift->branch_id === auth()->user()->branch_id, 403);
        }

        return view('shifts-show', compact('shift'));
    }

    public function shiftEdit(int $id)
    {
        abort_unless(Access::manager(), 403);
        $shift = Shift::with(['user.position', 'branch'])->findOrFail($id);
        if (! Access::admin()) {
            abort_unless($shift->branch_id === auth()->user()->branch_id, 403);
            abort_if($shift->status === 'approved', 409, 'Shift sudah disetujui, tidak bisa diedit langsung. Silakan batalkan shift dan buat shift baru bila ingin mengubah jadwal.');
        }
        abort_if($shift->attendance()->exists(), 409, 'Shift sudah memiliki rekaman absensi dan tidak dapat diedit langsung.');

        return view('shifts-edit', compact('shift'));
    }

    public function shift(ShiftStoreRequest $request, ShiftService $service)
    {
        $data = $request->validated();

        if (($data['mode'] ?? 'single') === 'recurring' || isset($data['start_date'])) {
            $result = $service->createBatch($data, auth()->user());
            $created = $result['created_count'];
            $skipped = $result['skipped_count'];

            $msg = "Berhasil membuat {$created} jadwal shift.";
            if ($skipped > 0) {
                $msg .= " ({$skipped} jadwal dilewati karena sudah ada shift yang tumpang tindih).";
            }

            return redirect()->route('shifts')->with('ok', $msg);
        }

        [$start, $end] = $this->times($data);
        $this->noOverlap($data['user_id'], $start, $end);
        $shift = $service->create($data);

        return redirect()->route('shifts.show', $shift->id)->with('ok', 'Shift draf berhasil dibuat.');
    }

    public function shiftUpdate(ShiftUpdateRequest $request, int $id, ShiftService $service)
    {
        $shift = Shift::findOrFail($id);
        $data = $request->validated();
        [$start, $end] = $this->times($data);
        $this->noOverlap($shift->user_id, $start, $end, $id);
        abort_if($shift->attendance()->exists(), 409, 'Shift dengan absensi harus dikoreksi melalui alur koreksi.');

        $isApprovedBefore = $shift->status === 'approved';
        $before = ['start_at' => $shift->start_at->toDateTimeString(), 'end_at' => $shift->end_at->toDateTimeString()];
        $service->update($shift, $data);
        Audit::record('shift', $shift->id, 'revision', $data['reason'], $before, ['start_at' => $start, 'end_at' => $end]);

        $msg = $isApprovedBefore
            ? 'Perubahan jadwal shift berhasil disesuaikan dan langsung berlaku.'
            : 'Perubahan shift tercatat dan menunggu persetujuan ulang.';

        return redirect()->route('shifts.show', $shift->id)->with('ok', $msg);
    }

    public function shiftApprove(int $id, ShiftService $service)
    {
        $this->admin();
        $shift = Shift::findOrFail($id);
        abort_unless($shift->status === 'draft', 409);
        $service->approve($shift, auth()->user());

        return back()->with('ok', 'Shift disetujui.');
    }

    public function shiftDestroy(int $id)
    {
        abort_unless(Access::manager(), 403);
        $shift = Shift::with(['attendance', 'user'])->findOrFail($id);
        if (! Access::admin()) {
            abort_unless($shift->branch_id === auth()->user()->branch_id, 403);
        }

        if ($shift->attendance()->exists()) {
            return back()->withErrors(['shift' => 'Shift yang sudah memiliki rekaman absensi tidak dapat dihapus. Silakan gunakan alur koreksi absensi.']);
        }

        $date = $shift->start_at->translatedFormat('d M Y');
        $userName = $shift->user?->name ?? 'Karyawan';
        $shift->delete();
        Audit::record('shift', $id, 'delete', "Jadwal shift {$userName} pada {$date} dibatalkan dan dihapus.");

        return redirect()->route('shifts')->with('ok', "Jadwal shift untuk {$userName} berhasil dibatalkan.");
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

