<?php

declare(strict_types=1);

namespace Turkbelge\EFatura\Servis;

use InvalidArgumentException;
use Turkbelge\EFatura\Hata\TurkbelgeHatasi;
use Turkbelge\EFatura\Model\Fatura;
use Turkbelge\EFatura\Model\FaturaDurumu;
use Turkbelge\EFatura\Model\FaturaFiltresi;
use Turkbelge\EFatura\Model\GonderimSonucu;
use Turkbelge\EFatura\Model\Yon;
use Turkbelge\EFatura\Oturum\OturumYoneticisi;
use Turkbelge\EFatura\ServisTipi;
use Turkbelge\EFatura\Tasima\Xml;
use Turkbelge\EFatura\Tasima\XmlOkuyucu;
use Turkbelge\EFatura\Tasima\Zip;
use Turkbelge\EFatura\Ubl\FaturaBilgisi;
use Turkbelge\EFatura\Ubl\UblOlusturucu;
use Turkbelge\EFatura\Yardimci\FaturaNo;

/**
 * e-Fatura servisi (EFaturaOIB): gönderim, taslak, listeleme, içerik, görsel ve durum sorgusu.
 */
final class EFaturaServisi
{
    public const GORSEL_TURLERI = ['PDF', 'HTML', 'XML'];

    public function __construct(private readonly OturumYoneticisi $oturum)
    {
    }

    /**
     * Tek istekte fatura başlıklarını listeler.
     *
     * Dönen kayıt sayısı limite eşitse liste eksik olabilir; tamamı için tumu() kullanın.
     *
     * @return list<Fatura>
     */
    public function liste(FaturaFiltresi $filtre): array
    {
        return $this->ara($filtre, baslikMi: true);
    }

    /**
     * Tarih aralığındaki tüm faturaları getirir; sonuç limite ulaşırsa aralığı
     * ikiye bölerek yeniden sorgular. Tek güne inip yine limite ulaşılırsa
     * eksik kalmaması için istisna fırlatır (karşı VKN ile daraltın).
     *
     * @return \Generator<int, Fatura>
     */
    public function tumu(FaturaFiltresi $filtre): \Generator
    {
        $faturalar = $this->liste($filtre);
        if (count($faturalar) < $filtre->limit) {
            yield from $faturalar;

            return;
        }

        if ($filtre->gunSayisi() === 1) {
            throw new \LengthException(sprintf(
                '%s tarihinde %d faturadan fazlası var; limiti artırın veya karşı VKN ile daraltın.',
                $filtre->baslangic->format('d.m.Y'),
                $filtre->limit,
            ));
        }

        $ortaGun = intdiv($filtre->gunSayisi(), 2);
        $orta = $filtre->baslangic->modify("+{$ortaGun} days");
        yield from $this->tumu($filtre->aralikla($filtre->baslangic, $orta->modify('-1 day')));
        yield from $this->tumu($filtre->aralikla($orta, $filtre->bitis));
    }

    /**
     * Faturayı UBL XML içeriğiyle getirir. Bulunamazsa null.
     */
    public function getir(string $ettn, Yon $yon): ?Fatura
    {
        $sonuc = $this->sorgula('GetInvoiceRequest', [
            'LIMIT' => 1,
            'UUID' => $ettn,
            'READ_INCLUDED' => 'true',
            'DIRECTION' => $yon->value,
        ], baslikMi: false, yon: $yon);

        return $sonuc[0] ?? null;
    }

    /**
     * Faturanın görüntüsünü döndürür (PDF ikili verisi, HTML veya XML metni).
     *
     * Servis bu çağrıda içeriği her zaman ZIP olarak döndürür; açılmış hali verilir.
     */
    public function gorsel(string $ettn, Yon $yon, string $tur = 'PDF'): string
    {
        $tur = strtoupper($tur);
        if (!in_array($tur, self::GORSEL_TURLERI, true)) {
            throw new InvalidArgumentException('Görsel türü PDF, HTML veya XML olmalı.');
        }

        $yanit = $this->oturum->cagir(
            ServisTipi::EFATURA,
            'GetInvoiceWithTypeRequest',
            '<INVOICE_SEARCH_KEY>'
            . Xml::elemanlar(['LIMIT' => 1, 'UUID' => $ettn, 'TYPE' => $tur, 'READ_INCLUDED' => 'true', 'DIRECTION' => $yon->value])
            . '</INVOICE_SEARCH_KEY>'
            . Xml::eleman('HEADER_ONLY', 'N'),
        );

        $icerik = $yanit->cocuk('INVOICE')?->deger('CONTENT');
        if ($icerik === null) {
            throw new TurkbelgeHatasi("Fatura görseli bulunamadı: {$ettn}", TurkbelgeHatasi::KAYIT_BULUNAMADI);
        }

        return Zip::icerik($icerik, strtolower($tur));
    }

