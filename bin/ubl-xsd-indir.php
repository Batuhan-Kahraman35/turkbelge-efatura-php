<?php

/**
 * GİB UBL-TR 1.2.1 paketindeki XSD şemalarını tests/.ubl-tr-xsd dizinine indirir.
 *
 * Şemalar depoya konmaz; testler bu dizin varsa üretilen faturaları şemaya
 * karşı doğrular, yoksa doğrulama testlerini atlar.
 *
 * Kullanım: composer ubl-xsd
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Turkbelge\EFatura\Tasima\Zip;

const PAKET = 'https://ebelge.gib.gov.tr/dosyalar/kilavuzlar/UBL-TR1.2.1_Paketi.zip';
$hedef = __DIR__ . '/../tests/.ubl-tr-xsd';

$ch = curl_init(PAKET);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_USERAGENT => 'turkbelge-efatura-php',
    CURLOPT_TIMEOUT => 120,
]);
$zip = curl_exec($ch);
$kod = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($zip === false || $kod !== 200) {
    fwrite(STDERR, "Paket indirilemedi (HTTP {$kod}): " . PAKET . PHP_EOL);
    exit(1);
}

$sayi = 0;
foreach (Zip::ac($zip) as $ad => $icerik) {
    // Yalnız xsdrt altındaki şemalar; yol dışına çıkan adlar reddedilir
    if (!preg_match('#(?:^|/)xsdrt/((?:common|maindoc)/[A-Za-z0-9_.\-]+\.xsd)$#', $ad, $m)) {
        continue;
    }
    $yol = $hedef . '/' . $m[1];
    if (!is_dir(dirname($yol))) {
        mkdir(dirname($yol), 0777, true);
    }
    file_put_contents($yol, $icerik);
    $sayi++;
}

echo "{$sayi} şema dosyası yazıldı: " . realpath($hedef) . PHP_EOL;
exit($sayi > 0 ? 0 : 1);
