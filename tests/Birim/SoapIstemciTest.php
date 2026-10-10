<?php

declare(strict_types=1);

namespace Turkbelge\EFatura\Testler\Birim;

use PHPUnit\Framework\TestCase;
use Turkbelge\EFatura\Ayarlar;
use Turkbelge\EFatura\Hata\BaglantiHatasi;
use Turkbelge\EFatura\Hata\TurkbelgeHatasi;
use Turkbelge\EFatura\ServisTipi;
use Turkbelge\EFatura\Tasima\SoapIstemci;
use Turkbelge\EFatura\Testler\Destek\SahteTasiyici;

final class SoapIstemciTest extends TestCase
{
    private SahteTasiyici $tasiyici;
    private SoapIstemci $soap;

    protected function setUp(): void
    {
        $this->tasiyici = new SahteTasiyici();
        $this->soap = new SoapIstemci(new Ayarlar('k', 's', uygulamaAdi: 'uyg', kanalAdi: 'kanal'), $this->tasiyici);
    }

    public function testZarfVeBaslikXsdSirasindaKurulur(): void
    {
        $this->tasiyici->ekleYanit('CheckUserResponse');

        $this->soap->cagir(ServisTipi::EFATURA, 'CheckUserRequest', '<USER/>', 'OTR');

        $govde = $this->tasiyici->sonGovde();
        self::assertStringContainsString('<ns:CheckUserRequest xmlns:ns="http://schemas.i2i.com/ei/wsdl">', $govde);
        self::assertStringContainsString(
            '<REQUEST_HEADER><SESSION_ID>OTR</SESSION_ID><APPLICATION_NAME>uyg</APPLICATION_NAME>'
            . '<CHANNEL_NAME>kanal</CHANNEL_NAME><COMPRESSED>N</COMPRESSED></REQUEST_HEADER><USER/>',
            $govde,
        );
        self::assertStringEndsWith('/EFaturaOIB', $this->tasiyici->istekler[0]['adres']);
    }

    public function testEarsivAdAlaniKullanilir(): void
    {
        $this->tasiyici->ekleYanit('X');
        $this->soap->cagir(ServisTipi::EARSIV, 'GetEArchiveInvoiceListRequest', '', 'OTR');

        self::assertStringContainsString('xmlns:ns="http://schemas.i2i.com/ei/wsdl/archive"', $this->tasiyici->sonGovde());
    }

    public function testYanitKokuDoner(): void
    {
        $this->tasiyici->ekleGiris('abc');

        $yanit = $this->soap->cagir(ServisTipi::KIMLIK, 'LoginRequest', '', '-1');

        self::assertSame('LoginResponse', $yanit->ad());
        self::assertSame('abc', $yanit->deger('SESSION_ID'));
    }

    public function testErrorTypeIstisnayaCevrilir(): void
    {
        $this->tasiyici->ekleHata('GetInvoiceResponse', 10010, 'Boyut sınırı');

        try {
            $this->soap->cagir(ServisTipi::EFATURA, 'GetInvoiceRequest', '', 'OTR');
            self::fail('İstisna bekleniyordu');
        } catch (TurkbelgeHatasi $e) {
            self::assertSame(10010, $e->hataKodu);
            self::assertStringContainsString('Boyut sınırı', $e->getMessage());
            self::assertSame('1', $e->islemNo);
        }
    }

    public function testRequestFaultIsHatasiOlarakOkunur(): void
    {
        $this->tasiyici->ekle(
            '<S:Envelope xmlns:S="http://schemas.xmlsoap.org/soap/envelope/"><S:Body><S:Fault>'
            . '<faultcode>S:Server</faultcode><faultstring>x</faultstring><detail>'
            . '<ns2:RequestFault xmlns:ns2="http://schemas.i2i.com/ei/wsdl"><INTL_TXN_ID>9</INTL_TXN_ID>'
            . '<ERROR_CODE>10004</ERROR_CODE><ERROR_SHORT_DES>Oturum geçersiz</ERROR_SHORT_DES></ns2:RequestFault>'
            . '</detail></S:Fault></S:Body></S:Envelope>',
            500,
        );

        $this->expectException(TurkbelgeHatasi::class);
        $this->expectExceptionCode(10004);
        $this->soap->cagir(ServisTipi::EFATURA, 'GetInvoiceRequest', '', 'OTR');
    }

    public function testDuzSoapFaultBaglantiHatasidir(): void
    {
        $this->tasiyici->ekle(
            '<S:Envelope xmlns:S="http://schemas.xmlsoap.org/soap/envelope/"><S:Body><S:Fault>'
            . '<faultcode>S:Client</faultcode><faultstring>Cannot find dispatch method</faultstring>'
            . '</S:Fault></S:Body></S:Envelope>',
            500,
        );

        $this->expectException(BaglantiHatasi::class);
        $this->expectExceptionMessage('Cannot find dispatch method');
        $this->soap->cagir(ServisTipi::EFATURA, 'YokRequest', '', 'OTR');
    }

    public function testXmlOlmayanYanitBaglantiHatasidir(): void
    {
        $this->tasiyici->ekle('<html><body>403 Forbidden', 403);

        try {
            $this->soap->cagir(ServisTipi::KIMLIK, 'LoginRequest', '', '-1');
            self::fail('İstisna bekleniyordu');
        } catch (BaglantiHatasi $e) {
            self::assertSame(403, $e->httpKodu);
        }
    }
}
