<?php

declare(strict_types=1);

namespace Turkbelge\EFatura\Model;

/**
 * Mükellefin GİB'e kayıtlı gönderici birim (GB) veya posta kutusu (PK) etiketi.
 */
final class Etiket
{
    public function __construct(
        public readonly string $adres,
        public readonly ?string $birim,
        public readonly ?string $belgeTuru,
        public readonly ?string $olusturmaTarihi,
    ) {
    }

    public function postaKutusuMu(): bool
    {
        if ($this->birim !== null) {
            return strtoupper($this->birim) === 'PK';
        }

        // Birim bilgisi gelmezse etiket adından tahmin edilir (urn:mail:defaultpk@...)
        return str_contains(strtolower($this->adres), 'pk@');
    }
}
