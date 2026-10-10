<?php

declare(strict_types=1);

namespace Turkbelge\EFatura\Yardimci;

use InvalidArgumentException;

/**
 * GİB fatura numarası: 3 hane seri (A-Z, 0-9) + 4 hane yıl + 9 hane sıra = 16 hane.
 * Sıra her yıl 1'den başlar. e-Fatura, e-Arşiv normal ve e-Arşiv internet ayrı seri kullanmalıdır.
 */
final class FaturaNo
{
    public static function gecerliMi(string $no): bool
    {
        return (bool) preg_match('/^[A-Z0-9]{3}\d{4}\d{9}$/', $no);
    }

    /** ABC + 2026 + 000000000: numarayı servisin atayacağı durumlar için. */
    public static function yerTutucu(string $seri, int $yil): string
    {
        return self::olustur($seri, $yil, 0);
    }

    public static function olustur(string $seri, int $yil, int $sira): string
    {
        $seri = strtoupper($seri);
        if (!preg_match('/^[A-Z0-9]{3}$/', $seri)) {
            throw new InvalidArgumentException("Seri 3 haneli harf/rakam olmalı: {$seri}");
        }
        if ($yil < 2000 || $yil > 9999 || $sira < 0 || $sira > 999999999) {
            throw new InvalidArgumentException('Yıl veya sıra numarası geçersiz.');
        }

        return sprintf('%s%04d%09d', $seri, $yil, $sira);
    }

    /** Numaradan sıra kısmı; yer tutucu (0) dahil. Geçersizse null. */
    public static function sira(string $no): ?int
    {
        return self::gecerliMi($no) ? (int) substr($no, 7) : null;
    }
}
