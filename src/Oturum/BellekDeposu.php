<?php

declare(strict_types=1);

namespace Turkbelge\EFatura\Oturum;

/**
 * Yalnız çalışan süreç boyunca saklar (CLI, kuyruk işçisi, tek istek).
 */
final class BellekDeposu implements OturumDeposu
{
    /** @var array<string, array{0: string, 1: int}> */
    private array $kayitlar = [];

    public function al(string $anahtar): ?string
    {
        [$deger, $bitis] = $this->kayitlar[$anahtar] ?? [null, 0];

        return $deger !== null && $bitis > time() ? $deger : null;
    }

    public function koy(string $anahtar, string $deger, int $saniye): void
    {
        $this->kayitlar[$anahtar] = [$deger, time() + $saniye];
    }

    public function sil(string $anahtar): void
    {
        unset($this->kayitlar[$anahtar]);
    }
}
