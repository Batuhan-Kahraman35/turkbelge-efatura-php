<?php

declare(strict_types=1);

namespace Turkbelge\EFatura\Model;

/**
 * Oturum açan hesabın firma bilgileri (GetAccount sonucu).
 */
final class HesapBilgisi
{
    /** @param array<string, string> $ham Servisin döndürdüğü tüm alanlar */
    public function __construct(
        public readonly ?string $unvan,
        public readonly ?string $vknTckn,
        public readonly ?string $vergiDairesi,
        public readonly ?string $eposta,
        public readonly ?string $il,
        public readonly ?string $ilce,
        public readonly array $ham,
    ) {
    }

    /** @param array<string, string> $alanlar */
    public static function diziden(array $alanlar): self
    {
        $al = static fn (string $k) => ($alanlar[$k] ?? '') !== '' ? $alanlar[$k] : null;
        $unvan = $al('COMMERCIAL_NAME') ?? trim(($alanlar['FIRST_NAME'] ?? '') . ' ' . ($alanlar['LAST_NAME'] ?? ''));

        return new self(
            $unvan !== '' ? $unvan : null,
            $al('VKN_TCKNO'),
            $al('TAX_OFFICE'),
            $al('EMAIL'),
            $al('CITY'),
            $al('SUB_CITY'),
            $alanlar,
        );
    }
}
