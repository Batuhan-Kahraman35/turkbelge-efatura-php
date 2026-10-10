<?php

declare(strict_types=1);

namespace Turkbelge\EFatura\Model;

use Turkbelge\EFatura\Tasima\XmlOkuyucu;
use Turkbelge\EFatura\Tasima\Zip;

/**
 * e-Arşiv fatura kaydı (GetEArchiveInvoiceList INVOICE elemanı).
 */
final class EArsivFatura
{
    /** @param array<string, string> $ham HEADER'daki tüm alanlar */
    public function __construct(
        public readonly ?string $faturaNo,
        public readonly string $ettn,
        public readonly ?string $aliciVkn,
        public readonly ?string $aliciUnvan,
        /** Y-m-d */
        public readonly ?string $tarih,
        public readonly ?string $tutar,
        public readonly ?string $matrah,
        public readonly ?string $paraBirimi,
        public readonly ?string $senaryo,
        public readonly ?string $tip,
        /** NORMAL veya INTERNET */
        public readonly ?string $earsivTuru,
        /** ELEKTRONIK veya KAGIT */
        public readonly ?string $gonderimSekli,
        public readonly ?string $durum,
        public readonly ?string $durumKodu,
        public readonly array $ham,
        public readonly ?string $icerik = null,
    ) {
    }

    /** Taslak (numara verilmemiş veya henüz işlenmemiş) kayıt mı? Taslakta durum boş gelir. */
    public function taslakMi(): bool
    {
        return $this->durumKodu === null || (int) $this->durumKodu === EArsivDurumu::TASLAK;
    }

    /** Fatura iptal edilmiş mi? (Raporlanmadan iptal veya iptal raporu) */
    public function iptalEdildiMi(): bool
    {
        return (int) $this->durumKodu === EArsivDurumu::IPTAL
            || strtoupper((string) $this->senaryo) === 'IPTAL'
            || str_contains(strtoupper((string) $this->durum), 'IPTAL');
    }

    public static function xmlden(XmlOkuyucu $fatura): self
    {
        $h = $fatura->cocuk('HEADER')?->dizi() ?? [];
        $al = static fn (string $k) => ($h[$k] ?? '') !== '' ? $h[$k] : null;
        $icerik = $fatura->deger('CONTENT');

        return new self(
            $al('INVOICE_ID'),
            (string) $al('UUID'),
            $al('CUSTOMER_IDENTIFIER'),
            $al('CUSTOMER_NAME'),
            self::tarih($al('ISSUE_DATE')),
            $al('PAYABLE_AMOUNT'),
            $al('TAXABLE_AMOUNT'),
            $al('CURRENCY_CODE'),
            $al('PROFILE_ID'),
            $al('INVOICE_TYPE'),
            $al('EARCHIVE_TYPE'),
            $al('SENDING_TYPE'),
            $al('STATUS'),
            $al('STATUS_CODE'),
            $h,
            $icerik !== null ? Zip::icerik($icerik) : null,
        );
    }

    /** Servis tarihi farklı biçimlerde döndürebilir: d-m-Y H:i:s, Y-m-d, Y-m-dTH:i:s… */
    public static function tarih(?string $deger): ?string
    {
        if ($deger === null) {
            return null;
        }
        if (preg_match('/^(\d{2})[.\-\/](\d{2})[.\-\/](\d{4})/', $deger, $m)) {
            return "{$m[3]}-{$m[2]}-{$m[1]}";
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $deger, $m)) {
            return $m[0];
        }

        return $deger;
    }
}
