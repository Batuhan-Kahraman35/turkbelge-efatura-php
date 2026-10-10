<?php

declare(strict_types=1);

namespace Turkbelge\EFatura\Model;

use Turkbelge\EFatura\Tasima\XmlOkuyucu;

/**
 * e-Arşiv durum sorgusu sonucu (GetEArchiveInvoiceStatus).
 */
final class EArsivDurumu
{
    public const KUYRUKTA = 100;
    public const TASLAK = 105;
    public const ISLENIYOR = 110;
    public const RAPORLANACAK = 120;
    public const RAPORLANDI = 130;
    /** Raporlanmadan iptal edildi, raporlanmayacak */
    public const IPTAL = 150;
    public const BULUNAMADI = 200;

    /** E-posta gönderim durumları */
    public const EPOSTA_ISLENMEDI = 100;
    public const EPOSTA_ISLENIYOR = 110;
    public const EPOSTA_ISLENDI = 120;
    public const EPOSTA_SONLANDI = 130;

    /** @param array<string, string> $ham */
    public function __construct(
        public readonly string $ettn,
        public readonly ?string $faturaNo,
        public readonly ?int $durumKodu,
        public readonly ?string $durumAciklama,
        public readonly ?int $epostaDurumKodu,
        public readonly ?string $epostaDurumAciklama,
        public readonly ?string $raporNo,
        public readonly ?string $webAnahtari,
        public readonly array $ham,
    ) {
    }

    public function raporlandiMi(): bool
    {
        return $this->durumKodu === self::RAPORLANDI;
    }

    public function iptalEdildiMi(): bool
    {
        return $this->durumKodu === self::IPTAL;
    }

    public function bulunduMu(): bool
    {
        return $this->durumKodu !== null && $this->durumKodu !== self::BULUNAMADI;
    }

    public static function xmlden(XmlOkuyucu $fatura): self
    {
        $h = $fatura->cocuk('HEADER')?->dizi() ?? [];
        $al = static fn (string $k) => ($h[$k] ?? '') !== '' ? $h[$k] : null;
        $sayi = static fn (?string $v) => $v !== null && is_numeric($v) ? (int) $v : null;

        return new self(
            (string) $al('UUID'),
            $al('INVOICE_ID'),
            $sayi($al('STATUS')),
            $al('STATUS_DESC'),
            $sayi($al('EMAIL_STATUS')),
            $al('EMAIL_STATUS_DESC'),
            $al('REPORT_ID'),
            $al('WEB_KEY'),
            $h,
        );
    }
}
