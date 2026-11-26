<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Memeriksa sambungan ke Supabase tanpa mengubah data: ekstensi PHP, koneksi PostgreSQL (SSL, zona
 * waktu), status migrasi dan Row Level Security, lalu menulis, membaca, dan menghapus satu berkas uji
 * di bucket unggahan. Rahasia (kata sandi, kunci) tidak pernah ditampilkan.
 */
class CekSupabase extends Command
{
    protected $signature = 'sipermon:cek-supabase {--tanpa-storage : Lewati uji Supabase Storage}';

    protected $description = 'Periksa koneksi database dan Storage Supabase';

    private int $gagal = 0;

    public function handle(): int
    {
        $this->periksa('Ekstensi pdo_pgsql', fn () => extension_loaded('pdo_pgsql') ? 'aktif' : throw new \RuntimeException('Aktifkan extension=pdo_pgsql di php.ini'));

        $koneksi = config('database.default');
        $tersambung = false;
        $this->periksa('DB_CONNECTION', fn () => $koneksi === 'pgsql' ? 'pgsql' : throw new \RuntimeException("DB_CONNECTION={$koneksi}; untuk Supabase harus pgsql"));

        if ($koneksi === 'pgsql') {
            $this->periksa('Host database', fn () => config('database.connections.pgsql.host').':'.config('database.connections.pgsql.port'));
            $tersambung = $this->periksa('Koneksi PostgreSQL', function () {
                $v = DB::selectOne('SELECT version() AS v, current_user AS u, current_setting(\'TimeZone\') AS tz');

                return Str::before($v->v, ' on ')." · user {$v->u} · zona {$v->tz}";
            });
            if (! $tersambung) {
                $this->line('    Periksa DB_HOST, DB_PORT (5432), DB_USERNAME (postgres.<project-ref>), dan DB_PASSWORD di backend/.env.');
            }
        }

        if ($koneksi === 'pgsql' && $tersambung) {
            $this->periksa('SSL', function () {
                // Lewat pooler, pg_stat_ssl menggambarkan sambungan pooler → database, bukan aplikasi → pooler.
                // Yang menjamin enkripsi aplikasi → pooler adalah sslmode=require: libpq menolak sambungan tanpa TLS.
                $mode = (string) config('database.connections.pgsql.sslmode');

                return in_array($mode, ['require', 'verify-ca', 'verify-full'], true)
                    ? "sslmode={$mode} (aplikasi → server terenkripsi)"
                    : throw new \RuntimeException("sslmode={$mode}; set DB_SSLMODE=require");
            });
            $this->periksa('Tabel SIPERMON', function () {
                $wajib = ['users', 'billers', 'periods', 'upload_batches', 'monthly_scores', 'audit_logs'];
                $ada = array_filter($wajib, fn ($t) => Schema::hasTable($t));

                return count($ada) === count($wajib) ? 'lengkap' : 'belum dimigrasi (jalankan php artisan migrate --force)';
            });
            $this->periksa('Row Level Security', function () {
                if (! Schema::hasTable('billers')) {
                    return 'dicek setelah migrasi';
                }
                $tanpa = collect(DB::select("SELECT c.relname FROM pg_class c JOIN pg_namespace n ON n.oid = c.relnamespace
                    WHERE n.nspname = 'public' AND c.relkind = 'r' AND NOT c.relrowsecurity"))->pluck('relname');

                return $tanpa->isEmpty() ? 'aktif di semua tabel public' : throw new \RuntimeException('Belum aktif di: '.$tanpa->implode(', '));
            });
        }

        if (! $this->option('tanpa-storage')) {
            $disk = (string) config('sipermon.disk_unggahan');
            $driver = config("filesystems.disks.{$disk}.driver");
            $this->periksa('Disk unggahan', fn () => "{$disk} ({$driver})".($driver === 's3' ? ' · bucket '.config("filesystems.disks.{$disk}.bucket") : ' · bukan Supabase Storage'));
            if ($driver === 's3') {
                $this->periksa('Tulis, baca, hapus berkas uji', function () use ($disk) {
                    $path = '_cek/'.Str::uuid().'.txt';
                    $s = Storage::disk($disk);
                    if (! $s->put($path, 'cek SIPERMON')) {
                        throw new \RuntimeException('Gagal menulis; periksa AWS_* dan bucket');
                    }
                    $isi = $s->get($path);
                    $url = $s->temporaryUrl($path, now()->addMinute());
                    $s->delete($path);

                    return $isi === 'cek SIPERMON' && str_starts_with($url, 'https://') ? 'berhasil (URL bertanda tangan terbentuk)' : throw new \RuntimeException('Isi berkas tidak cocok');
                });
            }
        }

        $this->newLine();
        $this->gagal === 0 ? $this->info('Semua pemeriksaan lolos.') : $this->error("{$this->gagal} pemeriksaan gagal.");

        return $this->gagal === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function periksa(string $label, callable $fn): bool
    {
        try {
            $this->line(sprintf('  <info>✓</info> %-30s %s', $label, $fn()));

            return true;
        } catch (Throwable $e) {
            $this->gagal++;
            // Pesan PDO bisa memuat nama host; kata sandi tidak pernah ada di pesan galat PDO.
            $this->line(sprintf('  <error>✕</error> %-30s %s', $label, Str::limit(Str::before($e->getMessage(), ' (Connection:'), 220)));

            return false;
        }
    }
}
