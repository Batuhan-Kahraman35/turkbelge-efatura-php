<?php

declare(strict_types=1);

namespace Turkbelge\EFatura\Testler\Birim;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Turkbelge\EFatura\Ayarlar;
use Turkbelge\EFatura\Hata\TurkbelgeHatasi;
use Turkbelge\EFatura\Istemci;
use Turkbelge\EFatura\Model\EArsivDurumu;
use Turkbelge\EFatura\Model\EArsivFatura;
use Turkbelge\EFatura\Model\EArsivFiltresi;
use Turkbelge\EFatura\Testler\Destek\SahteTasiyici;
use Turkbelge\EFatura\Testler\Destek\ZipUretici;

final class EArsivServisiTest extends TestCase
{
    private const AD_ALANI = 'http://schemas.i2i.com/ei/wsdl/archive';
    private const ETTN = '2b0b7d3d-0000-4000-8000-000000000001';

    private SahteTasiyici $tasiyici;
    private Istemci $istemci;

    protected function setUp(): void
    {
        $this->tasiyici = new SahteTasiyici();
        $this->tasiyici->ekleGiris();
        $this->istemci = new Istemci(new Ayarlar('k', 's'), tasiyici: $this->tasiyici);
    }

    /** Servisin döndürdüğü yapıda bir e-Arşiv INVOICE elemanı (değerler örnek). */
    private static function fatura(string $no, string $ettn = self::ETTN, ?string $durumKodu = '130', string $icerik = ''): string
    {
        return '<INVOICE xmlns=""><HEADER><INVOICE_ID>' . $no . '</INVOICE_ID><UUID>' . $ettn . '</UUID>'
            . '<SENDER_NAME>GONDEREN A.S.</SENDER_NAME><SENDER_IDENTIFIER>1111111111</SENDER_IDENTIFIER>'
            . '<CUSTOMER_NAME>ALICI KISI</CUSTOMER_NAME><CUSTOMER_IDENTIFIER>11111111111</CUSTOMER_IDENTIFIER>'
            . '<PROFILE_ID>EARSIVFATURA</PROFILE_ID><INVOICE_TYPE>SATIS</INVOICE_TYPE><EARCHIVE_TYPE>NORMAL</EARCHIVE_TYPE>'
            . '<SENDING_TYPE>KAGIT</SENDING_TYPE>'
            . ($durumKodu !== null ? '<STATUS>RAPORLANDI</STATUS><STATUS_CODE>' . $durumKodu . '</STATUS_CODE>' : '')
            . '<ISSUE_DATE>29-12-2022 13:44:51</ISSUE_DATE><PAYABLE_AMOUNT>11.8</PAYABLE_AMOUNT>'
            . '<TAXABLE_AMOUNT>1.8</TAXABLE_AMOUNT><CURRENCY_CODE>TRY</CURRENCY_CODE></HEADER>'
            . ($icerik !== '' ? '<CONTENT>' . $icerik . '</CONTENT>' : '') . '</INVOICE>';
    }

    private function yanit(string $kok, string $ic = ''): void
    {
        $this->tasiyici->ekleYanit($kok, $ic, self::AD_ALANI);
    }

    public function testListeOkunurVeTarihNormallesir(): void
    {
        $this->yanit('GetEArchiveInvoiceListResponse', self::fatura('ABC2022000000001') . self::fatura('ABC2022000000002', 'u2', null));

        $liste = $this->istemci->earsiv()->liste(new EArsivFiltresi(new DateTimeImmutable('2022-01-01'), new DateTimeImmutable('2022-12-31')));

        self::assertCount(2, $liste);
        $f = $liste[0];
        self::assertSame('ABC2022000000001', $f->faturaNo);
        self::assertSame(self::ETTN, $f->ettn);
        self::assertSame('2022-12-29', $f->tarih, 'd-m-Y H:i:s biçimi Y-m-d olmalı');
        self::assertSame(['11.8', '1.8', 'TRY'], [$f->tutar, $f->matrah, $f->paraBirimi]);
        self::assertSame(['NORMAL', 'KAGIT'], [$f->earsivTuru, $f->gonderimSekli]);
        self::assertSame('11111111111', $f->aliciVkn);
        self::assertFalse($f->taslakMi());
        self::assertTrue($liste[1]->taslakMi(), 'Durum boşsa taslaktır');
    }

    public function testListeIstegiXsdSirasindaVeDateTimeIleKurulur(): void
    {
        $this->yanit('GetEArchiveInvoiceListResponse');

        $this->istemci->earsiv()->liste(new EArsivFiltresi(new DateTimeImmutable('2022-01-01 10:00'), new DateTimeImmutable('2022-01-31'), 'ABC', 50));

        self::assertStringContainsString(
            '<LIMIT>50</LIMIT><START_DATE>2022-01-01T00:00:00</START_DATE><END_DATE>2022-01-31T23:59:59</END_DATE>'
            . '<PREFIX>ABC</PREFIX><HEADER_ONLY>Y</HEADER_ONLY><READ_INCLUDED>true</READ_INCLUDED>',
            $this->tasiyici->sonGovde(),
        );
        self::assertStringContainsString('xmlns:ns="' . self::AD_ALANI . '"', $this->tasiyici->sonGovde());
        self::assertStringNotContainsString('INVOICE_SEARCH_KEY', $this->tasiyici->sonGovde(), 'e-Arşivde arama alanları doğrudan köktedir');
    }

