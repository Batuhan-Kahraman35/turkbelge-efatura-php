<?php

declare(strict_types=1);

namespace Turkbelge\EFatura\Model;

/**
 * Fatura gönderiminin sonucu.
 */
final class GonderimSonucu
{
    public function __construct(
        /** Servisin atadığı (veya UBL'deki) fatura numarası */
        public readonly ?string $faturaNo,
        public readonly string $ettn,
        /** e-Arşivde faturanın web'den görüntülenme anahtarı */
        public readonly ?string $webAnahtari = null,
        public readonly bool $taslak = false,
    ) {
    }
}
