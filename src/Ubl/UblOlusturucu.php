<?php

declare(strict_types=1);

namespace Turkbelge\EFatura\Ubl;

use Turkbelge\EFatura\Tasima\Xml;

/**
 * UBL-TR 1.2 fatura XML'i üretir.
 *
 * Eleman sırası GİB UBL-TR 1.2.1 paketindeki UBL-Invoice-2.1.xsd ile birebir
 * aynıdır; sıra bozulursa servis 10003 (şema hatası) döner. İmza bloğu
 * (UBLExtensions) boş bırakılır, servis tarafında doldurulur.
 */
final class UblOlusturucu
{
    private const NS = [
        '' => 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2',
        'cac' => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
        'cbc' => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
        'ext' => 'urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2',
    ];

    private string $para = 'TRY';

    public function olustur(FaturaBilgisi $f): string
    {
        $this->para = $f->paraBirimi;
        $h = new Hesap($f->kalemler);
        $tarih = $f->tarih->format('Y-m-d');

        $x = '<?xml version="1.0" encoding="UTF-8"?>' . "\n<Invoice";
        foreach (self::NS as $onek => $uri) {
            $x .= ' xmlns' . ($onek !== '' ? ':' . $onek : '') . '="' . $uri . '"';
        }
        $x .= '>';

        $x .= '<ext:UBLExtensions><ext:UBLExtension><ext:ExtensionContent/></ext:UBLExtension></ext:UBLExtensions>';
        $x .= self::cbc('UBLVersionID', '2.1')
            . self::cbc('CustomizationID', 'TR1.2')
            . self::cbc('ProfileID', $f->senaryo->value)
            . self::cbc('ID', $f->faturaNo)
            . self::cbc('CopyIndicator', 'false')
            . self::cbc('UUID', $f->ettn)
            . self::cbc('IssueDate', $tarih)
            . self::cbc('IssueTime', $f->tarih->format('H:i:s'))
            . self::cbc('InvoiceTypeCode', $f->tip->value);
        foreach ($f->notlar as $not) {
            $x .= self::cbc('Note', $not);
        }
        $x .= self::cbc('DocumentCurrencyCode', $f->paraBirimi)
            . self::cbc('LineCountNumeric', (string) count($f->kalemler));

        if ($f->siparis !== null) {
            $x .= '<cac:OrderReference>' . self::cbc('ID', $f->siparis->no) . self::cbc('IssueDate', $f->siparis->tarih->format('Y-m-d')) . '</cac:OrderReference>';
        }
        foreach ($f->iadeEdilenler as $r) {
            $x .= '<cac:BillingReference><cac:InvoiceDocumentReference>'
                . self::cbc('ID', $r->no) . self::cbc('IssueDate', $r->tarih->format('Y-m-d'))
                . self::cbc('DocumentTypeCode', 'IADE')
                . '</cac:InvoiceDocumentReference></cac:BillingReference>';
        }
        foreach ($f->irsaliyeler as $r) {
            $x .= '<cac:DespatchDocumentReference>' . self::cbc('ID', $r->no) . self::cbc('IssueDate', $r->tarih->format('Y-m-d')) . '</cac:DespatchDocumentReference>';
        }

        if ($f->xslt !== null) {
            $x .= '<cac:AdditionalDocumentReference>' . self::cbc('ID', $f->ettn) . self::cbc('IssueDate', $tarih)
                . self::cbc('DocumentType', 'XSLT')
                . '<cac:Attachment>'
                . self::cbc('EmbeddedDocumentBinaryObject', base64_encode($f->xslt), [
                    'characterSetCode' => 'UTF-8',
                    'encodingCode' => 'Base64',
                    'filename' => $f->xsltAdi ?? $f->faturaNo . '.xslt',
                    'mimeCode' => 'application/xml',
                ])
                . '</cac:Attachment></cac:AdditionalDocumentReference>';
        }
        if ($f->senaryo->earsivMi()) {
            // Gönderim şekli: görüntü şablonu ve GİB raporu bu referanstan okur
            $x .= '<cac:AdditionalDocumentReference>' . self::cbc('ID', '1') . self::cbc('IssueDate', $tarih)
                . self::cbc('DocumentTypeCode', 'SendingType') . self::cbc('DocumentType', (string) $f->gonderimSekli)
                . '</cac:AdditionalDocumentReference>';
        }

        $x .= $this->imza($f);
        $x .= '<cac:AccountingSupplierParty>' . $this->taraf($f->satici) . '</cac:AccountingSupplierParty>';
        $x .= '<cac:AccountingCustomerParty>' . $this->taraf($f->alici) . '</cac:AccountingCustomerParty>';

        if ($f->odeme !== null) {
            $x .= $this->odeme($f->odeme);
        }
        if ($h->iskontoToplam > 0) {
            $x .= '<cac:AllowanceCharge>' . self::cbc('ChargeIndicator', 'false')
                . self::cbc('AllowanceChargeReason', 'Satır İskonto')
                . $this->tutar('Amount', $h->iskontoToplam)
                . '</cac:AllowanceCharge>';
        }
        if ($f->paraBirimi !== 'TRY') {
            $x .= '<cac:PricingExchangeRate>' . self::cbc('SourceCurrencyCode', $f->paraBirimi)
                . self::cbc('TargetCurrencyCode', 'TRY')
                . self::cbc('CalculationRate', Hesap::sayi((float) $f->dovizKuru, 6))
                . self::cbc('Date', $tarih)
                . '</cac:PricingExchangeRate>';
        }

        $x .= '<cac:TaxTotal>' . $this->tutar('TaxAmount', $h->kdvToplam);
        $sira = 1;
        foreach ($h->kdvGruplari as $g) {
            $x .= $this->vergiAlt($g['matrah'], $g['kdv'], $g['oran'], $sira++, $g['istisnaKodu'], $g['istisnaAciklama']);
        }
        $x .= '</cac:TaxTotal>';

        $x .= '<cac:LegalMonetaryTotal>'
            . $this->tutar('LineExtensionAmount', $h->brutToplam)
            . $this->tutar('TaxExclusiveAmount', $h->matrahToplam)
            . $this->tutar('TaxInclusiveAmount', $h->odenecek)
            . $this->tutar('AllowanceTotalAmount', $h->iskontoToplam)
            . $this->tutar('ChargeTotalAmount', 0)
            . $this->tutar('PayableAmount', $h->odenecek)
            . '</cac:LegalMonetaryTotal>';

        foreach ($f->kalemler as $i => $k) {
            $x .= $this->satir($i + 1, $k, $h->satirlar[$i]);
        }

        return $x . '</Invoice>';
    }