    public function testTumuLimitDolarsaAraligiBoler(): void
    {
        $this->yanit('GetEArchiveInvoiceListResponse', self::fatura('A1', 'u1') . self::fatura('A2', 'u2'));
        $this->yanit('GetEArchiveInvoiceListResponse', self::fatura('A1', 'u1'));
        $this->yanit('GetEArchiveInvoiceListResponse', self::fatura('A3', 'u3'));

        $hepsi = iterator_to_array($this->istemci->earsiv()->tumu(
            new EArsivFiltresi(new DateTimeImmutable('2022-01-01'), new DateTimeImmutable('2022-01-04'), limit: 2)
        ), false);

        self::assertSame(['A1', 'A3'], array_map(fn ($f) => $f->faturaNo, $hepsi));
        self::assertStringContainsString('<START_DATE>2022-01-03T00:00:00</START_DATE><END_DATE>2022-01-04T23:59:59</END_DATE>', $this->tasiyici->sonGovde());
    }

    public function testGetirIcerikIster(): void
    {
        $ubl = '<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2"/>';
        $this->yanit('GetEArchiveInvoiceListResponse', self::fatura('A1', icerik: base64_encode(ZipUretici::uret(['a.xml' => $ubl]))));

        self::assertSame($ubl, $this->istemci->earsiv()->getir(self::ETTN)?->icerik);
        self::assertStringContainsString(
            '<LIMIT>1</LIMIT><UUID>' . self::ETTN . '</UUID><HEADER_ONLY>N</HEADER_ONLY><CONTENT_TYPE>XML</CONTENT_TYPE>',
            $this->tasiyici->sonGovde(),
        );
    }

    public function testGorselIcerikTuruIleIstenir(): void
    {
        $this->yanit('GetEArchiveInvoiceListResponse', self::fatura('A1', icerik: base64_encode('%PDF-1.4')));

        self::assertSame('%PDF-1.4', $this->istemci->earsiv()->gorsel(self::ETTN, 'pdf'));
        self::assertStringContainsString('<CONTENT_TYPE>PDF</CONTENT_TYPE>', $this->tasiyici->sonGovde());
    }

    public function testGorselYoksaHataVerir(): void
    {
        $this->yanit('GetEArchiveInvoiceListResponse');

        $this->expectException(TurkbelgeHatasi::class);
        $this->istemci->earsiv()->gorsel(self::ETTN);
    }

    public function testDurumOkunur(): void
    {
        $this->yanit('GetEArchiveInvoiceStatusResponse',
            '<INVOICE xmlns=""><HEADER><INVOICE_ID>A1</INVOICE_ID><UUID>u1</UUID><STATUS>130</STATUS>'
            . '<STATUS_DESC>RAPORLANDI</STATUS_DESC><EMAIL_STATUS>130</EMAIL_STATUS><REPORT_ID>42</REPORT_ID>'
            . '<WEB_KEY>wk</WEB_KEY></HEADER></INVOICE>'
            . '<INVOICE xmlns=""><HEADER><UUID>u2</UUID><STATUS>200</STATUS><STATUS_DESC>FATURA ID BULUNAMADI</STATUS_DESC></HEADER></INVOICE>'
        );

        [$a, $b] = $this->istemci->earsiv()->durumlar(['u1', 'u2']);

        self::assertSame(['u1', 'A1', EArsivDurumu::RAPORLANDI, EArsivDurumu::EPOSTA_SONLANDI, '42', 'wk'], [$a->ettn, $a->faturaNo, $a->durumKodu, $a->epostaDurumKodu, $a->raporNo, $a->webAnahtari]);
        self::assertTrue($a->raporlandiMi());
        self::assertFalse($b->bulunduMu());
        self::assertStringContainsString('<UUID>u1</UUID><UUID>u2</UUID>', $this->tasiyici->sonGovde());
    }

    public function testDurumSorgusu500erliParcalanir(): void
    {
        $ettnler = array_map(fn ($i) => "u$i", range(1, 501));
        $this->yanit('GetEArchiveInvoiceStatusResponse');
        $this->yanit('GetEArchiveInvoiceStatusResponse');

        $this->istemci->earsiv()->durumlar($ettnler);

        self::assertCount(3, $this->tasiyici->istekler, 'Giriş + 2 parça');
        self::assertSame(500, substr_count($this->tasiyici->istekler[1]['govde'], '<UUID>'));
        self::assertSame(1, substr_count($this->tasiyici->istekler[2]['govde'], '<UUID>'));
    }

    public function testIptalAlgilanir(): void
    {
        $this->yanit('GetEArchiveInvoiceListResponse', self::fatura('A1', durumKodu: '150') . str_replace('EARSIVFATURA', 'IPTAL', self::fatura('A2', 'u2')));

        $liste = $this->istemci->earsiv()->liste(new EArsivFiltresi(new DateTimeImmutable('2022-01-01'), new DateTimeImmutable('2022-01-01')));

        self::assertTrue($liste[0]->iptalEdildiMi());
        self::assertTrue($liste[1]->iptalEdildiMi());
    }

    public function testTarihBicimleri(): void
    {
        self::assertSame('2022-12-29', EArsivFatura::tarih('29-12-2022 13:44:51'));
        self::assertSame('2022-12-29', EArsivFatura::tarih('29.12.2022'));
        self::assertSame('2022-12-29', EArsivFatura::tarih('2022-12-29T13:44:51+03:00'));
        self::assertNull(EArsivFatura::tarih(null));
    }

    public function testFiltreDenetimleri(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new EArsivFiltresi(new DateTimeImmutable('2022-01-01'), new DateTimeImmutable('2022-01-01'), 'abcd');
    }
}
