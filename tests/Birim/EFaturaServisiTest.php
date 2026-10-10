<?php

declare(strict_types=1);

namespace Turkbelge\EFatura\Testler\Birim;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Turkbelge\EFatura\Ayarlar;
use Turkbelge\EFatura\Hata\TurkbelgeHatasi;
use Turkbelge\EFatura\Istemci;
use Turkbelge\EFatura\Model\FaturaFiltresi;
use Turkbelge\EFatura\Model\Yon;
use Turkbelge\EFatura\Testler\Destek\SahteTasiyici;
use Turkbelge\EFatura\Testler\Destek\ZipUretici;

final class EFaturaServisiTest extends TestCase
{
    private const ETTN = 'd70105cc-0000-4000-8000-000000000001';

    private SahteTasiyici $tasiyici;
    private Istemci $istemci;

    protected function setUp(): void
    {
        $this->tasiyici = new SahteTasiyici();
        $this->tasiyici->ekleGiris();
        $this->istemci = new Istemci(new Ayarlar('k', 's'), tasiyici: $this->tasiyici);
    }

    /** Servisin döndürdüğü yapıda bir INVOICE elemanı (değerler örnek). */
    private static function fatura(string $no, string $ettn = self::ETTN, string $icerik = ''): string
    {
        return '<INVOICE xmlns="" ID="' . $no . '" UUID="' . $ettn . '"><HEADER>'
            . '<SENDER>1111111111</SENDER><RECEIVER>2222222222</RECEIVER><SUPPLIER>GONDEREN A.S.</SUPPLIER>'
            . '<CUSTOMER>ALICI LTD.</CUSTOMER><ISSUE_DATE>2024-12-13+03:00</ISSUE_DATE>'
            . '<PAYABLE_AMOUNT currencyID="TRY">1.2</PAYABLE_AMOUNT><FROM>urn:mail:gb@ornek.com</FROM>'
            . '<TO>urn:mail:pk@ornek.com</TO><PROFILEID>TICARIFATURA</PROFILEID><INVOICE_TYPE_CODE>SATIS</INVOICE_TYPE_CODE>'
            . '<STATUS>SEND - WAIT_APPLICATION_RESPONSE</STATUS><STATUS_DESCRIPTION>ALICIDAN ONAY BEKLİYOR</STATUS_DESCRIPTION>'
            . '<GIB_STATUS_CODE>1300</GIB_STATUS_CODE><GIB_STATUS_DESCRIPTION>BASARIYLA_TAMAMLANDI</GIB_STATUS_DESCRIPTION>'
            . '<CDATE>2024-12-13T15:42:29.000+03:00</CDATE><STATUS_CODE>111</STATUS_CODE><DIRECTION>OUT</DIRECTION>'
            . '</HEADER>' . ($icerik !== '' ? '<CONTENT>' . $icerik . '</CONTENT>' : '') . '</INVOICE>';
    }

    private function filtre(int $limit = 1000, ?string $karsi = null, Yon $yon = Yon::GIDEN, string $bas = '2024-01-01', string $bit = '2024-12-31'): FaturaFiltresi
    {
        return new FaturaFiltresi($yon, new DateTimeImmutable($bas), new DateTimeImmutable($bit), $karsi, limit: $limit);
    }

    public function testListeBasliklariOkunur(): void
    {
        $this->tasiyici->ekleYanit('GetInvoiceResponse', self::fatura('ABC2024000000001') . self::fatura('ABC2024000000002', 'diger'));

        $liste = $this->istemci->efatura()->liste($this->filtre());

        self::assertCount(2, $liste);
        $f = $liste[0];
        self::assertSame('ABC2024000000001', $f->faturaNo);
        self::assertSame(self::ETTN, $f->ettn);
        self::assertSame('2024-12-13', $f->tarih, 'Saat dilimi eki atılmalı');
        self::assertSame('1.2', $f->tutar);
        self::assertSame('TRY', $f->paraBirimi);
        self::assertSame('TICARIFATURA', $f->senaryo);
        self::assertSame('111', $f->durumKodu);
        self::assertSame('1300', $f->gibDurumKodu);
        self::assertSame('GONDEREN A.S.', $f->gonderenUnvan);
        self::assertSame(Yon::GIDEN, $f->yon);
        self::assertNull($f->icerik);
    }

    public function testListedeDirectionYoksaIstektekiYonKullanilir(): void
    {
        $this->tasiyici->ekleYanit('GetInvoiceResponse', str_replace('<DIRECTION>OUT</DIRECTION>', '', self::fatura('A1')));

        self::assertSame(Yon::GELEN, $this->istemci->efatura()->liste($this->filtre(yon: Yon::GELEN))[0]->yon);
    }

