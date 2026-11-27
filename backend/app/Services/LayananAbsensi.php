<?php

namespace App\Services;

use App\Enums\Role;
use App\Enums\StatusAbsensi;
use App\Models\Attendance;
use App\Models\MonthlyScore;
use App\Models\Period;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Absensi mandiri (PRD 4, diperbarui sesuai arahan mentor 26 Nov 2026). Memengaruhi skor biller lewat bobot_kehadiran.
 *  1. Absen selalu atas nama akun yang login, untuk hari ini menurut jam server (WIB).
 *  2. Sekali sehari memilih hadir, izin, atau sakit. Pilihan kedua di hari yang sama ditolak 409.
 *  3. Hadir = absen masuk + absen pulang, keduanya hanya diterima dalam radius_absen_m (bawaan 100 m) dari
 *     kantor. Masuk lewat jam_masuk (07.30, tanpa toleransi) dicatat terlambat_menit; pulang sebelum jam_pulang
 *     (16.30) dicatat pulang_cepat_menit. Di luar radius absen ditolak dan jamnya tidak tercatat.
 *  4. Izin dan sakit wajib keterangan dan lampiran (foto atau PDF surat).
 *  5. IP dan perangkat setiap absen disimpan agar Koordinator/Super Admin bisa melihat titip absen.
 *  6. MarkAbsentJob mencatat alpa pukul 23.59 WIB untuk Biller dan Koordinator aktif yang belum absen,
 *     lalu menghitung ulang skor periode yang bulan kerjanya memuat tanggal itu.
 *  7. Koreksi hanya oleh Super Admin dengan alasan wajib, tercatat di audit_logs, dan menghitung ulang skor.
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

    /** @return array<string, mixed> parameter periode bawaan (atau nilai bawaan bila belum ada periode) */
    private static function parameter(): array
    {
        $periode = Period::bawaan();

        return $periode ? app(ParameterSkor::class)->untuk($periode) : ParameterSkor::bawaan();
    }

    /**
     * Lokasi kantor dan radius absen dari parameter periode bawaan.
     *
     * @return array{lat: float|null, lng: float|null, radius: int, ada: bool}
     */
    public static function lokasiKantor(): array
    {
        $p = self::parameter();
        $lat = is_numeric($p['kantor_lat'] ?? null) ? (float) $p['kantor_lat'] : null;
        $lng = is_numeric($p['kantor_lng'] ?? null) ? (float) $p['kantor_lng'] : null;

        return ['lat' => $lat, 'lng' => $lng, 'radius' => (int) ($p['radius_absen_m'] ?? 100), 'ada' => $lat !== null && $lng !== null];
    }

    /**
     * Aturan absensi untuk halaman Absensi Saya.
     *
     * @return array<string, mixed>
     */
    public static function aturan(): array
    {
        $p = self::parameter();

        return [
            'kantor' => self::lokasiKantor(),
            'akurasi_maks_m' => (int) config('sipermon.absensi.akurasi_maks_m'),
            'foto_maks_kb' => (int) config('sipermon.absensi.foto_maks_kb'),
            'bobot_kehadiran' => (float) ($p['bobot_kehadiran'] ?? 0),
            'nilai_terlambat' => (float) ($p['nilai_terlambat'] ?? 75),
            'jam_masuk' => (string) ($p['jam_masuk'] ?? '07:30'),
            'jam_pulang' => (string) ($p['jam_pulang'] ?? '16:30'),
        ];
    }

    /** Jam kerja hari ini, contoh "07:30" → hari ini 07.30 WIB. */
    public static function jamHariIni(string $jam): CarbonImmutable
    {
        [$h, $m] = array_map('intval', explode(':', $jam) + [0, 0]);

        return self::hariIni()->setTime($h, $m);
    }

    /** Jarak dua titik (meter), rumus haversine. */
    public static function jarakMeter(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 2 * 6371000 * asin(min(1, sqrt($a)));
    }

    public static function disk(): string
    {
        return (string) config('sipermon.disk_unggahan');
    }

    /** Ringkasan perangkat dari user agent, contoh "Chrome · Android". */
    public static function ringkasPerangkat(?string $ua): ?string
    {
        if (blank($ua)) {
            return null;
        }
        $os = match (true) {
            str_contains($ua, 'Android') => 'Android',
            str_contains($ua, 'iPhone'), str_contains($ua, 'iPad') => 'iOS',
            str_contains($ua, 'Windows') => 'Windows',
            str_contains($ua, 'Mac OS') => 'macOS',
            str_contains($ua, 'Linux') => 'Linux',
            default => 'Lainnya',
        };
        $browser = match (true) {
            str_contains($ua, 'Edg/') => 'Edge',
            str_contains($ua, 'OPR/') => 'Opera',
            str_contains($ua, 'SamsungBrowser') => 'Samsung Internet',
            str_contains($ua, 'Chrome/') => 'Chrome',
            str_contains($ua, 'Firefox/') => 'Firefox',
            str_contains($ua, 'Safari/') => 'Safari',
            default => 'Browser lain',
        };

        return "{$browser} · {$os}";
    }

    /** @throws ValidationException */
    public function absenSendiri(
        User $user,
        StatusAbsensi $status,
        ?string $keterangan,
        ?float $lat = null,
        ?float $lng = null,
        ?float $akurasi = null,
        ?UploadedFile $lampiran = null,
    ): Attendance {
        abort_unless($user->wajibAbsen(), 403, 'Super Admin tidak wajib absen.');
        if ($status === StatusAbsensi::Alpa) {
            throw ValidationException::withMessages(['status' => 'Pilih hadir, izin, atau sakit.']);
        }
        $hadir = $status === StatusAbsensi::Hadir;
        if (! $hadir && blank($keterangan)) {
            throw ValidationException::withMessages(['keterangan' => 'Tulis keterangan untuk izin atau sakit.']);
        }
        if (! $hadir && ! $lampiran) {
            throw ValidationException::withMessages(['foto' => 'Lampirkan foto atau PDF bukti (mis. surat dokter) untuk izin atau sakit.']);
        }
        abort_if($this->hariIniMilik($user) !== null, 409, 'Anda sudah absen hari ini. Absen hanya sekali sehari.');

        $jarak = $hadir ? $this->periksaRadius($lat, $lng, $akurasi, 'masuk') : null;
        $sekarang = CarbonImmutable::now(config('app.timezone'));
        $terlambat = $hadir ? max(0, (int) floor(self::jamHariIni(self::aturan()['jam_masuk'])->diffInMinutes($sekarang, false))) : null;

        $absen = Attendance::create([
            'user_id' => $user->id,
            'tanggal' => self::hariIni()->toDateString(),
            'status' => $status,
            'absen_at' => $sekarang,
            'terlambat_menit' => $terlambat,
            'lat' => $hadir ? $lat : null,
            'lng' => $hadir ? $lng : null,
            'jarak_m' => $jarak,
            'akurasi_m' => $hadir && $akurasi !== null ? round($akurasi, 1) : null,
            'ip' => request()->ip(),
            'perangkat' => self::ringkasPerangkat(request()->userAgent()),
            'keterangan' => $hadir ? (blank($keterangan) ? null : $keterangan) : $keterangan,
            'foto_path' => $hadir ? null : $this->simpanLampiran($user, $lampiran),
            'sumber' => 'mandiri',
        ]);
        Audit::catat(Audit::ATTEND, $absen, null, [
            'tanggal' => $absen->tanggal->toDateString(), 'status' => $status->value, 'terlambat_menit' => $terlambat,
        ], $user->id);

        return $absen;
    }

    /**
     * Absen pulang untuk hari ini: hanya setelah absen masuk (hadir), sekali, dan dalam radius kantor.
     *
     * @throws ValidationException
     */
    public function absenPulang(User $user, ?float $lat, ?float $lng, ?float $akurasi = null): Attendance
    {
        abort_unless($user->wajibAbsen(), 403, 'Super Admin tidak wajib absen.');
        $absen = $this->hariIniMilik($user);
        if (! $absen || $absen->status !== StatusAbsensi::Hadir || $absen->absen_at === null) {
            throw ValidationException::withMessages(['status' => 'Absen pulang hanya bisa setelah absen masuk (Hadir) hari ini.']);
        }
        abort_if($absen->pulang_at !== null, 409, 'Anda sudah absen pulang hari ini.');

        $jarak = $this->periksaRadius($lat, $lng, $akurasi, 'pulang');
        $sekarang = CarbonImmutable::now(config('app.timezone'));
        $cepat = max(0, (int) ceil($sekarang->diffInMinutes(self::jamHariIni(self::aturan()['jam_pulang']), false)));

        $absen->fill([
            'pulang_at' => $sekarang,
            'pulang_cepat_menit' => $cepat,
            'pulang_lat' => $lat,
            'pulang_lng' => $lng,
            'pulang_jarak_m' => $jarak,
            'pulang_ip' => request()->ip(),
        ])->save();
        Audit::catat(Audit::ATTEND, $absen, null, ['tanggal' => $absen->tanggal->toDateString(), 'pulang' => $sekarang->format('H:i'), 'pulang_cepat_menit' => $cepat], $user->id);

        return $absen;
    }

    /**
     * Absen masuk/pulang hanya dalam radius kantor. Mengembalikan jarak (meter, 1 desimal).
     *
     * @throws ValidationException
     */
    private function periksaRadius(?float $lat, ?float $lng, ?float $akurasi, string $jenis): float
    {
        if ($lat === null || $lng === null) {
            throw ValidationException::withMessages(['lokasi' => "Lokasi belum terbaca. Izinkan akses lokasi di browser; absen {$jenis} hanya bisa dari kantor."]);
        }
        $kantor = self::lokasiKantor();
        if (! $kantor['ada']) {
            throw ValidationException::withMessages(['lokasi' => 'Lokasi kantor belum diatur Super Admin (Periode & Parameter → Kehadiran & Lokasi Absen).']);
        }
        $maksAkurasi = (float) config('sipermon.absensi.akurasi_maks_m');
        if ($akurasi !== null && $akurasi > $maksAkurasi) {
            throw ValidationException::withMessages(['lokasi' => 'Sinyal GPS kurang akurat (±'.round($akurasi).' m). Tunggu sebentar di tempat terbuka, lalu coba lagi. Absen belum tersimpan.']);
        }
        $jarak = self::jarakMeter($kantor['lat'], $kantor['lng'], $lat, $lng);
        if ($jarak > $kantor['radius']) {
            throw ValidationException::withMessages(['lokasi' => 'Belum di dalam radius kantor: Anda berada ±'.number_format($jarak, 0, ',', '.')." m dari kantor (batas {$kantor['radius']} m). Absen tidak tersimpan."]);
        }

        return round($jarak, 1);
    }

    private function simpanLampiran(User $user, UploadedFile $berkas): string
    {
        $hari = self::hariIni();
        $nama = $user->id.'-'.$hari->toDateString().'-'.Str::uuid().'.'.($berkas->guessExtension() ?: 'bin');
        $path = $berkas->storeAs('absensi/'.$hari->format('Y-m'), $nama, ['disk' => self::disk()]);
        if ($path === false) {
            throw ValidationException::withMessages(['foto' => 'Lampiran gagal disimpan. Coba lagi.']);
        }

        return $path;
    }

    /**
     * Hitung ulang skor periode terbuka yang bulan kerjanya (bulan sebelum BLTH) memuat tanggal ini.
     * Periode tanpa skor dilewati karena belum ada berkas yang di-commit.
     */
    public function hitungUlangSkor(CarbonImmutable $tanggal): void
    {
        $periode = Period::kode($tanggal->startOfMonth()->addMonthNoOverflow()->format('Ym'));
        if ($periode && $periode->terbuka() && MonthlyScore::query()->where('period_id', $periode->id)->exists()) {
            app(PenghitungSkor::class)->hitung($periode);
        }
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
        if ($target->biller_id !== null) {
            $this->hitungUlangSkor($tanggal);
        }

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
        $this->hitungUlangSkor($tanggal);

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
            $isi = $a ? collect(self::baris($a))->except(['id', 'tanggal'])->all() : array_fill_keys(array_keys(self::baris(new Attendance)), null);
            unset($isi['id'], $isi['tanggal']);

            return $this->orangInfo($u) + ['attendance_id' => $a?->id, 'status' => $a?->status->value ?? 'belum'] + $isi + ['dikoreksi' => $a?->dikoreksi_oleh !== null];
        })->values();
    }

    /**
     * Rekap bulanan per orang: hadir, terlambat, pulang cepat, izin, sakit, alpa, dan koreksi Super Admin.
     *
     * @param  list<Role>  $peran
     * @return Collection<int, array<string, mixed>>
     */
    public function rekapBulanan(CarbonImmutable $bulan, array $peran): Collection
    {
        $catatan = $this->catatanBulan($bulan)->groupBy('user_id');

        return $this->orang($peran)->map(fn (User $u) => $this->orangInfo($u) + $this->hitung($catatan->get($u->id) ?? collect()))->values();
    }

    /** @return array<string, int> ringkasan bulan ini untuk satu akun */
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
        $path = $a->foto_path;

        return [
            'id' => $a->id,
            'tanggal' => $a->tanggal?->toDateString(),
            'status' => $a->status?->value,
            'absen_at' => $a->absen_at?->toIso8601String(),
            'terlambat_menit' => $a->terlambat_menit,
            'pulang_at' => $a->pulang_at?->toIso8601String(),
            'pulang_cepat_menit' => $a->pulang_cepat_menit,
            'lat' => $a->lat,
            'lng' => $a->lng,
            'jarak_m' => $a->jarak_m,
            'pulang_jarak_m' => $a->pulang_jarak_m,
            'ip' => $a->ip,
            'perangkat' => $a->perangkat,
            'keterangan' => $a->keterangan,
            'foto_url' => $path && $a->id ? route('absensi.foto', $a->id) : null,
            'lampiran_pdf' => $path !== null && str_ends_with(strtolower($path), '.pdf'),
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
        $hadir = $catatan->filter(fn (Attendance $a) => $a->status === StatusAbsensi::Hadir);

        return [
            'hadir' => $hadir->count(),
            'terlambat' => $hadir->filter(fn (Attendance $a) => $a->terlambat_menit > 0)->count(),
            'pulang_cepat' => $hadir->filter(fn (Attendance $a) => $a->pulang_cepat_menit > 0)->count(),
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
