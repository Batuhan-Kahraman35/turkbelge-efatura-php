<?php

declare(strict_types=1);

namespace Turkbelge\EFatura\Testler\Birim;

use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Turkbelge\EFatura\Ayarlar;
use Turkbelge\EFatura\Hata\TurkbelgeHatasi;
use Turkbelge\EFatura\Istemci;
use Turkbelge\EFatura\Ortam;
use Turkbelge\EFatura\Testler\Destek\OrnekFatura;
use Turkbelge\EFatura\Testler\Destek\SahteTasiyici;
use Turkbelge\EFatura\Ubl\Senaryo;

final class GonderimTest extends TestCase
{
    private const ARSIV = 'http://schemas.i2i.com/ei/wsdl/archive';

    private SahteTasiyici $tasiyici;

    protected function setUp(): void
    {
        $this->tasiyici = new SahteTasiyici();
        $this->tasiyici->ekleGiris();
    }

    private function istemci(Ortam $ortam = Ortam::TEST): Istemci
    {
        return new Istemci(new Ayarlar('k', 's', $ortam), tasiyici: $this->tasiyici);
    }

    /** İstekteki base64 UBL'i çözer. */
    private function gonderilenUbl(string $eleman): string
    {
        self::assertMatchesRegularExpression("#<{$eleman}>([A-Za-z0-9+/=]+)</{$eleman}>#", $this->tasiyici->sonGovde());
        preg_match("#<{$eleman}>([A-Za-z0-9+/=]+)</{$eleman}>#", $this->tasiyici->sonGovde(), $m);

        return base64_decode($m[1]);
    }

    public function testEFaturaYerTutucuNumaradaSeriOnekiGonderilir(): void
    {
        $this->tasiyici->ekleYanit('SendInvoiceResponse', '<REQUEST_RETURN xmlns=""><INTL_TXN_ID>1</INTL_TXN_ID><RETURN_CODE>0</RETURN_CODE></REQUEST_RETURN><INVOICE_ID xmlns="">ABC2026000000007</INVOICE_ID>');

        $s = $this->istemci()->efatura()->gonder(OrnekFatura::temel(), 'urn:mail:pk@ornek.com', 'urn:mail:gb@ornek.com');

        self::assertSame('ABC2026000000007', $s->faturaNo, 'Servisin atadığı numara');
        self::assertSame(OrnekFatura::ETTN, $s->ettn);
        self::assertFalse($s->taslak);
        $govde = $this->tasiyici->sonGovde();
        self::assertStringContainsString(
            '<SENDER vkn="1234567890" alias="urn:mail:gb@ornek.com"/><RECEIVER vkn="9876543210" alias="urn:mail:pk@ornek.com"/>'
            . '<SERI_PREFIX>ABC</SERI_PREFIX><INVOICE UUID="' . OrnekFatura::ETTN . '"><CONTENT>',
            $govde,
        );
        self::assertStringContainsString('<cbc:ID>ABC2026000000000</cbc:ID>', $this->gonderilenUbl('CONTENT'));
    }

    public function testNumaraliFaturadaSeriOnekiGonderilmez(): void
    {
        $this->tasiyici->ekleYanit('SendInvoiceResponse', '<INVOICE_ID xmlns="">ABC2026000000042</INVOICE_ID>');

        $this->istemci()->efatura()->gonder(OrnekFatura::temel(null, ['faturaNo' => 'ABC2026000000042']), 'urn:mail:pk@ornek.com');

        self::assertStringNotContainsString('SERI_PREFIX', $this->tasiyici->sonGovde());
        self::assertStringNotContainsString('<SENDER', $this->tasiyici->sonGovde(), 'GB etiketi verilmezse SENDER yazılmaz');
    }

    public function testEArsivFaturasiEFaturadanGonderilemez(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->istemci()->efatura()->gonder(OrnekFatura::temel(null, ['senaryo' => Senaryo::EARSIV, 'gonderimSekli' => 'KAGIT']), 'pk');
    }

    public function testTaslakYuklenirVeSilinir(): void
    {
        $this->tasiyici->ekleYanit('LoadInvoiceResponse')->ekleYanit('CancelDraftInvoiceResponse');
        $e = $this->istemci()->efatura();

        $s = $e->taslakYukle(OrnekFatura::temel());
        self::assertTrue($s->taslak);
        self::assertStringContainsString('<ns:LoadInvoiceRequest', $this->tasiyici->istekler[1]['govde']);
        self::assertStringContainsString('<INVOICE UUID="' . OrnekFatura::ETTN . '"><CONTENT>', $this->tasiyici->istekler[1]['govde']);

        $e->taslakSil(OrnekFatura::ETTN);
        self::assertStringContainsString('<UUID>' . OrnekFatura::ETTN . '</UUID>', $this->tasiyici->sonGovde());
    }

