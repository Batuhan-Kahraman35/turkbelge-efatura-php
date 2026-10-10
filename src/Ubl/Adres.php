<?php

declare(strict_types=1);

namespace Turkbelge\EFatura\Ubl;

use InvalidArgumentException;

/**
 * Posta adresi (cac:PostalAddress). İlçe ve il UBL-TR'de zorunludur.
 */
final class Adres
{
    public function __construct(
        public readonly string $ilce,
        public readonly string $il,
        public readonly ?string $cadde = null,
        public readonly ?string $binaNo = null,
        public readonly ?string $postaKodu = null,
        public readonly string $ulke = 'Türkiye',
    ) {
        if (trim($ilce) === '' || trim($il) === '') {
            throw new InvalidArgumentException('Adreste ilçe ve il zorunludur.');
        }
    }
}
