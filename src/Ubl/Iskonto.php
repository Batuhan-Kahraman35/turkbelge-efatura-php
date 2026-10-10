<?php

declare(strict_types=1);

namespace Turkbelge\EFatura\Ubl;

use InvalidArgumentException;

/**
 * Satır iskontosu. Oran veya tutar verilir; birden çok iskonto ardışık uygulanır
 * (her biri bir öncekinden kalan tutar üzerinden).
 */
final class Iskonto
{
    private function __construct(
        public readonly ?float $oran,
        public readonly ?float $tutar,
        public readonly string $aciklama,
    ) {
    }

    /** @param float $oran Yüzde, ör. 10 = %10 */
    public static function oran(float $oran, string $aciklama = 'İskonto'): self
    {
        if ($oran <= 0 || $oran > 100) {
            throw new InvalidArgumentException('İskonto oranı 0 ile 100 arasında olmalı.');
        }

        return new self($oran, null, $aciklama);
    }

    public static function tutar(float $tutar, string $aciklama = 'İskonto'): self
    {
        if ($tutar <= 0) {
            throw new InvalidArgumentException('İskonto tutarı sıfırdan büyük olmalı.');
        }

        return new self(null, $tutar, $aciklama);
    }

    /** Verilen taban üzerinden iskonto tutarı (2 hane). */
    public function hesapla(float $taban): float
    {
        $tutar = $this->tutar ?? $taban * $this->oran / 100;
        if ($tutar > $taban + 0.0001) {
            throw new InvalidArgumentException("İskonto ({$tutar}) satır tutarını ({$taban}) aşamaz.");
        }

        return round($tutar, 2);
    }
}
