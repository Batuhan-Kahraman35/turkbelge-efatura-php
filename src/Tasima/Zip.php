<?php

declare(strict_types=1);

namespace Turkbelge\EFatura\Tasima;

use RuntimeException;

/**
 * Servisin döndürdüğü base64 / ZIP içerikleri bellekte açar.
 *
 * ZipArchive geçici dosya ister; paylaşımlı sunucularda (open_basedir) geçici
 * dizine yazılamadığında süreç sessizce düşebilir. Bu yüzden ZIP, merkezi dizin
 * okunarak saf PHP ile açılır (yalnız "stored" ve "deflate" yöntemleri).
 */
final class Zip
{
    private const YEREL_BASLIK = "PK\x03\x04";
    private const MERKEZI_BASLIK = "PK\x01\x02";
    private const DIZIN_SONU = "PK\x05\x06";

    /**
     * Gelen CONTENT değerini çözer: gerekirse base64'ten çevirir, ZIP ise içinden
     * dosyayı çıkarır, değilse olduğu gibi döner.
     *
     * @param string|null $uzanti Birden çok dosya varsa tercih edilecek uzantı (pdf, html, xml)
     */
    public static function icerik(string $veri, ?string $uzanti = null): string
    {
        // İkili ZIP'e trim uygulanmaz: dizin sonu kaydı \0 baytlarıyla biter
        if (!str_starts_with($veri, self::YEREL_BASLIK)) {
            $veri = trim($veri);
        }
        if ($veri === '') {
            return '';
        }

        if (!str_starts_with($veri, self::YEREL_BASLIK) && !str_starts_with($veri, '<')) {
            $cozulen = base64_decode($veri, true);
            if ($cozulen !== false) {
                $veri = $cozulen;
            }
        }

        if (!str_starts_with($veri, self::YEREL_BASLIK)) {
            return $veri;
        }

        $dosyalar = self::ac($veri);
        if ($dosyalar === []) {
            throw new RuntimeException('ZIP içinde dosya yok.');
        }
        if ($uzanti !== null) {
            foreach ($dosyalar as $ad => $icerik) {
                if (strcasecmp(pathinfo($ad, PATHINFO_EXTENSION), $uzanti) === 0) {
                    return $icerik;
                }
            }
        }

        return reset($dosyalar);
    }

    /**
     * ZIP arşivindeki tüm dosyalar.
     *
     * @return array<string, string> dosya adı => içerik
     */
    public static function ac(string $zip): array
    {
        $son = strrpos($zip, self::DIZIN_SONU);
        if ($son === false || strlen($zip) < $son + 22) {
            throw new RuntimeException('Geçersiz ZIP: dizin sonu kaydı bulunamadı.');
        }
        $eocd = unpack('vkayit/Vboyut/Vofset', substr($zip, $son + 10, 10));

        $dosyalar = [];
        $konum = $eocd['ofset'];
        for ($i = 0; $i < $eocd['kayit']; $i++) {
            if (substr($zip, $konum, 4) !== self::MERKEZI_BASLIK) {
                throw new RuntimeException('Geçersiz ZIP: merkezi dizin bozuk.');
            }
            $m = unpack('vyontem/x4/Vcrc/Vsikisik/Vacik/vadUz/vekUz/vyorumUz/x8/Vyerel', substr($zip, $konum + 10, 36));
            $ad = substr($zip, $konum + 46, $m['adUz']);
            $konum += 46 + $m['adUz'] + $m['ekUz'] + $m['yorumUz'];

            if (str_ends_with($ad, '/')) {
                continue; // klasör
            }

            $yerel = unpack('vadUz/vekUz', substr($zip, $m['yerel'] + 26, 4));
            $ham = substr($zip, $m['yerel'] + 30 + $yerel['adUz'] + $yerel['ekUz'], $m['sikisik']);

            $icerik = match ($m['yontem']) {
                0 => $ham,
                8 => @gzinflate($ham),
                default => throw new RuntimeException("Desteklenmeyen ZIP sıkıştırma yöntemi: {$m['yontem']}"),
            };
            if ($icerik === false || strlen($icerik) !== $m['acik']) {
                throw new RuntimeException("ZIP dosyası açılamadı: {$ad}");
            }
            if ((crc32($icerik) & 0xFFFFFFFF) !== $m['crc']) {
                throw new RuntimeException("ZIP sağlama toplamı hatalı: {$ad}");
            }
            $dosyalar[$ad] = $icerik;
        }

        return $dosyalar;
    }
}
