<?php

declare(strict_types=1);

namespace Turkbelge\EFatura\Testler\Birim\Ubl;

use DOMDocument;
use DOMXPath;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Turkbelge\EFatura\Testler\Destek\OrnekFatura;
use Turkbelge\EFatura\Ubl\BelgeReferansi;
use Turkbelge\EFatura\Ubl\FaturaBilgisi;
use Turkbelge\EFatura\Ubl\FaturaTipi;
use Turkbelge\EFatura\Ubl\Iskonto;
use Turkbelge\EFatura\Ubl\Kalem;
use Turkbelge\EFatura\Ubl\Odeme;
use Turkbelge\EFatura\Ubl\Senaryo;
use Turkbelge\EFatura\Ubl\UblOlusturucu;

final class UblOlusturucuTest extends TestCase
{
    private const XSD = __DIR__ . '/../../.ubl-tr-xsd/maindoc/UBL-Invoice-2.1.xsd';

    /** @return iterable<string, array{0: FaturaBilgisi}> */
    public static function senaryolar(): iterable
    {
        $t = OrnekFatura::tarih();

        yield 'temel satış' => [OrnekFatura::temel([new Kalem('A', 2, 100, 20), new Kalem('B', 1.5, 33.333, 10, 'KGM', aciklama: 'Not')], ['notlar' => ['Test']])];
        yield 'ticari dövizli iskontolu' => [OrnekFatura::temel(
            [new Kalem('Ürün', 1, 10, 20, iskontolar: [Iskonto::oran(10), Iskonto::oran(20)], saticiKodu: 'SKU-1')],
            ['senaryo' => Senaryo::TICARI, 'paraBirimi' => 'USD', 'dovizKuru' => 41.2345,
             'siparis' => new BelgeReferansi('SIP-1', $t), 'irsaliyeler' => [new BelgeReferansi('IRS2026000000001', $t)],
             'odeme' => new Odeme(Odeme::EFT_HAVALE, $t->modify('+30 days'), 'TR00 0000 0000 0000 0000 0000 00', 'Örnek Bank', 'Merkez')], // gitleaks:allow (sahte IBAN)
        )];
        yield 'e-Arşiv TCKN' => [OrnekFatura::temel(null, ['senaryo' => Senaryo::EARSIV, 'alici' => OrnekFatura::aliciTckn(), 'seri' => 'EAR', 'gonderimSekli' => 'ELEKTRONIK'])];
        yield 'iade' => [OrnekFatura::temel(null, ['tip' => FaturaTipi::IADE, 'iadeEdilenler' => [new BelgeReferansi('ABC2026000000010', $t->modify('-5 days'))]])];
        yield 'istisna + XSLT' => [OrnekFatura::temel(
            [new Kalem('İstisna', 1, 500, 0, istisnaKodu: '301', istisnaAciklama: '11/1-a Mal ihracatı'), new Kalem('Normal', 1, 100, 20)],
            ['tip' => FaturaTipi::ISTISNA, 'xslt' => '<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform"/>'],
        )];
    }

    /**
     * GİB UBL-TR XSD'sine göre doğrular. Şemalar yoksa (composer ubl-xsd çalıştırılmadıysa) atlanır.
     * İmza bloğunu servis doldurduğu için doğrulamada yerine sahte bir eleman konur.
     */
    #[DataProvider('senaryolar')]
    public function testGibSemasinaUyar(FaturaBilgisi $f): void
    {
        if (!is_file(self::XSD)) {
            self::markTestSkipped('UBL-TR şemaları yok: composer ubl-xsd');
        }

        $xml = str_replace('<ext:ExtensionContent/>', '<ext:ExtensionContent><s:Imza xmlns:s="urn:sahte"/></ext:ExtensionContent>', (new UblOlusturucu())->olustur($f));
        $dom = new DOMDocument();
        $dom->loadXML($xml);
        libxml_use_internal_errors(true);
        $gecerli = $dom->schemaValidate(self::XSD);
        $hatalar = array_map(fn ($e) => trim($e->message), libxml_get_errors());
        libxml_clear_errors();

        self::assertTrue($gecerli, implode("\n", $hatalar));
    }

