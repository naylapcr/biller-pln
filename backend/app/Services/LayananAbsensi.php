<?php

namespace App\Services;

use App\Enums\Role;
use App\Enums\StatusAbsensi;
use App\Models\Attendance;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Absensi mandiri (PRD 4). Tidak lewat unggahan dan tidak memengaruhi skor.
 *  1. Absen selalu atas nama akun yang login, untuk hari ini menurut jam server (WIB).
 *  2. Sekali sehari: hadir, izin, atau sakit. Absen kedua di hari yang sama ditolak 409.
 *  3. Izin dan sakit wajib disertai keterangan.
 *  4. Saat hadir, lokasi browser disimpan bila diizinkan; bila tidak, lokasi kosong.
 *  5. MarkAbsentJob mencatat alpa pukul 23.59 WIB untuk Biller dan Koordinator aktif yang belum absen.
 *  6. Koreksi hanya oleh Super Admin dengan alasan wajib, tercatat di audit_logs.
 */
class LayananAbsensi
{
    /** Tanggal hari ini dalam WIB (timezone aplikasi), bukan tanggal UTC. */
    public static function hariIni(): CarbonImmutable
    {
        return CarbonImmutable::now(config('app.timezone'))->startOfDay();
    }

    public static function hariKerja(CarbonImmutable $tanggal): bool
    {
        return in_array($tanggal->isoWeekday(), (array) config('sipermon.absensi.hari_kerja'), true)
            && ! in_array($tanggal->toDateString(), (array) config('sipermon.absensi.hari_libur'), true);
    }

    public function hariIniMilik(User $user): ?Attendance
    {
        return Attendance::query()->where('user_id', $user->id)->whereDate('tanggal', self::hariIni()->toDateString())->first();
    }

    /** @throws ValidationException */
    public function absenSendiri(User $user, StatusAbsensi $status, ?string $keterangan, ?float $lat = null, ?float $lng = null): Attendance
    {
        abort_unless($user->wajibAbsen(), 403, 'Super Admin tidak wajib absen.');
        if ($status === StatusAbsensi::Alpa) {
            throw ValidationException::withMessages(['status' => 'Pilih hadir, izin, atau sakit.']);
        }
        if ($status !== StatusAbsensi::Hadir && blank($keterangan)) {
            throw ValidationException::withMessages(['keterangan' => 'Tulis keterangan untuk izin atau sakit.']);
        }
        abort_if($this->hariIniMilik($user) !== null, 409, 'Anda sudah absen hari ini. Absen hanya sekali sehari.');

        $hadir = $status === StatusAbsensi::Hadir;
        $absen = Attendance::create([
            'user_id' => $user->id,
            'tanggal' => self::hariIni()->toDateString(),
            'status' => $status,
            'absen_at' => now(),
            'lat' => $hadir ? $lat : null,
            'lng' => $hadir ? $lng : null,
            'keterangan' => $hadir ? (blank($keterangan) ? null : $keterangan) : $keterangan,
            'sumber' => 'mandiri',
        ]);
        Audit::catat(Audit::ATTEND, $absen, null, ['tanggal' => $absen->tanggal->toDateString(), 'status' => $status->value], $user->id);

        return $absen;
    }

    /**
     * Koreksi Super Admin: ubah status (atau isi bila belum ada) dengan alasan wajib.
     *
     * @throws ValidationException
     */
    public function koreksi(User $oleh, User $target, CarbonImmutable $tanggal, StatusAbsensi $status, string $alasan, ?string $keterangan = null): Attendance
    {
        abort_unless($oleh->isSuperAdmin(), 403, 'Hanya Super Admin yang bisa mengoreksi absensi.');
        if (! $target->wajibAbsen()) {
            throw ValidationException::withMessages(['user_id' => 'Absensi hanya untuk Biller dan Koordinator Admin.']);
        }
        if ($tanggal->greaterThan(self::hariIni())) {
            throw ValidationException::withMessages(['tanggal' => 'Absensi tanggal yang akan datang belum bisa dikoreksi.']);
        }
        if (blank($alasan)) {
            throw ValidationException::withMessages(['alasan_diubah' => 'Alasan koreksi wajib diisi.']);
        }

        $absen = Attendance::query()->where('user_id', $target->id)->whereDate('tanggal', $tanggal->toDateString())->first()
            ?? new Attendance(['user_id' => $target->id, 'tanggal' => $tanggal->toDateString(), 'sumber' => 'sistem']);
        $sebelum = $absen->exists ? ['status' => $absen->status->value, 'keterangan' => $absen->keterangan] : ['status' => 'belum_absen'];

        $absen->fill([
            'status' => $status,
            'keterangan' => $keterangan ?? $absen->keterangan,
            'dikoreksi_oleh' => $oleh->id,
            'alasan_diubah' => $alasan,
        ])->save();

        Audit::catat(Audit::CORRECT_ATT, $absen, $sebelum, [
            'user' => $target->username_nip, 'tanggal' => $tanggal->toDateString(), 'status' => $status->value, 'alasan' => $alasan,
        ], $oleh->id);

        return $absen;
    }

