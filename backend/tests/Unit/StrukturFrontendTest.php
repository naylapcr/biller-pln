<?php

/*
 * Aturan impor frontend (PRD 10.4): halaman dan komponen satu peran tidak mengimpor folder peran lain,
 * dan components/ui tidak bergantung pada folder peran atau shared.
 */

function imporTerlarang(array $folder, array $terlarang): array
{
    $src = dirname(__DIR__, 3).'/frontend/src/';
    $hasil = [];
    foreach ($folder as $f) {
        $berkas = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src.$f, FilesystemIterator::SKIP_DOTS));
        foreach ($berkas as $b) {
            foreach ($terlarang as $t) {
                if (str_contains(file_get_contents($b->getPathname()), "@/{$t}/")) {
                    $hasil[] = $b->getFilename().' → '.$t;
                }
            }
        }
    }

    return $hasil;
}

test('tidak ada impor silang antarperan di frontend', function (array $folder, array $terlarang) {
    expect(imporTerlarang($folder, $terlarang))->toBe([]);
})->with([
    'Biller' => [['pages/Biller', 'components/biller'], ['components/koordinator', 'components/superadmin']],
    'Koordinator' => [['pages/Koordinator', 'components/koordinator'], ['components/biller', 'components/superadmin']],
    'Super Admin' => [['pages/SuperAdmin', 'components/superadmin'], ['components/biller', 'components/koordinator']],
    'shared' => [['components/shared'], ['components/biller', 'components/koordinator', 'components/superadmin']],
    'ui' => [['components/ui'], ['components/shared', 'components/biller', 'components/koordinator', 'components/superadmin']],
]);