    public function testListeIstegiXsdSirasindaKurulur(): void
    {
        $this->tasiyici->ekleYanit('GetInvoiceResponse');

        $this->istemci->efatura()->liste($this->filtre(50, '222-222-2222'));

        self::assertStringContainsString(
            '<INVOICE_SEARCH_KEY><LIMIT>50</LIMIT><START_DATE>2024-01-01</START_DATE><END_DATE>2024-12-31</END_DATE>'
            . '<READ_INCLUDED>true</READ_INCLUDED><DIRECTION>OUT</DIRECTION><RECEIVER>2222222222</RECEIVER>'
            . '</INVOICE_SEARCH_KEY><HEADER_ONLY>Y</HEADER_ONLY>',
            $this->tasiyici->sonGovde(),
        );
    }

    public function testGelendeKarsiVknGondericidir(): void
    {
        $this->tasiyici->ekleYanit('GetInvoiceResponse');

        $this->istemci->efatura()->liste($this->filtre(karsi: '1111111111', yon: Yon::GELEN));

        self::assertStringContainsString('<DIRECTION>IN</DIRECTION><SENDER>1111111111</SENDER>', $this->tasiyici->sonGovde());
    }

    public function testKayitYokHatasiBosListedir(): void
    {
        $this->tasiyici->ekleHata('GetInvoiceResponse', 10008);

        self::assertSame([], $this->istemci->efatura()->liste($this->filtre()));
    }

    public function testTumuLimitDolarsaAraligiBoler(): void
    {
        // 4 günlük aralık, limit 2: ilk istek dolu → [1-2] ve [3-4] olarak bölünür
        $this->tasiyici
            ->ekleYanit('GetInvoiceResponse', self::fatura('A1', 'u1') . self::fatura('A2', 'u2'))
            ->ekleYanit('GetInvoiceResponse', self::fatura('A1', 'u1'))
            ->ekleYanit('GetInvoiceResponse', self::fatura('A3', 'u3'));

        $hepsi = iterator_to_array($this->istemci->efatura()->tumu($this->filtre(2, bas: '2024-01-01', bit: '2024-01-04')), false);

        self::assertSame(['A1', 'A3'], array_map(fn ($f) => $f->faturaNo, $hepsi));
        $govdeler = array_column(array_slice($this->tasiyici->istekler, 1), 'govde');
        self::assertStringContainsString('<START_DATE>2024-01-01</START_DATE><END_DATE>2024-01-04</END_DATE>', $govdeler[0]);
        self::assertStringContainsString('<START_DATE>2024-01-01</START_DATE><END_DATE>2024-01-02</END_DATE>', $govdeler[1]);
        self::assertStringContainsString('<START_DATE>2024-01-03</START_DATE><END_DATE>2024-01-04</END_DATE>', $govdeler[2]);
    }

    public function testTumuTekGundeDoluysaEksikVermez(): void
    {
        $this->tasiyici->ekleYanit('GetInvoiceResponse', self::fatura('A1', 'u1') . self::fatura('A2', 'u2'));

        $this->expectException(\LengthException::class);
        iterator_to_array($this->istemci->efatura()->tumu($this->filtre(2, bas: '2024-01-01', bit: '2024-01-01')));
    }

    public function testGetirIcerigiCozer(): void
    {
        $ubl = '<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2"/>';
        $this->tasiyici->ekleYanit('GetInvoiceResponse', self::fatura('A1', icerik: base64_encode($ubl)));

        $f = $this->istemci->efatura()->getir(self::ETTN, Yon::GIDEN);

        self::assertSame($ubl, $f?->icerik);
        self::assertStringContainsString('<UUID>' . self::ETTN . '</UUID>', $this->tasiyici->sonGovde());
        self::assertStringContainsString('<HEADER_ONLY>N</HEADER_ONLY>', $this->tasiyici->sonGovde());
    }

    public function testGetirBulamazsaNullDoner(): void
    {
        $this->tasiyici->ekleYanit('GetInvoiceResponse');

        self::assertNull($this->istemci->efatura()->getir('yok', Yon::GELEN));
    }

    public function testGorselZiptenCikarilir(): void
    {
        $zip = ZipUretici::uret(['d70105cc.pdf' => '%PDF-1.4 ornek']);
        $this->tasiyici->ekleYanit('GetInvoiceWithTypeResponse', self::fatura('A1', icerik: base64_encode($zip)));

        $pdf = $this->istemci->efatura()->gorsel(self::ETTN, Yon::GIDEN, 'pdf');

        self::assertSame('%PDF-1.4 ornek', $pdf);
        self::assertStringContainsString(
            '<INVOICE_SEARCH_KEY><LIMIT>1</LIMIT><UUID>' . self::ETTN . '</UUID><TYPE>PDF</TYPE>',
            $this->tasiyici->sonGovde(),
        );
    }