    /**
     * Tek faturanın durumu. GİB portalından yapılan iptal (EXTERNAL_CANCEL_FLAG)
     * yalnız bu sorguda gelir.
     *
     * Servis kuralı: durum en sık 4 saatte bir sorgulanmalı; kesin sonuca
     * (başarılı / hatalı) ulaşan faturalar tekrar sorgulanmamalıdır.
     */
    public function durum(string $ettn): FaturaDurumu
    {
        $yanit = $this->oturum->cagir(
            ServisTipi::EFATURA,
            'GetInvoiceStatusRequest',
            '<INVOICE UUID="' . Xml::kacir($ettn) . '"/>',
        );

        $durum = $yanit->cocuk('INVOICE_STATUS')
            ?? throw new TurkbelgeHatasi("Durum yanıtı boş: {$ettn}", TurkbelgeHatasi::KAYIT_BULUNAMADI);

        return FaturaDurumu::xmlden($durum);
    }

    /**
     * Birden çok faturanın durumu tek istekte (iptal bayrağı bu sorguda gelmez).
     *
     * @param list<string> $ettnler
     *
     * @return list<FaturaDurumu>
     */
    public function durumlar(array $ettnler): array
    {
        if ($ettnler === []) {
            return [];
        }

        $yanit = $this->oturum->cagir(
            ServisTipi::EFATURA,
            'GetInvoiceStatusAllRequest',
            implode('', array_map(static fn (string $u) => Xml::eleman('UUID', $u), $ettnler)),
        );

        return array_map(FaturaDurumu::xmlden(...), $yanit->cocuklar('INVOICE_STATUS'));
    }

    /**
     * Tarih aralığındaki fatura sayısı: portaldan ve web servisten gelenler ayrı.
     *
     * @return array{portal: int, servis: int}
     */
    public function sayim(Yon $yon, \DateTimeInterface $baslangic, \DateTimeInterface $bitis): array
    {
        $yanit = $this->oturum->cagir(
            ServisTipi::EFATURA,
            'GetInvoiceCountRequest',
            Xml::elemanlar([
                'ISSUE_DATE_START' => $baslangic->format('Y-m-d'),
                'ISSUE_DATE_END' => $bitis->format('Y-m-d'),
                'DIRECTION' => $yon->value,
            ]),
        );

        return ['portal' => (int) $yanit->deger('PORTAL'), 'servis' => (int) $yanit->deger('WS')];
    }

    /**
     * Faturayı UBL'den üretip alıcıya gönderir.
     *
     * Fatura numarası yer tutucuysa (ABC2026000000000) seri öneki gönderilir ve
     * numarayı servis atar; dönen sonuçta gerçek numara bulunur.
     *
     * @param string      $aliciEtiket     Alıcının posta kutusu (PK) etiketi: KimlikServisi::mukellefSorgula()->pkEtiketi()
     * @param string|null $gondericiEtiket Gönderici birim (GB) etiketi; verilmezse servis hesabın varsayılanını kullanır
     */
    public function gonder(FaturaBilgisi $fatura, string $aliciEtiket, ?string $gondericiEtiket = null): GonderimSonucu
    {
        if ($fatura->senaryo->earsivMi()) {
            throw new InvalidArgumentException('e-Arşiv faturası e-Fatura servisinden gönderilemez; earsiv()->gonder() kullanın.');
        }

        return $this->gonderXml(
            (new UblOlusturucu())->olustur($fatura),
            $fatura->ettn,
            $fatura->alici->vknTckn,
            $aliciEtiket,
            $fatura->satici->vknTckn,
            $gondericiEtiket,
            FaturaNo::sira($fatura->faturaNo) === 0 ? substr($fatura->faturaNo, 0, 3) : null,
        );
    }

