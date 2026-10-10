<?php

declare(strict_types=1);

namespace Turkbelge\EFatura\Ubl;

/**
 * Fatura senaryosu (cbc:ProfileID).
 */
enum Senaryo: string
{
    /** e-Fatura; alıcının kabul/red yanıtı yoktur */
    case TEMEL = 'TEMELFATURA';
    /** e-Fatura; alıcı 8 gün içinde kabul/red yanıtı verebilir */
    case TICARI = 'TICARIFATURA';
    /** e-Fatura mükellefi olmayan alıcıya e-Arşiv */
    case EARSIV = 'EARSIVFATURA';

    public function earsivMi(): bool
    {
        return $this === self::EARSIV;
    }
}
