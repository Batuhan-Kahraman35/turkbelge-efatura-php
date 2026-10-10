<?php

declare(strict_types=1);

namespace Turkbelge\EFatura\Oturum;

/**
 * Oturum numarasını (SESSION_ID) istekler arasında saklar.
 *
 * Kendi önbelleğinizi (Redis, PSR-16, Laravel Cache vb.) bu arayüzle bağlayabilirsiniz.
 */
interface OturumDeposu
{
    public function al(string $anahtar): ?string;

    public function koy(string $anahtar, string $deger, int $saniye): void;

    public function sil(string $anahtar): void;
}