    /**
     * Hazır UBL XML'ini gönderir (kendi UBL üreticinizi kullanıyorsanız).
     *
     * @param string|null $seriOneki Verilirse numarayı servis bu seriden atar
     */
    public function gonderXml(
        string $ubl,
        string $ettn,
        string $aliciVkn,
        string $aliciEtiket,
        ?string $gondericiVkn = null,
        ?string $gondericiEtiket = null,
        ?string $seriOneki = null,
    ): GonderimSonucu {
        // SendInvoiceRequest sırası: SENDER, RECEIVER, XSLT_NAME, SERI_PREFIX, DISABLE_DATE_CONTROL, INVOICE
        $xml = '';
        if ($gondericiVkn !== null && $gondericiEtiket !== null) {
            $xml .= '<SENDER vkn="' . Xml::kacir($gondericiVkn) . '" alias="' . Xml::kacir($gondericiEtiket) . '"/>';
        }
        $xml .= '<RECEIVER vkn="' . Xml::kacir($aliciVkn) . '" alias="' . Xml::kacir($aliciEtiket) . '"/>'
            . Xml::eleman('SERI_PREFIX', $seriOneki)
            . self::faturaElemani($ubl, $ettn);

        $yanit = $this->oturum->cagir(ServisTipi::EFATURA, 'SendInvoiceRequest', $xml);

        return new GonderimSonucu($yanit->deger('INVOICE_ID'), $ettn);
    }

    /**
     * Faturayı taslak olarak yükler; GİB'e gönderilmez. Aynı ETTN ile tekrar
     * yüklemek içeriği günceller. Portaldan veya gonder() ile sonradan gönderilebilir.
     */
    public function taslakYukle(FaturaBilgisi $fatura): GonderimSonucu
    {
        if ($fatura->senaryo->earsivMi()) {
            throw new InvalidArgumentException('e-Arşiv taslağı için earsiv()->gonder(taslak: true) kullanın.');
        }

        $this->oturum->cagir(
            ServisTipi::EFATURA,
            'LoadInvoiceRequest',
            self::faturaElemani((new UblOlusturucu())->olustur($fatura), $fatura->ettn),
        );

        return new GonderimSonucu($fatura->faturaNo, $fatura->ettn, taslak: true);
    }

    /** Henüz gönderilmemiş taslağı siler. */
    public function taslakSil(string $ettn): void
    {
        $this->oturum->cagir(ServisTipi::EFATURA, 'CancelDraftInvoiceRequest', Xml::eleman('UUID', $ettn));
    }

    private static function faturaElemani(string $ubl, string $ettn): string
    {
        return '<INVOICE UUID="' . Xml::kacir($ettn) . '"><CONTENT>' . base64_encode($ubl) . '</CONTENT></INVOICE>';
    }

    /** @return list<Fatura> */
    private function ara(FaturaFiltresi $f, bool $baslikMi): array
    {
        $karsi = $f->karsiVkn !== null ? preg_replace('/\D/', '', $f->karsiVkn) : null;

        return $this->sorgula('GetInvoiceRequest', [
            'LIMIT' => $f->limit,
            'DATE_TYPE' => $f->olusturmaTarihi ? 'CREATE' : null,
            'START_DATE' => $f->baslangic->format('Y-m-d'),
            'END_DATE' => $f->bitis->format('Y-m-d'),
            'READ_INCLUDED' => $f->okunanlarDahil ? 'true' : 'false',
            'DIRECTION' => $f->yon->value,
            'SENDER' => $f->yon === Yon::GELEN ? $karsi : null,
            'RECEIVER' => $f->yon === Yon::GIDEN ? $karsi : null,
            'DRAFT_FLAG' => $f->taslaklar ? 'Y' : null,
        ], $baslikMi, $f->yon);
    }

    /**
     * @param array<string, string|int|null> $anahtar INVOICE_SEARCH_KEY alanları, XSD sırasıyla
     *
     * @return list<Fatura>
     */
    private function sorgula(string $kok, array $anahtar, bool $baslikMi, Yon $yon): array
    {
        try {
            $yanit = $this->oturum->cagir(
                ServisTipi::EFATURA,
                $kok,
                '<INVOICE_SEARCH_KEY>' . Xml::elemanlar($anahtar) . '</INVOICE_SEARCH_KEY>'
                . Xml::eleman('HEADER_ONLY', $baslikMi ? 'Y' : 'N'),
            );
        } catch (TurkbelgeHatasi $e) {
            if ($e->kayitYokMu()) {
                return [];
            }
            throw $e;
        }

        return array_map(static fn (XmlOkuyucu $x) => Fatura::xmlden($x, $yon), $yanit->cocuklar('INVOICE'));
    }
}
