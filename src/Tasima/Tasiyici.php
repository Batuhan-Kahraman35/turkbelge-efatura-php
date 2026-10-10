<?php

declare(strict_types=1);

namespace Turkbelge\EFatura\Tasima;

/**
 * HTTP katmanı. Testlerde sahte bir taşıyıcı verilerek ağa çıkılmadan çalışılabilir.
 */
interface Tasiyici
{
    /**
     * SOAP isteğini gönderir.
     *
     * @return array{0: int, 1: string} [HTTP kodu, yanıt gövdesi]
     *
     * @throws \Turkbelge\EFatura\Hata\BaglantiHatasi Ağ hatasında
     */
    public function gonder(string $adres, string $govde): array;
}
