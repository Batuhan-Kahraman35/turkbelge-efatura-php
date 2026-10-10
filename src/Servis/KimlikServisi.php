<?php

declare(strict_types=1);

namespace Turkbelge\EFatura\Servis;

use Turkbelge\EFatura\Hata\TurkbelgeHatasi;
use Turkbelge\EFatura\Model\Etiket;
use Turkbelge\EFatura\Model\HesapBilgisi;
use Turkbelge\EFatura\Model\Mukellef;
use Turkbelge\EFatura\Oturum\OturumYoneticisi;
use Turkbelge\EFatura\ServisTipi;
use Turkbelge\EFatura\Tasima\Xml;

/**
 * Kimlik doğrulama servisi (AuthenticationWS): mükellef sorgusu, hesap ve yetki bilgisi.
 */
final class KimlikServisi
{
    public const URUNLER = ['EINVOICE', 'EARCHIVE', 'EDESPATCH', 'CREDITNOTE', 'RECONCILIATION', 'CONNECTOR', 'ELEDGER', 'ELEDGERARCHIVE', 'EINVOICEARCHIVE', 'ESMM'];

    public function __construct(private readonly OturumYoneticisi $oturum)
    {
    }

    /**
     * VKN/TCKN'nin e-Fatura mükellefi olup olmadığını sorgular.
     *
     * Mükellef değilse null döner (fatura e-Arşiv olarak kesilmelidir). Servis hatası
     * ise istisna fırlatır; hata durumunda "mükellef değil" varsayılıp mükellefe
     * yanlışlıkla e-Arşiv kesilmesi bu yüzden engellenir.
     *
     * @param string $belgeTuru INVOICE (e-Fatura) veya DESPATCHADVICE (e-İrsaliye)
     */
    public function mukellefSorgula(string $vknTckn, string $belgeTuru = 'INVOICE'): ?Mukellef
    {
        $vknTckn = preg_replace('/\D/', '', $vknTckn);
        if (!in_array(strlen($vknTckn), [10, 11], true)) {
            return null;
        }

        try {
            $yanit = $this->oturum->cagir(
                ServisTipi::KIMLIK,
                'CheckUserRequest',
                '<USER>' . Xml::eleman('IDENTIFIER', $vknTckn) . '</USER>' . Xml::eleman('DOCUMENT_TYPE', $belgeTuru),
            );
        } catch (TurkbelgeHatasi $e) {
            if ($e->kayitYokMu()) {
                return null;
            }
            throw $e;
        }

        $unvan = null;
        $tur = null;
        $kayitTarihi = null;
        $etiketler = [];
        foreach ($yanit->cocuklar('USER') as $kullanici) {
            $alan = $kullanici->dizi();
            if (strtoupper($alan['DELETED'] ?? 'N') === 'Y') {
                continue;
            }
            if (($alan['DOCUMENT_TYPE'] ?? '') !== '' && strtoupper($alan['DOCUMENT_TYPE']) !== $belgeTuru) {
                continue;
            }
            $unvan ??= $alan['TITLE'] ?? null;
            $tur ??= $alan['TYPE'] ?? null;
            $kayitTarihi ??= $alan['REGISTER_TIME'] ?? null;
            if (($alan['ALIAS'] ?? '') !== '') {
                $etiketler[] = new Etiket(
                    $alan['ALIAS'],
                    ($alan['UNIT'] ?? '') !== '' ? $alan['UNIT'] : null,
                    $alan['DOCUMENT_TYPE'] ?? null,
                    $alan['ALIAS_CREATION_TIME'] ?? null,
                );
            }
        }

        if ($etiketler === []) {
            return null;
        }

        return new Mukellef($vknTckn, (string) $unvan, $tur, $kayitTarihi, $etiketler);
    }

    /** Oturum açan hesabın firma bilgileri. */
    public function hesapBilgisi(): HesapBilgisi
    {
        $yanit = $this->oturum->cagir(ServisTipi::KIMLIK, 'GetAccountRequest');

        return HesapBilgisi::diziden($yanit->cocuk('ACCOUNT_ADDRESS')?->dizi() ?? []);
    }

    /**
     * Hesabın bir ürünü (EINVOICE, EARCHIVE…) kullanma yetkisi var mı?
     */
    public function yetkiliMi(string $urun): bool
    {
        $urun = strtoupper($urun);
        if (!in_array($urun, self::URUNLER, true)) {
            throw new \InvalidArgumentException("Geçersiz ürün: {$urun}. Geçerli: " . implode(', ', self::URUNLER));
        }

        $yanit = $this->oturum->cagir(
            ServisTipi::KIMLIK,
            'GetUserAuthorizationRequest',
            Xml::elemanlar(['PRODUCT' => $urun, 'PRODUCT_DETAIL_FLAG' => 'N']),
        );

        return strtoupper($yanit->deger('AUTHORIZED') ?? 'N') === 'Y';
    }

    public function cikis(): void
    {
        $this->oturum->cikis();
    }
}