    public function testEArsivGonderimiTestOrtamindaIsaretlenir(): void
    {
        $this->tasiyici->ekleYanit('ArchiveInvoiceExtendedResponse', '<INVOICE_ID xmlns="">EAR2026000000001</INVOICE_ID><WEB_KEY xmlns="">wk-1</WEB_KEY>', self::ARSIV);
        $f = OrnekFatura::temel(null, ['senaryo' => Senaryo::EARSIV, 'alici' => OrnekFatura::aliciTckn(), 'seri' => 'EAR', 'gonderimSekli' => 'ELEKTRONIK']);

        $s = $this->istemci()->earsiv()->gonder($f, epostalar: ['a@ornek.com', 'b@ornek.com']);

        self::assertSame(['EAR2026000000001', 'wk-1', false], [$s->faturaNo, $s->webAnahtari, $s->taslak]);
        self::assertStringContainsString(
            '<ArchiveInvoiceExtendedContent><INVOICE_PROPERTIES><EARSIV_FLAG>Y</EARSIV_FLAG><EARSIV_PROPERTIES>'
            . '<EARSIV_TYPE>NORMAL</EARSIV_TYPE><EARSIV_EMAIL_FLAG>Y</EARSIV_EMAIL_FLAG>'
            . '<EARSIV_EMAIL>a@ornek.com</EARSIV_EMAIL><EARSIV_EMAIL>b@ornek.com</EARSIV_EMAIL>'
            . '<SUB_STATUS>NEW</SUB_STATUS><SERI>EAR</SERI><EARCHIVE_TEST_FLAG>Y</EARCHIVE_TEST_FLAG>'
            . '</EARSIV_PROPERTIES><INVOICE_CONTENT>',
            $this->tasiyici->sonGovde(),
        );
        self::assertStringContainsString('<cbc:ProfileID>EARSIVFATURA</cbc:ProfileID>', $this->gonderilenUbl('INVOICE_CONTENT'));
    }

    public function testEArsivTaslakVeCanliOrtam(): void
    {
        $this->tasiyici->ekleYanit('ArchiveInvoiceExtendedResponse', '', self::ARSIV);
        $f = OrnekFatura::temel(null, ['senaryo' => Senaryo::EARSIV, 'faturaNo' => 'EAR2026000000005', 'gonderimSekli' => 'KAGIT']);

        $s = $this->istemci(Ortam::CANLI)->earsiv()->gonder($f, taslak: true);

        $govde = $this->tasiyici->sonGovde();
        self::assertTrue($s->taslak);
        self::assertStringContainsString('<EARSIV_EMAIL_FLAG>N</EARSIV_EMAIL_FLAG><SUB_STATUS>DRAFT</SUB_STATUS></EARSIV_PROPERTIES>', $govde);
        self::assertStringNotContainsString('EARCHIVE_TEST_FLAG', $govde, 'Canlıda test işareti olmamalı');
        self::assertStringNotContainsString('<SERI>', $govde, 'Numaralı faturada seri gönderilmez');
    }

    public function testEFaturaSenaryosuEArsivdenGonderilemez(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->istemci()->earsiv()->gonder(OrnekFatura::temel());
    }

    public function testEArsivIptalIstegiXsdSirasinda(): void
    {
        $this->tasiyici->ekleYanit('CancelEArchiveInvoiceResponse', '', self::ARSIV);

        $this->istemci()->earsiv()->iptal(OrnekFatura::ETTN, 'EAR2026000000001', new DateTimeImmutable('2026-10-10'), 'Hatalı tutar', ['a@ornek.com', 'b@ornek.com']);

        self::assertStringContainsString(
            '<CancelEArsivInvoiceContent><FATURA_UUID>' . OrnekFatura::ETTN . '</FATURA_UUID><FATURA_ID>EAR2026000000001</FATURA_ID>'
            . '<EARSIV_CANCEL_EMAIL>a@ornek.com,b@ornek.com</EARSIV_CANCEL_EMAIL><IPTAL_TARIHI>2026-10-10</IPTAL_TARIHI>'
            . '<IPTAL_NOTU>Hatalı tutar</IPTAL_NOTU></CancelEArsivInvoiceContent>',
            $this->tasiyici->sonGovde(),
        );
    }

    public function testServisHatasiAktarilir(): void
    {
        $this->tasiyici->ekleHata('ArchiveInvoiceExtendedResponse', 10013, 'Gönderilen seri ön eki sistemde tanım degildir');

        $this->expectException(TurkbelgeHatasi::class);
        $this->expectExceptionMessage('seri ön eki');
        $this->istemci()->earsiv()->gonder(OrnekFatura::temel(null, ['senaryo' => Senaryo::EARSIV, 'gonderimSekli' => 'KAGIT']));
    }
}
