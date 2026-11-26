<?php

namespace App\Http\Controllers\Pengaturan;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Biller;
use App\Models\User;
use App\Services\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/** CRUD akun (Super Admin). Akun Biller wajib ditautkan ke satu baris master biller. */
class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', Rule::enum(Role::class)],
        ]);

        $users = User::query()
            ->with('biller:id,nama,login_acmt,kode_petugas_acmt')
            ->when($filters['q'] ?? null, fn ($q, $t) => $q->where(fn ($w) => $w->whereRaw('LOWER(nama) LIKE ?', ['%'.strtolower($t).'%'])->orWhereRaw('LOWER(username_nip) LIKE ?', ['%'.strtolower($t).'%'])))
            ->when($filters['role'] ?? null, fn ($q, $r) => $q->where('role', $r))
            ->orderByRaw("CASE role WHEN 'super_admin' THEN 0 WHEN 'admin' THEN 1 ELSE 2 END")
            ->orderBy('nama')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (User $u) => [
                'id' => $u->id,
                'username_nip' => $u->username_nip,
                'nama' => $u->nama,
                'role' => $u->role->value,
                'role_label' => $u->role->label(),
                'biller_id' => $u->biller_id,
                'biller' => $u->biller ? "{$u->biller->nama} · {$u->biller->login_acmt}" : null,
                'is_active' => $u->is_active,
                'terkunci' => $u->terkunci(),
                'gagal_login' => $u->gagal_login,
                'last_login_at' => $u->last_login_at?->toIso8601String(),
            ]);

        $terpakai = User::query()->whereNotNull('biller_id')->pluck('biller_id');

        return Inertia::render('SuperAdmin/ManajemenAkun', [
            'users' => $users,
            'filters' => (object) $filters,
            'roleOptions' => Role::options(),
            'jumlah' => (object) User::query()->selectRaw('role, COUNT(*) as n')->groupBy('role')->pluck('n', 'role')->all(),
            'billerOptions' => Biller::query()->orderBy('nama')->get()->map(fn (Biller $b) => [
                'value' => $b->id,
                'label' => "{$b->nama} · {$b->login_acmt} ({$b->kode_petugas_acmt})",
                'terpakai' => $terpakai->contains($b->id),
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validasi($request);
        $user = User::create($data);
        Audit::catat(Audit::CREATE_USER, $user, null, $this->jejak($user));

        return back()->with('success', "Akun {$user->nama} dibuat.");
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $this->validasi($request, $user);
        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }
        if ($user->is($request->user()) && (($data['role'] ?? null) !== $user->role->value || ! ($data['is_active'] ?? true))) {
            return back()->with('error', 'Anda tidak bisa mengubah peran atau menonaktifkan akun sendiri.');
        }

        $sebelum = $this->jejak($user);
        $user->update($data);
        Audit::catat(Audit::UPDATE_USER, $user, $sebelum, $this->jejak($user->refresh()));

        return back()->with('success', "Akun {$user->nama} diperbarui.");
    }

    /** Buka akun yang terkunci karena 5 kali gagal login (UNLOCK_USER). */
    public function unlock(User $user): RedirectResponse
    {
        if (! $user->terkunci()) {
            return back()->with('error', "Akun {$user->nama} tidak sedang terkunci.");
        }
        $user->forceFill(['terkunci_at' => null, 'gagal_login' => 0])->save();
        Audit::catat(Audit::UNLOCK_USER, $user, ['terkunci' => true], ['terkunci' => false]);

        return back()->with('success', "Akun {$user->nama} dibuka. Pengguna bisa masuk lagi.");
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->with('error', 'Anda tidak bisa menghapus akun sendiri.');
        }
        $sebelum = $this->jejak($user);
        $user->delete();
        Audit::catat(Audit::DELETE_USER, 'User', $sebelum, null);

        return back()->with('success', "Akun {$user->nama} dihapus.");
    }

    private function validasi(Request $request, ?User $user = null): array
    {
        $data = $request->validate([
            'username_nip' => ['required', 'string', 'max:60', Rule::unique('users', 'username_nip')->ignore($user?->id)],
            'nama' => ['required', 'string', 'max:100'],
            'role' => ['required', Rule::enum(Role::class)],
            'biller_id' => [
                Rule::requiredIf(fn () => $request->input('role') === Role::Biller->value),
                'nullable', 'integer', Rule::exists('billers', 'id'),
                Rule::unique('users', 'biller_id')->ignore($user?->id),
            ],
            'password' => [$user ? 'nullable' : 'required', 'string', Password::defaults()],
            'is_active' => ['boolean'],
        ], [
            'biller_id.required' => 'Akun Biller wajib ditautkan ke data biller.',
            'biller_id.unique' => 'Biller ini sudah punya akun.',
        ]);

        if ($data['role'] !== Role::Biller->value) {
            $data['biller_id'] = null;
        }

        return $data;
    }

    private function jejak(User $u): array
    {
        return $u->only(['username_nip', 'nama', 'biller_id', 'is_active']) + ['role' => $u->role->value];
    }
}
