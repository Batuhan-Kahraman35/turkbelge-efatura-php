<?php

declare(strict_types=1);

namespace Turkbelge\EFatura\Servis;

use InvalidArgumentException;
use Turkbelge\EFatura\Hata\TurkbelgeHatasi;
use Turkbelge\EFatura\Model\EArsivDurumu;
use Turkbelge\EFatura\Model\EArsivFatura;
use Turkbelge\EFatura\Model\EArsivFiltresi;
use Turkbelge\EFatura\Model\GonderimSonucu;
use Turkbelge\EFatura\Oturum\OturumYoneticisi;
use Turkbelge\EFatura\ServisTipi;
use Turkbelge\EFatura\Tasima\Xml;
use Turkbelge\EFatura\Ubl\FaturaBilgisi;
use Turkbelge\EFatura\Ubl\UblOlusturucu;
use Turkbelge\EFatura\Yardimci\FaturaNo;

/**
 * e-Arşiv servisi (EFaturaArchive): gönderim, iptal, listeleme, içerik, görsel ve durum sorgusu.
 */
final class EArsivServisi
{
    public const GORSEL_TURLERI = ['PDF', 'HTML', 'XML'];
    public const DURUM_SORGU_SINIRI = 500;

    public const TUR_NORMAL = 'NORMAL';
    /** İnternet satışı: e-ticaret bilgileri (web adresi, ödeme, kargo) UBL'de bulunmalıdır */
    public const TUR_INTERNET = 'INTERNET';

    /**
     * @param bool $testOrtami Test ortamında faturalar EARCHIVE_TEST_FLAG ile işaretlenir
     */
    public function __construct(
        private readonly OturumYoneticisi $oturum,
        private readonly bool $testOrtami = false,
    ) {
    }

    /**
     * e-Arşiv faturasını UBL'den üretip gönderir.
     *
     * Fatura numarası yer tutucuysa (ABC2026000000000) seri gönderilir ve numarayı
     * servis atar. Taslak olarak gönderilen fatura portaldan onaylanana kadar raporlanmaz.
     *
     * @param list<string> $epostalar Fatura bu adreslere e-posta ile de gönderilir
     */
    public function gonder(
        FaturaBilgisi $fatura,
        bool $taslak = false,
        array $epostalar = [],
        string $tur = self::TUR_NORMAL,
    ): GonderimSonucu {
        if (!$fatura->senaryo->earsivMi()) {
            throw new InvalidArgumentException('e-Arşiv servisi yalnız EARSIVFATURA senaryosunu gönderir.');
        }
        if (!in_array($tur, [self::TUR_NORMAL, self::TUR_INTERNET], true)) {
            throw new InvalidArgumentException('e-Arşiv türü NORMAL veya INTERNET olmalı.');
        }

        $seri = FaturaNo::sira($fatura->faturaNo) === 0 ? substr($fatura->faturaNo, 0, 3) : null;

        // INVOICE_PROPERTIES sırası: EARSIV_FLAG, EARSIV_PROPERTIES, PDF_PROPERTIES, ARCHIVE_NOTE, INVOICE_CONTENT
        // EARSIV_PROPERTIES sırası: EARSIV_TYPE, EARSIV_EMAIL_FLAG, EARSIV_EMAIL*, SUB_STATUS, ARCH_INVOICE_ID, SERI, EARCHIVE_TEST_FLAG
        $ozellikler = Xml::elemanlar([
            'EARSIV_TYPE' => $tur,
            'EARSIV_EMAIL_FLAG' => $epostalar === [] ? 'N' : 'Y',
        ]);
        foreach ($epostalar as $eposta) {
            $ozellikler .= Xml::eleman('EARSIV_EMAIL', $eposta);
        }
        $ozellikler .= Xml::elemanlar([
            'SUB_STATUS' => $taslak ? 'DRAFT' : 'NEW',
            'SERI' => $seri,
            'EARCHIVE_TEST_FLAG' => $this->testOrtami ? 'Y' : null,
        ]);

        $yanit = $this->oturum->cagir(
            ServisTipi::EARSIV,
            'ArchiveInvoiceExtendedRequest',
            '<ArchiveInvoiceExtendedContent><INVOICE_PROPERTIES>'
            . Xml::eleman('EARSIV_FLAG', 'Y')
            . '<EARSIV_PROPERTIES>' . $ozellikler . '</EARSIV_PROPERTIES>'
            . '<INVOICE_CONTENT>' . base64_encode((new UblOlusturucu())->olustur($fatura)) . '</INVOICE_CONTENT>'
            . '</INVOICE_PROPERTIES></ArchiveInvoiceExtendedContent>',
        );

        return new GonderimSonucu($yanit->deger('INVOICE_ID'), $fatura->ettn, $yanit->deger('WEB_KEY'), $taslak);
    }

    /**
     * e-Arşiv faturasını iptal eder; GİB'e iptal olarak raporlanır.
     *
     * @param list<string> $epostalar İptal bildirimi gönderilecek adresler
     */
    public function iptal(
        string $ettn,
        ?string $faturaNo = null,
        ?\DateTimeInterface $iptalTarihi = null,
        ?string $not = null,
        array $epostalar = [],
    ): void {
        // CancelEArsivInvoiceContent sırası: UPLOAD_FLAG, FATURA_UUID, FATURA_ID, EARSIV_CANCEL_EMAIL, DELETE_FLAG, IPTAL_TARIHI, TOPLAM_TUTAR, INVOICE_CONTENT, IPTAL_NOTU
        $this->oturum->cagir(
            ServisTipi::EARSIV,
            'CancelEArchiveInvoiceRequest',
            '<CancelEArsivInvoiceContent>' . Xml::elemanlar([
                'FATURA_UUID' => $ettn,
                'FATURA_ID' => $faturaNo,
                'EARSIV_CANCEL_EMAIL' => $epostalar === [] ? null : implode(',', $epostalar),
                'IPTAL_TARIHI' => $iptalTarihi?->format('Y-m-d'),
                'IPTAL_NOTU' => $not,
            ]) . '</CancelEArsivInvoiceContent>',
        );
    }

