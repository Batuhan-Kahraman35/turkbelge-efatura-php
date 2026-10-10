<?php

declare(strict_types=1);

namespace Turkbelge\EFatura\Model;

/**
 * GİB e-Fatura mükellef kaydı (CheckUser sonucu).
 */
final class Mukellef
{
    /**
     * @param list<Etiket> $etiketler Silinmemiş etiketler
     */
    public function __construct(
        public readonly string $vkn,
        public readonly string $unvan,
        public readonly ?string $tur,
        public readonly ?string $kayitTarihi,
        public readonly array $etiketler,
    ) {
    }

    /** Fatura gönderiminde alıcı olarak kullanılacak posta kutusu (PK) etiketi. */
    public function pkEtiketi(): ?string
    {
        foreach ($this->etiketler as $e) {
            if ($e->postaKutusuMu()) {
                return $e->adres;
            }
        }

        return $this->etiketler[0]->adres ?? null;
    }

    /** @return list<string> */
    public function gbEtiketleri(): array
    {
        return array_values(array_map(
            static fn (Etiket $e) => $e->adres,
            array_filter($this->etiketler, static fn (Etiket $e) => !$e->postaKutusuMu()),
        ));
    }
}