    public function testGorselBulunamazsaHataVerir(): void
    {
        $this->tasiyici->ekleYanit('GetInvoiceWithTypeResponse');

        $this->expectException(TurkbelgeHatasi::class);
        $this->istemci->efatura()->gorsel(self::ETTN, Yon::GIDEN);
    }

    public function testGecersizGorselTuruReddedilir(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->istemci->efatura()->gorsel(self::ETTN, Yon::GIDEN, 'DOCX');
    }

    public function testTekilDurumVeHaricIptalOkunur(): void
    {
        $this->tasiyici->ekleYanit('GetInvoiceStatusResponse',
            '<INVOICE_STATUS xmlns="" ID="A1" UUID="' . self::ETTN . '"><STATUS>SEND - SUCCEED</STATUS>'
            . '<STATUS_DESCRIPTION>SUCCEED</STATUS_DESCRIPTION><GIB_STATUS_CODE>1300</GIB_STATUS_CODE>'
            . '<GIB_STATUS_DESCRIPTION>BASARIYLA_TAMAMLANDI</GIB_STATUS_DESCRIPTION><STATUS_CODE>130</STATUS_CODE>'
            . '<EXTERNAL_CANCEL_FLAG>Y</EXTERNAL_CANCEL_FLAG></INVOICE_STATUS>'
        );

        $d = $this->istemci->efatura()->durum(self::ETTN);

        self::assertSame(self::ETTN, $d->ettn);
        self::assertSame('A1', $d->faturaNo);
        self::assertSame('130', $d->durumKodu);
        self::assertSame('1300', $d->gibDurumKodu);
        self::assertTrue($d->iptalEdildiMi(), 'GİB "başarılı" dese de harici iptal bayrağı esas alınmalı');
        self::assertStringContainsString('<INVOICE UUID="' . self::ETTN . '"/>', $this->tasiyici->sonGovde());
    }

    public function testIptalBayragiGelmezseBilinmiyordur(): void
    {
        $this->tasiyici->ekleYanit('GetInvoiceStatusResponse',
            '<INVOICE_STATUS xmlns="" UUID="' . self::ETTN . '"><STATUS>X</STATUS><STATUS_CODE>111</STATUS_CODE></INVOICE_STATUS>'
        );

        self::assertNull($this->istemci->efatura()->durum(self::ETTN)->iptalEdildiMi());
    }

    public function testTopluDurumHeaderIcindenOkunur(): void
    {
        $this->tasiyici->ekleYanit('GetInvoiceStatusAllResponse',
            '<INVOICE_STATUS xmlns="" ID="A1" UUID="u1"><HEADER><STATUS>S1</STATUS><STATUS_CODE>111</STATUS_CODE>'
            . '<GIB_STATUS_CODE>1300</GIB_STATUS_CODE></HEADER></INVOICE_STATUS>'
            . '<INVOICE_STATUS xmlns="" ID="A2" UUID="u2"><HEADER><STATUS>S2</STATUS><STATUS_CODE>130</STATUS_CODE></HEADER></INVOICE_STATUS>'
        );

        $durumlar = $this->istemci->efatura()->durumlar(['u1', 'u2']);

        self::assertSame(['u1', 'u2'], array_map(fn ($d) => $d->ettn, $durumlar));
        self::assertSame(['111', '130'], array_map(fn ($d) => $d->durumKodu, $durumlar));
        self::assertSame('1300', $durumlar[0]->gibDurumKodu);
        self::assertStringContainsString('<UUID>u1</UUID><UUID>u2</UUID>', $this->tasiyici->sonGovde());
    }

    public function testBosTopluDurumdaServiseGidilmez(): void
    {
        self::assertSame([], $this->istemci->efatura()->durumlar([]));
        self::assertSame([], $this->tasiyici->istekler);
    }

    public function testSayim(): void
    {
        $this->tasiyici->ekleYanit('GetInvoiceCountResponse', '<PORTAL xmlns="">6</PORTAL><WS xmlns="">2</WS>');

        $s = $this->istemci->efatura()->sayim(Yon::GIDEN, new DateTimeImmutable('2024-01-01'), new DateTimeImmutable('2024-12-31'));

        self::assertSame(['portal' => 6, 'servis' => 2], $s);
    }
}