    /**
     * Tek istekte fatura başlıklarını listeler (taslaklar dahil).
     *
     * @return list<EArsivFatura>
     */
    public function liste(EArsivFiltresi $filtre): array
    {
        return $this->sorgula([
            'LIMIT' => $filtre->limit,
            'START_DATE' => $filtre->baslangic->format('Y-m-d\TH:i:s'),
            'END_DATE' => $filtre->bitis->format('Y-m-d\TH:i:s'),
            'PREFIX' => $filtre->seri,
            'HEADER_ONLY' => 'Y',
            'READ_INCLUDED' => 'true',
        ]);
    }

    /**
     * Tarih aralığındaki tüm faturalar; sonuç limite ulaşırsa aralığı ikiye böler.
     *
     * @return \Generator<int, EArsivFatura>
     */
    public function tumu(EArsivFiltresi $filtre): \Generator
    {
        $faturalar = $this->liste($filtre);
        if (count($faturalar) < $filtre->limit) {
            yield from $faturalar;

            return;
        }

        if ($filtre->gunSayisi() === 1) {
            throw new \LengthException(sprintf(
                '%s tarihinde %d e-Arşiv faturadan fazlası var; limiti artırın.',
                $filtre->baslangic->format('d.m.Y'),
                $filtre->limit,
            ));
        }

        $ortaGun = intdiv($filtre->gunSayisi(), 2);
        $orta = $filtre->baslangic->modify("+{$ortaGun} days");
        yield from $this->tumu($filtre->aralikla($filtre->baslangic, $orta->modify('-1 day')));
        yield from $this->tumu($filtre->aralikla($orta, $filtre->bitis));
    }

    /** Faturayı UBL XML içeriğiyle getirir. Bulunamazsa null. */
    public function getir(string $ettn): ?EArsivFatura
    {
        return $this->tekil($ettn, 'XML');
    }

    /**
     * Faturanın görüntüsü (PDF ikili verisi, HTML veya XML metni).
     *
     * e-Arşivde ayrı bir görsel servisi yoktur; içerik listeleme servisine
     * ETTN ve içerik türü verilerek alınır.
     */
    public function gorsel(string $ettn, string $tur = 'PDF'): string
    {
        $tur = strtoupper($tur);
        if (!in_array($tur, self::GORSEL_TURLERI, true)) {
            throw new InvalidArgumentException('Görsel türü PDF, HTML veya XML olmalı.');
        }

        $fatura = $this->tekil($ettn, $tur);
        if ($fatura?->icerik === null) {
            throw new TurkbelgeHatasi("e-Arşiv fatura görseli bulunamadı: {$ettn}", TurkbelgeHatasi::KAYIT_BULUNAMADI);
        }

        return $fatura->icerik;
    }

    public function durum(string $ettn): EArsivDurumu
    {
        return $this->durumlar([$ettn])[0]
            ?? throw new TurkbelgeHatasi("e-Arşiv durum yanıtı boş: {$ettn}", TurkbelgeHatasi::KAYIT_BULUNAMADI);
    }

    /**
     * Birden çok faturanın durumu. Servis sınırı istek başına 500 ETTN'dir;
     * daha fazlası otomatik olarak parçalanır.
     *
     * @param list<string> $ettnler
     *
     * @return list<EArsivDurumu>
     */
    public function durumlar(array $ettnler): array
    {
        $sonuc = [];
        foreach (array_chunk(array_values($ettnler), self::DURUM_SORGU_SINIRI) as $parca) {
            $yanit = $this->oturum->cagir(
                ServisTipi::EARSIV,
                'GetEArchiveInvoiceStatusRequest',
                implode('', array_map(static fn (string $u) => Xml::eleman('UUID', $u), $parca)),
            );
            foreach ($yanit->cocuklar('INVOICE') as $fatura) {
                $sonuc[] = EArsivDurumu::xmlden($fatura);
            }
        }

        return $sonuc;
    }

    private function tekil(string $ettn, string $icerikTuru): ?EArsivFatura
    {
        return $this->sorgula([
            'LIMIT' => 1,
            'UUID' => $ettn,
            'HEADER_ONLY' => 'N',
            'CONTENT_TYPE' => $icerikTuru,
            'READ_INCLUDED' => 'true',
        ])[0] ?? null;
    }

    /**
     * @param array<string, string|int|null> $alanlar XSD sırasıyla: LIMIT, ID, UUID, START_DATE, END_DATE,
     *                                               PERIOD, PREFIX, …, HEADER_ONLY, CONTENT_TYPE, READ_INCLUDED
     *
     * @return list<EArsivFatura>
     */
    private function sorgula(array $alanlar): array
    {
        try {
            $yanit = $this->oturum->cagir(ServisTipi::EARSIV, 'GetEArchiveInvoiceListRequest', Xml::elemanlar($alanlar));
        } catch (TurkbelgeHatasi $e) {
            if ($e->kayitYokMu()) {
                return [];
            }
            throw $e;
        }

        return array_map(EArsivFatura::xmlden(...), $yanit->cocuklar('INVOICE'));
    }
}
