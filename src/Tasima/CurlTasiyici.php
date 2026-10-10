<?php

declare(strict_types=1);

namespace Turkbelge\EFatura\Tasima;

use Turkbelge\EFatura\Ayarlar;
use Turkbelge\EFatura\Hata\BaglantiHatasi;

/**
 * cURL ile SOAP 1.1 isteği gönderir.
 *
 * - SSL doğrulaması açıktır; kapatılmamalıdır.
 * - User-Agent gönderilir: bazı sunucu güvenlik duvarları (ör. ModSecurity)
 *   User-Agent içermeyen istekleri 403 ile reddeder.
 */
final class CurlTasiyici implements Tasiyici
{
    public const SURUM = '0.1.0';

    public function __construct(private readonly Ayarlar $ayarlar)
    {
    }

    public function gonder(string $adres, string $govde): array
    {
        $ch = curl_init($adres);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $govde,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: text/xml; charset=utf-8',
                'SOAPAction: ""',
            ],
            CURLOPT_USERAGENT => 'turkbelge-efatura-php/' . self::SURUM,
            CURLOPT_TIMEOUT => $this->ayarlar->zamanAsimi,
            CURLOPT_CONNECTTIMEOUT => $this->ayarlar->baglantiZamanAsimi,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_ENCODING => '',
        ]);

        $yanit = curl_exec($ch);
        $httpKodu = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $hata = curl_error($ch);
        curl_close($ch);

        if ($yanit === false) {
            throw new BaglantiHatasi("Bağlantı hatası: {$hata}", $httpKodu ?: null);
        }

        return [$httpKodu, (string) $yanit];
    }
}
