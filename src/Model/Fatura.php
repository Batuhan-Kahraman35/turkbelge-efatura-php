<?php

declare(strict_types=1);

namespace Turkbelge\EFatura\Model;

use Turkbelge\EFatura\Tasima\XmlOkuyucu;
use Turkbelge\EFatura\Tasima\Zip;

/**
 * e-Fatura kaydı (GetInvoice INVOICE elemanı).
 */
final class Fatura
{
    /** @param array<string, string> $ham HEADER'daki tüm alanlar */
    public function __construct(
        public readonly ?string $faturaNo,
        public readonly string $ettn,
        public readonly ?string $gonderenVkn,
        public readonly ?string $gonderenUnvan,
        public readonly ?string $aliciVkn,
        public readonly ?string $aliciUnvan,
        /** Y-m-d */
        public readonly ?string $tarih,
        public readonly ?string $tutar,
        public readonly ?string $paraBirimi,
        public readonly ?string $senaryo,
        public readonly ?string $tip,
        public readonly ?string $durum,
        public readonly ?string $durumKodu,
        public readonly ?string $gibDurumKodu,
        public readonly ?string $gibDurumAciklama,
        public readonly ?string $yanitKodu,
        public readonly ?string $yanitAciklama,
        public readonly ?string $sistemTarihi,
        public readonly ?Yon $yon,
        public readonly array $ham,
        public readonly ?string $icerik = null,
    ) {
    }

    /** @param Yon|null $yon İstekteki yön; liste yanıtında DIRECTION alanı gelmez */
    public static function xmlden(XmlOkuyucu $fatura, ?Yon $yon = null): self
    {
        $baslik = $fatura->cocuk('HEADER');
        $h = $baslik?->dizi() ?? [];
        $al = static fn (string $k) => ($h[$k] ?? '') !== '' ? $h[$k] : null;
        $icerik = $fatura->deger('CONTENT');

        return new self(
            $fatura->oznitelik('ID') ?: null,
            (string) $fatura->oznitelik('UUID'),
            $al('SENDER'),
            $al('SUPPLIER'),
            $al('RECEIVER'),
            $al('CUSTOMER'),
            // Servis tarihi saat dilimi ekiyle döner: 2024-12-13+03:00
            $al('ISSUE_DATE') !== null ? substr($al('ISSUE_DATE'), 0, 10) : null,
            $al('PAYABLE_AMOUNT'),
            $baslik?->cocuk('PAYABLE_AMOUNT')?->oznitelik('currencyID') ?: null,
            $al('PROFILEID'),
            $al('INVOICE_TYPE_CODE'),
            $al('STATUS'),
            $al('STATUS_CODE'),
            $al('GIB_STATUS_CODE'),
            $al('GIB_STATUS_DESCRIPTION'),
            $al('RESPONSE_CODE'),
            $al('RESPONSE_DESCRIPTION'),
            $al('CDATE'),
            Yon::tryFrom((string) $al('DIRECTION')) ?? $yon,
            $h,
            $icerik !== null ? Zip::icerik($icerik, 'xml') : null,
        );
    }
}