    private function imza(FaturaBilgisi $f): string
    {
        $s = $f->satici;

        return '<cac:Signature>'
            . self::cbc('ID', $s->vknTckn, ['schemeID' => 'VKN_TCKN'])
            . '<cac:SignatoryParty>'
            . '<cac:PartyIdentification>' . self::cbc('ID', $s->vknTckn, ['schemeID' => $s->kimlikTuru()]) . '</cac:PartyIdentification>'
            . $this->adres($s->adres)
            . '</cac:SignatoryParty>'
            . '<cac:DigitalSignatureAttachment><cac:ExternalReference>'
            . self::cbc('URI', '#Signature_' . $f->faturaNo)
            . '</cac:ExternalReference></cac:DigitalSignatureAttachment>'
            . '</cac:Signature>';
    }

    /** PartyType sırası: WebsiteURI, PartyIdentification*, PartyName, PostalAddress, PartyTaxScheme, Contact, Person */
    private function taraf(Taraf $t): string
    {
        $x = '<cac:Party>';
        if ($t->webSitesi !== null) {
            $x .= self::cbc('WebsiteURI', $t->webSitesi);
        }
        $x .= '<cac:PartyIdentification>' . self::cbc('ID', $t->vknTckn, ['schemeID' => $t->kimlikTuru()]) . '</cac:PartyIdentification>';
        foreach ($t->ekKimlikler as $tur => $deger) {
            $x .= '<cac:PartyIdentification>' . self::cbc('ID', $deger, ['schemeID' => $tur]) . '</cac:PartyIdentification>';
        }
        if ($t->unvan !== null) {
            $x .= '<cac:PartyName>' . self::cbc('Name', $t->unvan) . '</cac:PartyName>';
        }
        $x .= $this->adres($t->adres);
        if ($t->vergiDairesi !== null) {
            $x .= '<cac:PartyTaxScheme><cac:TaxScheme>' . self::cbc('Name', $t->vergiDairesi) . '</cac:TaxScheme></cac:PartyTaxScheme>';
        }
        if ($t->telefon !== null || $t->eposta !== null) {
            $x .= '<cac:Contact>' . self::cbc('Telephone', $t->telefon) . self::cbc('ElectronicMail', $t->eposta) . '</cac:Contact>';
        }
        if ($t->tcknMi()) {
            $x .= '<cac:Person>' . self::cbc('FirstName', (string) $t->ad) . self::cbc('FamilyName', (string) $t->soyad) . '</cac:Person>';
        }

        return $x . '</cac:Party>';
    }

    private function adres(Adres $a): string
    {
        return '<cac:PostalAddress>'
            . self::cbc('StreetName', $a->cadde)
            . self::cbc('BuildingNumber', $a->binaNo)
            . self::cbc('CitySubdivisionName', $a->ilce)
            . self::cbc('CityName', $a->il)
            . self::cbc('PostalZone', $a->postaKodu)
            . '<cac:Country>' . self::cbc('Name', $a->ulke) . '</cac:Country>'
            . '</cac:PostalAddress>';
    }

