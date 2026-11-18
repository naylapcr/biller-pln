<?php

use App\Parsers\Nilai;
use App\Parsers\NilaiTidakValid;

test('WAKTU berbentuk teks hari - jam dan nilai waktu Excel disimpan sebagai detik', function () {
    expect(Nilai::durasi('4 - 06:03:51'))->toBe(367431)
        ->and(Nilai::durasi(14638 / 86400))->toBe(14638)   // 04:03:58 sebagai nilai waktu Excel
        ->and(Nilai::durasi('16:44:28'))->toBe(60268)
        ->and(Nilai::durasi(0))->toBe(0)
        ->and(Nilai::durasi(null))->toBeNull();
});

test('IDPEL yang terbaca float disimpan sebagai teks 12 digit', function () {
    expect(Nilai::kode(181101389783.0, 12))->toBe('181101389783')
        ->and(Nilai::kode('181101389783.0', 12))->toBe('181101389783')
        ->and(Nilai::kode(1234, 12))->toBe('000000001234');
});

test('koordinat prabayar tanpa titik desimal dinormalisasi', function () {
    expect(Nilai::koordinat(101444303, 5159556))->toBe([0.5159556, 101.444303])
        ->and(Nilai::koordinat(1014214288, 4750746))->toBe([0.4750746, 101.4214288])
        ->and(Nilai::koordinat('101.5', '0.45'))->toBe([0.45, 101.5])
        ->and(Nilai::koordinat(null, null))->toBe([null, null]);
});

test('tanggal ACMT dari teks dan angka seri Excel dibaca sebagai WIB', function () {
    expect(Nilai::waktu('26 Oct 2026 13.42')->format('Y-m-d H:i:s P'))->toBe('2026-10-26 13:42:00 +07:00')
        ->and(Nilai::waktu('23/10/2026 7:28:12')->format('Y-m-d H:i:s'))->toBe('2026-10-23 07:28:12')
        ->and(Nilai::waktu(46318.31125)->format('Y-m-d H:i:s'))->toBe('2026-10-23 07:28:12');
});

test('% BACA berupa teks "100.0" maupun angka diterima', function () {
    expect(Nilai::desimal('100.0'))->toBe(100.0)
        ->and(Nilai::bulat('204'))->toBe(204)
        ->and(Nilai::bulat(204.0))->toBe(204);
});

test('nilai yang tidak bisa diurai melempar NilaiTidakValid', function (callable $fn) {
    expect($fn)->toThrow(NilaiTidakValid::class);
})->with([
    'angka' => [fn () => Nilai::bulat('dua ratus')],
    'tanggal' => [fn () => Nilai::waktu('31/02/2026 08:00:00')],
    'durasi' => [fn () => Nilai::durasi('sebentar')],
    'idpel' => [fn () => Nilai::kode('18A1')],
]);
