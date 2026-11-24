<?php

namespace App\Services;

use App\Enums\StatusAbsensi;
use App\Models\Attendance;
use App\Models\Biller;
use App\Models\Period;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Absensi harian biller.
 *  - Biller mengisi sendiri untuk hari ini: hadir, izin, atau sakit.
 *  - Hari kerja (parameter absensi_hari_kerja, di luar absensi_hari_libur) yang sudah lewat
 *    tanpa absensi dihitung alpa. Hari ini belum dihitung alpa sampai harinya berakhir.
 *  - Koordinator Admin bisa mengisi atau mengoreksi absensi biller mana pun, termasuk alpa.
 *  - Denda = jumlah alpa × denda_alpa_per_hari (parameter periode).
 * Rentang tanggal yang dinilai per periode = absensi_mulai … absensi_selesai (bawaan bulan BLTH).
 */
class LayananAbsensi
{
    public function __construct(private readonly ParameterSkor $parameter) {}

    public static function hariIni(): CarbonImmutable
    {
        return CarbonImmutable::now(config('app.timezone'))->startOfDay();
    }

    /** @throws ValidationException */
    public function absenSendiri(User $user, StatusAbsensi $status, ?string $keterangan): Attendance
    {
        $biller = $user->biller ?? throw ValidationException::withMessages(['status' => 'Akun ini belum ditautkan ke data biller.']);
        if ($status === StatusAbsensi::Alpa) {
            throw ValidationException::withMessages(['status' => 'Pilih hadir, izin, atau sakit.']);
        }
        if ($status !== StatusAbsensi::Hadir && blank($keterangan)) {
            throw ValidationException::withMessages(['keterangan' => 'Tulis keterangan untuk izin atau sakit.']);
        }

        $hariIni = self::hariIni();
        $ada = Attendance::query()->where('biller_id', $biller->id)->whereDate('tanggal', $hariIni->toDateString())->first();
        if ($ada && $ada->sumber !== 'biller') {
            throw ValidationException::withMessages(['status' => 'Absensi hari ini sudah diisi Koordinator Admin. Hubungi koordinator bila perlu diubah.']);
        }

        $absen = $this->simpan($biller, $hariIni, $status, $keterangan, 'biller', $user);
        Audit::catat('absen', $absen, $ada ? ['status' => $ada->status->value] : null, ['tanggal' => $hariIni->toDateString(), 'status' => $status->value], $user->id);

        return $absen;
    }

    /**
     * Koreksi oleh Koordinator Admin. $status null = hapus (kembali "belum absen").
     *
     * @throws ValidationException
     */
    public function atur(User $oleh, Biller $biller, CarbonImmutable $tanggal, ?StatusAbsensi $status, ?string $keterangan): void
    {
        if ($tanggal->greaterThan(self::hariIni())) {
            throw ValidationException::withMessages(['tanggal' => 'Absensi tanggal yang akan datang belum bisa diisi.']);
        }
        $periodeTutup = Period::query()->where('status', 'ditutup')
            ->whereDate('absensi_mulai', '<=', $tanggal->toDateString())->whereDate('absensi_selesai', '>=', $tanggal->toDateString())->first();
        if ($periodeTutup) {
            throw ValidationException::withMessages(['tanggal' => "Tanggal ini termasuk periode {$periodeTutup->kode} yang sudah ditutup."]);
        }

        $ada = Attendance::query()->where('biller_id', $biller->id)->whereDate('tanggal', $tanggal->toDateString())->first();
        $sebelum = $ada ? ['status' => $ada->status->value, 'sumber' => $ada->sumber] : null;

        if ($status === null) {
            $ada?->delete();
            $this->hitungUlang($tanggal);
        } else {
            $this->simpan($biller, $tanggal, $status, $keterangan, 'admin', $oleh);
        }

        Audit::catat('koreksi_absensi', $biller, $sebelum, ['tanggal' => $tanggal->toDateString(), 'status' => $status?->value, 'keterangan' => $keterangan], $oleh->id);
    }

    private function simpan(Biller $biller, CarbonImmutable $tanggal, StatusAbsensi $status, ?string $keterangan, string $sumber, User $oleh): Attendance
    {
        $absen = Attendance::query()->where('biller_id', $biller->id)->whereDate('tanggal', $tanggal->toDateString())->first()
            ?? new Attendance(['biller_id' => $biller->id, 'tanggal' => $tanggal->toDateString()]);
        $absen->fill(['status' => $status, 'keterangan' => $keterangan, 'sumber' => $sumber, 'dicatat_oleh' => $oleh->id])->save();

        $this->hitungUlang($tanggal);

        return $absen;
    }

    private function hitungUlang(CarbonImmutable $tanggal): void
    {
        if ($periode = Period::untukTanggal($tanggal)) {
            app(PenghitungSkor::class)->hitung($periode);
        }
    }