    private function odeme(Odeme $o): string
    {
        $x = '<cac:PaymentMeans>' . self::cbc('PaymentMeansCode', $o->kod)
            . self::cbc('PaymentDueDate', $o->vadeTarihi?->format('Y-m-d'))
            . self::cbc('InstructionNote', $o->aciklama);
        if ($o->iban !== null) {
            $x .= '<cac:PayeeFinancialAccount>'
                . self::cbc('ID', preg_replace('/\s+/', '', $o->iban))
                . self::cbc('CurrencyCode', $this->para);
            if ($o->bankaAdi !== null || $o->subeAdi !== null) {
                $x .= '<cac:FinancialInstitutionBranch>' . self::cbc('Name', $o->subeAdi);
                if ($o->bankaAdi !== null) {
                    $x .= '<cac:FinancialInstitution>' . self::cbc('Name', $o->bankaAdi) . '</cac:FinancialInstitution>';
                }
                $x .= '</cac:FinancialInstitutionBranch>';
            }
            $x .= '</cac:PayeeFinancialAccount>';
        }

        return $x . '</cac:PaymentMeans>';
    }

    /**
     * InvoiceLineType sırası: ID, Note, InvoicedQuantity, LineExtensionAmount, AllowanceCharge*, TaxTotal, Item, Price
     *
     * @param array{brut: float, iskontolar: list<array{iskonto: Iskonto, taban: float, tutar: float}>, matrah: float, kdv: float} $s
     */
    private function satir(int $no, Kalem $k, array $s): string
    {
        $x = '<cac:InvoiceLine>' . self::cbc('ID', (string) $no)
            . self::cbc('Note', $k->aciklama)
            . self::cbc('InvoicedQuantity', Hesap::sayi($k->miktar), ['unitCode' => $k->birim])
            . $this->tutar('LineExtensionAmount', $s['matrah']);

        foreach ($s['iskontolar'] as $i) {
            $x .= '<cac:AllowanceCharge>' . self::cbc('ChargeIndicator', 'false')
                . self::cbc('AllowanceChargeReason', $i['iskonto']->aciklama)
                . self::cbc('MultiplierFactorNumeric', $i['taban'] > 0 ? Hesap::sayi($i['tutar'] / $i['taban'], 6) : '0')
                . $this->tutar('Amount', $i['tutar'])
                . $this->tutar('BaseAmount', $i['taban'])
                . '</cac:AllowanceCharge>';
        }

        $x .= '<cac:TaxTotal>' . $this->tutar('TaxAmount', $s['kdv'])
            . $this->vergiAlt($s['matrah'], $s['kdv'], $k->kdvOrani, 1, $k->istisnaKodu, $k->istisnaAciklama)
            . '</cac:TaxTotal>';

        $x .= '<cac:Item>' . self::cbc('Name', $k->ad);
        if ($k->saticiKodu !== null) {
            $x .= '<cac:SellersItemIdentification>' . self::cbc('ID', $k->saticiKodu) . '</cac:SellersItemIdentification>';
        }
        $x .= '</cac:Item>';

        return $x . '<cac:Price>' . self::cbc('PriceAmount', Hesap::sayi($k->birimFiyat), ['currencyID' => $this->para]) . '</cac:Price>'
            . '</cac:InvoiceLine>';
    }

    /** TaxSubtotalType sırası: TaxableAmount, TaxAmount, CalculationSequenceNumeric, Percent, TaxCategory */
    private function vergiAlt(float $matrah, float $kdv, float $oran, int $sira, ?string $istisnaKodu, ?string $istisnaAciklama): string
    {
        return '<cac:TaxSubtotal>'
            . $this->tutar('TaxableAmount', $matrah)
            . $this->tutar('TaxAmount', $kdv)
            . self::cbc('CalculationSequenceNumeric', (string) $sira)
            . self::cbc('Percent', Hesap::sayi($oran))
            . '<cac:TaxCategory>'
            . ($oran == 0 ? self::cbc('TaxExemptionReasonCode', $istisnaKodu) . self::cbc('TaxExemptionReason', $istisnaAciklama) : '')
            . '<cac:TaxScheme>' . self::cbc('Name', 'KDV') . self::cbc('TaxTypeCode', '0015') . '</cac:TaxScheme>'
            . '</cac:TaxCategory>'
            . '</cac:TaxSubtotal>';
    }

    private function tutar(string $ad, float $deger): string
    {
        return self::cbc($ad, Hesap::tutar($deger), ['currencyID' => $this->para]);
    }

    /** Değer null veya boşsa eleman yazılmaz. */
    private static function cbc(string $ad, ?string $deger, array $oznitelikler = []): string
    {
        if ($deger === null || $deger === '') {
            return '';
        }
        $oz = '';
        foreach ($oznitelikler as $k => $v) {
            $oz .= ' ' . $k . '="' . Xml::kacir((string) $v) . '"';
        }

        return "<cbc:{$ad}{$oz}>" . Xml::kacir($deger) . "</cbc:{$ad}>";
    }
}
