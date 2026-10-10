<?php

declare(strict_types=1);

namespace Turkbelge\EFatura\Model;

use Turkbelge\EFatura\Tasima\XmlOkuyucu;

/**
 * e-Fatura durum sorgusu sonucu (GetInvoiceStatus / GetInvoiceStatusAll).
 */
final class FaturaDurumu
{
    /** @param array<string, string> $ham */
    public function __construct(
        public readonly string $ettn,
        public readonly ?string $faturaNo,
        public readonly ?string $durum,
        public readonly ?string $durumKodu,
        public readonly ?string $durumAciklama,
        public readonly ?string $gibDurumKodu,
        public readonly ?string $gibDurumAciklama,
        public readonly ?string $yanitKodu,
        public readonly ?string $yanitAciklama,
        public readonly ?string $webAnahtari,
        public readonly ?bool $haricIptal,
        public readonly array $ham,
    ) {
    }

    /**
     * Fatura GİB İptal/İtiraz Portalı'ndan iptal edilmiş mi?
     *
     * Bu bilgi yalnız tekil durum sorgusunda (GetInvoiceStatus) gelir; GİB durum
     * açıklaması iptal edilmiş faturada bile "başarıyla tamamlandı" diyebilir.
     * Toplu sorguda bilgi gelmediği için null döner.
     */
    public function iptalEdildiMi(): ?bool
    {
        return $this->haricIptal;
    }

    public static function xmlden(XmlOkuyucu $durum): self
    {
        // Tekil sorguda alanlar doğrudan INVOICE_STATUS altında, toplu sorguda HEADER içinde gelir
        $h = ($durum->cocuk('HEADER')?->dizi() ?? []) + $durum->dizi();
        $al = static fn (string $k) => ($h[$k] ?? '') !== '' ? $h[$k] : null;
        $iptal = $al('EXTERNAL_CANCEL_FLAG');

        return new self(
            (string) $durum->oznitelik('UUID'),
            $durum->oznitelik('ID') ?: null,
            $al('STATUS'),
            $al('STATUS_CODE'),
            $al('STATUS_DESCRIPTION'),
            $al('GIB_STATUS_CODE'),
            $al('GIB_STATUS_DESCRIPTION'),
            $al('RESPONSE_CODE'),
            $al('RESPONSE_DESCRIPTION'),
            $al('WEB_KEY'),
            $iptal === null ? null : strtoupper($iptal) === 'Y',
            $h,
        );
    }
}