    private function xpath(FaturaBilgisi $f): DOMXPath
    {
        $dom = new DOMDocument();
        self::assertTrue($dom->loadXML((new UblOlusturucu())->olustur($f)));
        $xp = new DOMXPath($dom);
        $xp->registerNamespace('i', 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2');
        $xp->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        $xp->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');

        return $xp;
    }

    private static function deger(DOMXPath $xp, string $yol): ?string
    {
        $n = $xp->query($yol)->item(0);

        return $n?->textContent;
    }

    public function testBaslikAlanlari(): void
    {
        $xp = $this->xpath(OrnekFatura::temel());

        self::assertSame('TR1.2', self::deger($xp, '/i:Invoice/cbc:CustomizationID'));
        self::assertSame('TEMELFATURA', self::deger($xp, '/i:Invoice/cbc:ProfileID'));
        self::assertSame('ABC2026000000000', self::deger($xp, '/i:Invoice/cbc:ID'), 'Seriden yer tutucu numara');
        self::assertSame(OrnekFatura::ETTN, self::deger($xp, '/i:Invoice/cbc:UUID'));
        self::assertSame('2026-10-10', self::deger($xp, '/i:Invoice/cbc:IssueDate'));
        self::assertSame('14:30:00', self::deger($xp, '/i:Invoice/cbc:IssueTime'));
        self::assertSame('1', self::deger($xp, '/i:Invoice/cbc:LineCountNumeric'));
        self::assertSame('#Signature_ABC2026000000000', self::deger($xp, '//cac:Signature//cbc:URI'));
    }

    public function testToplamlarVeKdv(): void
    {
        $xp = $this->xpath(OrnekFatura::temel([new Kalem('A', 2, 100, 20), new Kalem('B', 1, 50, 10)]));

        self::assertSame('250.00', self::deger($xp, '//cac:LegalMonetaryTotal/cbc:LineExtensionAmount'));
        self::assertSame('250.00', self::deger($xp, '//cac:LegalMonetaryTotal/cbc:TaxExclusiveAmount'));
        self::assertSame('295.00', self::deger($xp, '//cac:LegalMonetaryTotal/cbc:PayableAmount'));
        self::assertSame('45.00', self::deger($xp, '/i:Invoice/cac:TaxTotal/cbc:TaxAmount'));
        self::assertSame(2, $xp->query('/i:Invoice/cac:TaxTotal/cac:TaxSubtotal')->length);
        self::assertSame(0, $xp->query('/i:Invoice/cac:AllowanceCharge')->length, 'İskonto yoksa belge iskontosu yazılmaz');
    }

    public function testIskontoTurkbelgeKuraliylaYazilir(): void
    {
        $xp = $this->xpath(OrnekFatura::temel([new Kalem('Ürün', 1, 10, 18, iskontolar: [Iskonto::oran(10, 'İndirim 1'), Iskonto::oran(20, 'İndirim 2')])]));

        self::assertSame('10.00', self::deger($xp, '//cac:LegalMonetaryTotal/cbc:LineExtensionAmount'), 'Brüt');
        self::assertSame('2.80', self::deger($xp, '//cac:LegalMonetaryTotal/cbc:AllowanceTotalAmount'));
        self::assertSame('7.20', self::deger($xp, '//cac:LegalMonetaryTotal/cbc:TaxExclusiveAmount'), 'Net');
        self::assertSame('8.50', self::deger($xp, '//cac:LegalMonetaryTotal/cbc:PayableAmount'));
        self::assertSame('Satır İskonto', self::deger($xp, '/i:Invoice/cac:AllowanceCharge/cbc:AllowanceChargeReason'));
        self::assertSame('2.80', self::deger($xp, '/i:Invoice/cac:AllowanceCharge/cbc:Amount'));
        self::assertSame('7.20', self::deger($xp, '//cac:InvoiceLine/cbc:LineExtensionAmount'));
        self::assertSame(['0.1', '0.2'], array_map(fn ($n) => $n->textContent, iterator_to_array($xp->query('//cac:InvoiceLine/cac:AllowanceCharge/cbc:MultiplierFactorNumeric'))));
        self::assertSame(['10.00', '9.00'], array_map(fn ($n) => $n->textContent, iterator_to_array($xp->query('//cac:InvoiceLine/cac:AllowanceCharge/cbc:BaseAmount'))));
    }

    public function testDovizKuruYazilir(): void
    {
        $xp = $this->xpath(OrnekFatura::temel(null, ['paraBirimi' => 'EUR', 'dovizKuru' => 47.5]));

        self::assertSame('EUR', self::deger($xp, '/i:Invoice/cbc:DocumentCurrencyCode'));
        self::assertSame('EUR', $xp->query('//cbc:PayableAmount/@currencyID')->item(0)?->textContent);
        self::assertSame('EUR', self::deger($xp, '//cac:PricingExchangeRate/cbc:SourceCurrencyCode'));
        self::assertSame('TRY', self::deger($xp, '//cac:PricingExchangeRate/cbc:TargetCurrencyCode'));
        self::assertSame('47.5', self::deger($xp, '//cac:PricingExchangeRate/cbc:CalculationRate'));
    }

    public function testTcknAliciKisiOlarakYazilir(): void
    {
        $xp = $this->xpath(OrnekFatura::temel(null, ['senaryo' => Senaryo::EARSIV, 'alici' => OrnekFatura::aliciTckn(), 'gonderimSekli' => 'KAGIT']));
        $alici = '//cac:AccountingCustomerParty/cac:Party';

        self::assertSame('TCKN', $xp->query("$alici/cac:PartyIdentification/cbc:ID/@schemeID")->item(0)?->textContent);
        self::assertSame('Ayşe', self::deger($xp, "$alici/cac:Person/cbc:FirstName"));
        self::assertSame('Yılmaz', self::deger($xp, "$alici/cac:Person/cbc:FamilyName"));
        self::assertSame(0, $xp->query("$alici/cac:PartyName")->length);
        self::assertSame('KAGIT', self::deger($xp, "//cac:AdditionalDocumentReference[cbc:DocumentTypeCode='SendingType']/cbc:DocumentType"));
    }

    public function testIadeReferansi(): void
    {
        $xp = $this->xpath(OrnekFatura::temel(null, ['tip' => FaturaTipi::IADE, 'iadeEdilenler' => [new BelgeReferansi('ABC2026000000010', new \DateTime('2026-10-05'))]]));

        self::assertSame('IADE', self::deger($xp, '/i:Invoice/cbc:InvoiceTypeCode'));
        self::assertSame('ABC2026000000010', self::deger($xp, '//cac:BillingReference/cac:InvoiceDocumentReference/cbc:ID'));
        self::assertSame('2026-10-05', self::deger($xp, '//cac:BillingReference/cac:InvoiceDocumentReference/cbc:IssueDate'));
        self::assertSame('IADE', self::deger($xp, '//cac:BillingReference/cac:InvoiceDocumentReference/cbc:DocumentTypeCode'));
    }

    public function testIstisnaKoduYalnizSifirKdvdeYazilir(): void
    {
        $xp = $this->xpath(OrnekFatura::temel([new Kalem('İstisna', 1, 500, 0, istisnaKodu: '301', istisnaAciklama: 'Mal ihracatı'), new Kalem('Normal', 1, 100, 20)], ['tip' => FaturaTipi::ISTISNA]));

        self::assertSame(['301'], array_map(fn ($n) => $n->textContent, iterator_to_array($xp->query('/i:Invoice/cac:TaxTotal//cbc:TaxExemptionReasonCode'))));
        self::assertSame('Mal ihracatı', self::deger($xp, '//cac:InvoiceLine[1]//cbc:TaxExemptionReason'));
        self::assertSame(0, $xp->query('//cac:InvoiceLine[2]//cbc:TaxExemptionReasonCode')->length);
    }

    public function testOzelKarakterlerKacirilir(): void
    {
        $xml = (new UblOlusturucu())->olustur(OrnekFatura::temel([new Kalem('A & B <özel> "ürün"', 1, 1, 20)]));

        self::assertStringContainsString('ALICI &amp; ORTAKLARI LTD.', $xml);
        self::assertStringContainsString('<cbc:Name>A &amp; B &lt;özel&gt; &quot;ürün&quot;</cbc:Name>', $xml);
    }

    public function testXsltGomulur(): void
    {
        $xslt = '<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform"/>';
        $xp = $this->xpath(OrnekFatura::temel(null, ['xslt' => $xslt]));

        $ek = '//cac:AdditionalDocumentReference[cbc:DocumentType="XSLT"]/cac:Attachment/cbc:EmbeddedDocumentBinaryObject';
        self::assertSame($xslt, base64_decode((string) self::deger($xp, $ek)));
        self::assertSame('ABC2026000000000.xslt', $xp->query("$ek/@filename")->item(0)?->textContent);
    }
}