    /**
     * Tanggal kerja di rentang absensi periode (sampai batas $sampai bila diberikan).
     *
     * @return list<string> Y-m-d
     */
    public function hariKerja(Period $periode, ?array $aturan = null, ?CarbonImmutable $sampai = null): array
    {
        $aturan ??= $this->parameter->untuk($periode);
        if (! $periode->absensi_mulai || ! $periode->absensi_selesai) {
            return [];
        }
        $kerja = array_map('intval', (array) ($aturan['absensi_hari_kerja'] ?? []));
        $libur = array_flip((array) ($aturan['absensi_hari_libur'] ?? []));
        $akhir = $sampai ? $periode->absensi_selesai->min($sampai) : $periode->absensi_selesai;

        $hasil = [];
        if ($akhir->lessThan($periode->absensi_mulai)) {
            return $hasil;
        }
        foreach (CarbonPeriod::create($periode->absensi_mulai, $akhir) as $hari) {
            if (in_array($hari->isoWeekday(), $kerja, true) && ! isset($libur[$hari->toDateString()])) {
                $hasil[] = $hari->toDateString();
            }
        }

        return $hasil;
    }

    /**
     * Rekap per biller: jumlah hadir, izin, sakit, dan alpa (dicatat + hari kerja lewat tanpa absensi).
     *
     * @param  iterable<int>|null  $billerIds  null = semua biller aktif
     * @return Collection<int, array{hadir: int, izin: int, sakit: int, alpa: int, alpa_tanpa_absen: int, hari_kerja: int}>
     */
    public function rekap(Period $periode, ?iterable $billerIds = null, ?array $aturan = null): Collection
    {
        $aturan ??= $this->parameter->untuk($periode);
        // Hari kerja yang sudah lewat (hari ini belum dihitung alpa).
        $lewat = $this->hariKerja($periode, $aturan, self::hariIni()->subDay());
        $billerIds = collect($billerIds ?? Biller::query()->aktif()->pluck('id'));

        $catatan = Attendance::query()
            ->whereIn('biller_id', $billerIds)
            ->whereDate('tanggal', '>=', $periode->absensi_mulai?->toDateString())
            ->whereDate('tanggal', '<=', $periode->absensi_selesai?->toDateString())
            ->get(['biller_id', 'tanggal', 'status'])
            ->groupBy('biller_id');

        return $billerIds->mapWithKeys(function ($id) use ($catatan, $lewat) {
            $milik = $catatan->get($id) ?? collect();
            $tercatat = $milik->map(fn (Attendance $a) => $a->tanggal->toDateString())->flip();
            $tanpaAbsen = count(array_filter($lewat, fn ($t) => ! isset($tercatat[$t])));
            $hitung = fn (StatusAbsensi $s) => $milik->where('status', $s)->count();

            return [$id => [
                'hadir' => $hitung(StatusAbsensi::Hadir),
                'izin' => $hitung(StatusAbsensi::Izin),
                'sakit' => $hitung(StatusAbsensi::Sakit),
                'alpa' => $hitung(StatusAbsensi::Alpa) + $tanpaAbsen,
                'alpa_tanpa_absen' => $tanpaAbsen,
                'hari_kerja' => count($lewat),
            ]];
        });
    }

    /**
     * Status setiap tanggal di rentang absensi untuk satu biller.
     *
     * @return list<array{tanggal: string, status: string, keterangan: string|null, sumber: string|null}>
     */
    public function harian(Period $periode, Biller $biller): array
    {
        $aturan = $this->parameter->untuk($periode);
        if (! $periode->absensi_mulai || ! $periode->absensi_selesai) {
            return [];
        }
        $kerja = array_flip($this->hariKerja($periode, $aturan));
        $hariIni = self::hariIni();
        $catatan = Attendance::query()->where('biller_id', $biller->id)
            ->whereDate('tanggal', '>=', $periode->absensi_mulai->toDateString())
            ->whereDate('tanggal', '<=', $periode->absensi_selesai->toDateString())
            ->get()->keyBy(fn (Attendance $a) => $a->tanggal->toDateString());

        $hasil = [];
        foreach (CarbonPeriod::create($periode->absensi_mulai, $periode->absensi_selesai) as $hari) {
            $t = $hari->toDateString();
            $a = $catatan->get($t);
            $hasil[] = [
                'tanggal' => $t,
                'status' => match (true) {
                    $a !== null => $a->status->value,
                    ! isset($kerja[$t]) => 'libur',
                    $hari->lessThan($hariIni) => 'alpa',
                    default => 'belum',
                },
                'keterangan' => $a?->keterangan,
                'sumber' => $a?->sumber,
            ];
        }

        return $hasil;
    }
}
