<?php

declare(strict_types=1);

namespace Turkbelge\EFatura\Testler\Destek;

/**
 * Testler için ext-zip gerektirmeden ZIP arşivi üretir.
 */
final class ZipUretici
{
    /** @param array<string, string> $dosyalar ad => içerik */
    public static function uret(array $dosyalar, bool $sikistir = true): string
    {
        $yerel = '';
        $merkezi = '';
        foreach ($dosyalar as $ad => $icerik) {
            $veri = $sikistir ? gzdeflate($icerik) : $icerik;
            $yontem = $sikistir ? 8 : 0;
            $ortak = pack('vvvvvVVVv', 20, 0, $yontem, 0, 0, crc32($icerik), strlen($veri), strlen($icerik), strlen($ad));
            $ofset = strlen($yerel);
            $yerel .= "PK\x03\x04" . $ortak . pack('v', 0) . $ad . $veri;
            $merkezi .= "PK\x01\x02" . pack('v', 20) . $ortak . pack('vvvvVV', 0, 0, 0, 0, 0, $ofset) . $ad;
        }

        return $yerel . $merkezi
            . "PK\x05\x06" . pack('vvvvVVv', 0, 0, count($dosyalar), count($dosyalar), strlen($merkezi), strlen($yerel), 0);
    }
}
