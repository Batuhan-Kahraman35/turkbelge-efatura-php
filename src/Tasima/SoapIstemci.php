<?php

declare(strict_types=1);

namespace Turkbelge\EFatura\Tasima;

use Turkbelge\EFatura\Ayarlar;
use Turkbelge\EFatura\Hata\BaglantiHatasi;
use Turkbelge\EFatura\Hata\TurkbelgeHatasi;
use Turkbelge\EFatura\ServisTipi;

/**
 * SOAP zarfını kurar, gönderir ve yanıttaki hataları istisnaya çevirir.
 *
 * Hata sırası: ağ hatası → çözülemeyen yanıt → SOAP Fault (RequestFault) → ERROR_TYPE.
 */
final class SoapIstemci
{
    public function __construct(
        private readonly Ayarlar $ayarlar,
        private readonly Tasiyici $tasiyici,
    ) {
    }

    /**
     * @param string $kokEleman İstek kök elemanı, ör. "LoginRequest"
     * @param string $icXml     REQUEST_HEADER'dan sonra gelen, XSD sırasına uygun alanlar
     * @param string $oturumNo  Login için "-1"
     *
     * @return XmlOkuyucu Yanıt kök elemanı (ör. LoginResponse)
     */
    public function cagir(ServisTipi $servis, string $kokEleman, string $icXml, string $oturumNo): XmlOkuyucu
    {
        $govde = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"><soapenv:Body>'
            . '<ns:' . $kokEleman . ' xmlns:ns="' . $servis->adAlani() . '">'
            . $this->baslik($oturumNo)
            . $icXml
            . '</ns:' . $kokEleman . '></soapenv:Body></soapenv:Envelope>';

        [$httpKodu, $yanit] = $this->tasiyici->gonder($this->ayarlar->adres($servis), $govde);

        $okuyucu = XmlOkuyucu::metinden($yanit);
        if ($okuyucu === null) {
            throw new BaglantiHatasi("Geçersiz SOAP yanıtı (HTTP {$httpKodu})", $httpKodu);
        }

        $fault = $okuyucu->bul('Fault');
        if ($fault !== null) {
            // RequestFault detayı ERROR_TYPE ile aynı yapıdadır
            $detay = $fault->bul('RequestFault');
            if ($detay !== null && $detay->deger('ERROR_CODE') !== null) {
                throw self::hatadan($detay);
            }
            throw new BaglantiHatasi('SOAP Fault: ' . ($fault->deger('faultstring') ?? 'bilinmeyen hata'), $httpKodu);
        }

        $govdeEl = $okuyucu->bul('Body');
        $sonuc = $govdeEl?->cocuklar()[0] ?? null;
        if ($sonuc === null) {
            throw new BaglantiHatasi("Boş SOAP yanıtı (HTTP {$httpKodu})", $httpKodu);
        }

        $hata = $sonuc->cocuk('ERROR_TYPE');
        if ($hata !== null) {
            throw self::hatadan($hata);
        }

        return $sonuc;
    }

    private function baslik(string $oturumNo): string
    {
        // REQUEST_HEADERType sırası: SESSION_ID … APPLICATION_NAME, HOSTNAME, CHANNEL_NAME, SIMULATION_FLAG, COMPRESSED
        return '<REQUEST_HEADER>'
            . Xml::elemanlar([
                'SESSION_ID' => $oturumNo,
                'APPLICATION_NAME' => $this->ayarlar->uygulamaAdi,
                'CHANNEL_NAME' => $this->ayarlar->kanalAdi,
                'COMPRESSED' => 'N',
            ])
            . '</REQUEST_HEADER>';
    }

    private static function hatadan(XmlOkuyucu $hata): TurkbelgeHatasi
    {
        return new TurkbelgeHatasi(
            $hata->deger('ERROR_SHORT_DES') ?? 'Bilinmeyen hata',
            (int) ($hata->deger('ERROR_CODE') ?? -1),
            $hata->deger('ERROR_LONG_DES'),
            $hata->deger('INTL_TXN_ID'),
        );
    }
}
