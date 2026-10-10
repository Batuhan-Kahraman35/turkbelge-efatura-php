<?php

declare(strict_types=1);

namespace Turkbelge\EFatura\Ubl;

use DateTimeInterface;

/**
 * Ödeme bilgisi (cac:PaymentMeans). IBAN verilirse görüntüde banka hesabı olarak basılır.
 */
final class Odeme
{
    /** Ödeme şekli kodları (UN/EDIFACT 4461) */
    public const KREDI_KARTI = '48';
    public const EFT_HAVALE = '46';
    public const KAPIDA = '10';
    public const DIGER = '1';

    public function __construct(
        public readonly string $kod = self::EFT_HAVALE,
        public readonly ?DateTimeInterface $vadeTarihi = null,
        public readonly ?string $iban = null,
        public readonly ?string $bankaAdi = null,
        public readonly ?string $subeAdi = null,
        public readonly ?string $aciklama = null,
    ) {
    }
}
