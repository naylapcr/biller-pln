<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

/** Unduhan CSV (UTF-8 dengan BOM agar terbaca di Excel). */
class UnduhCsv
{
    /**
     * @param  list<string>  $kepala
     * @param  iterable<list<scalar|null>>  $baris
     */
    public static function kirim(string $nama, array $kepala, iterable $baris): StreamedResponse
    {
        return response()->streamDownload(function () use ($kepala, $baris) {
            $f = fopen('php://output', 'w');
            fwrite($f, "\xEF\xBB\xBF");
            fputcsv($f, $kepala, escape: '');
            foreach ($baris as $b) {
                fputcsv($f, array_map(self::aman(...), $b), escape: '');
            }
            fclose($f);
        }, $nama, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Sel berawalan = + - @ dijadikan teks agar tidak dijalankan sebagai rumus. */
    private static function aman(mixed $v): string
    {
        $v = (string) $v;

        return preg_match('/^[=+\-@\t\r]/', $v) && ! is_numeric($v) ? "'".$v : $v;
    }
}