    /**
     * Catat alpa (sumber = sistem) untuk Biller dan Koordinator aktif yang belum absen pada hari kerja ini.
     *
     * @return int jumlah akun yang dicatat alpa
     */
    public function tandaiAlpa(CarbonImmutable $tanggal): int
    {
        if (! self::hariKerja($tanggal)) {
            return 0;
        }
        $sudah = Attendance::query()->whereDate('tanggal', $tanggal->toDateString())->pluck('user_id');
        $belum = User::query()->where('is_active', true)
            ->whereIn('role', [Role::Biller->value, Role::Admin->value])
            ->whereNotIn('id', $sudah)->pluck('id');

        $sekarang = now();
        foreach ($belum->chunk(200) as $ids) {
            Attendance::insert($ids->map(fn ($id) => [
                'user_id' => $id,
                'tanggal' => $tanggal->toDateString(),
                'status' => StatusAbsensi::Alpa->value,
                'sumber' => 'sistem',
                'created_at' => $sekarang,
                'updated_at' => $sekarang,
            ])->all());
        }

        return $belum->count();
    }

    /**
     * Pantauan harian. Koordinator melihat semua biller; Super Admin juga melihat Koordinator.
     *
     * @param  list<Role>  $peran
     * @return Collection<int, array<string, mixed>>
     */
    public function harian(CarbonImmutable $tanggal, array $peran): Collection
    {
        $catatan = Attendance::query()->whereDate('tanggal', $tanggal->toDateString())->get()->keyBy('user_id');

        return $this->orang($peran)->map(function (User $u) use ($catatan) {
            $a = $catatan->get($u->id);

            return $this->orangInfo($u) + [
                'attendance_id' => $a?->id,
                'status' => $a?->status->value ?? 'belum',
                'absen_at' => $a?->absen_at?->toIso8601String(),
                'lat' => $a?->lat,
                'lng' => $a?->lng,
                'keterangan' => $a?->keterangan,
                'sumber' => $a?->sumber,
                'dikoreksi' => $a?->dikoreksi_oleh !== null,
            ];
        })->values();
    }

    /**
     * Rekap bulanan per orang: jumlah hadir, izin, sakit, alpa, dan koreksi Super Admin.
     *
     * @param  list<Role>  $peran
     * @return Collection<int, array<string, mixed>>
     */
    public function rekapBulanan(CarbonImmutable $bulan, array $peran): Collection
    {
        $catatan = $this->catatanBulan($bulan)->groupBy('user_id');

        return $this->orang($peran)->map(fn (User $u) => $this->orangInfo($u) + $this->hitung($catatan->get($u->id) ?? collect()))->values();
    }

    /** @return array{hadir: int, izin: int, sakit: int, alpa: int, koreksi: int} ringkasan bulan ini untuk satu akun */
    public function ringkasan(User $user, CarbonImmutable $bulan): array
    {
        return $this->hitung($this->catatanBulan($bulan)->where('user_id', $user->id));
    }

    /** @return Collection<int, array<string, mixed>> riwayat absensi satu akun di bulan ini, terbaru di atas */
    public function riwayat(User $user, CarbonImmutable $bulan): Collection
    {
        return $this->catatanBulan($bulan)->where('user_id', $user->id)->sortByDesc(fn (Attendance $a) => $a->tanggal)
            ->map(fn (Attendance $a) => self::baris($a))->values();
    }

    /** @return array<string, mixed> */
    public static function baris(Attendance $a): array
    {
        return [
            'id' => $a->id,
            'tanggal' => $a->tanggal->toDateString(),
            'status' => $a->status->value,
            'absen_at' => $a->absen_at?->toIso8601String(),
            'lat' => $a->lat,
            'lng' => $a->lng,
            'keterangan' => $a->keterangan,
            'sumber' => $a->sumber,
            'dikoreksi' => $a->dikoreksi_oleh !== null,
            'alasan_diubah' => $a->alasan_diubah,
        ];
    }

    /** @return Collection<int, Attendance> */
    private function catatanBulan(CarbonImmutable $bulan): Collection
    {
        return Attendance::query()
            ->whereDate('tanggal', '>=', $bulan->startOfMonth()->toDateString())
            ->whereDate('tanggal', '<=', $bulan->endOfMonth()->toDateString())
            ->get();
    }

    /** @param  Collection<int, Attendance>  $catatan */
    private function hitung(Collection $catatan): array
    {
        $n = fn (StatusAbsensi $s) => $catatan->filter(fn (Attendance $a) => $a->status === $s)->count();

        return [
            'hadir' => $n(StatusAbsensi::Hadir),
            'izin' => $n(StatusAbsensi::Izin),
            'sakit' => $n(StatusAbsensi::Sakit),
            'alpa' => $n(StatusAbsensi::Alpa),
            'koreksi' => $catatan->whereNotNull('dikoreksi_oleh')->count(),
        ];
    }

    /** @param  list<Role>  $peran */
    private function orang(array $peran): Collection
    {
        return User::query()->where('is_active', true)
            ->whereIn('role', array_map(fn (Role $r) => $r->value, $peran))
            ->with('biller:id,nip,nama,zona_wilayah,kode_petugas_acmt')
            ->orderByRaw("CASE role WHEN 'admin' THEN 0 ELSE 1 END")->orderBy('nama')
            ->get();
    }

    private function orangInfo(User $u): array
    {
        return [
            'user_id' => $u->id,
            'nama' => $u->nama,
            'nip' => $u->biller?->nip ?? $u->username_nip,
            'username' => $u->username_nip,
            'peran' => $u->role->value,
            'peran_label' => $u->role->label(),
            'wilayah' => $u->biller?->zona_wilayah,
        ];
    }
}
