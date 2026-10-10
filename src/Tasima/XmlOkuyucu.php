<?php

declare(strict_types=1);

namespace Turkbelge\EFatura\Tasima;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * SOAP yanıtlarını ad alanından bağımsız okur.
 *
 * Türkbelge yanıtlarında alt elemanlar xmlns="" ile gelir; bu yüzden
 * okumalar ad alanına değil elemanın yerel adına (localName) göre yapılır.
 */
final class XmlOkuyucu
{
    public function __construct(private readonly DOMElement $kok)
    {
    }

    public static function metinden(string $xml): ?self
    {
        if (trim($xml) === '') {
            return null;
        }
        $dom = new DOMDocument();
        $onceki = libxml_use_internal_errors(true);
        $tamam = $dom->loadXML($xml, LIBXML_NONET | LIBXML_PARSEHUGE);
        libxml_clear_errors();
        libxml_use_internal_errors($onceki);

        return $tamam && $dom->documentElement ? new self($dom->documentElement) : null;
    }

    public function eleman(): DOMElement
    {
        return $this->kok;
    }

    /** Alt ağaçtaki ilk eşleşen eleman (derinlik öncelikli). */
    public function bul(string $ad): ?self
    {
        return $this->hepsi($ad)[0] ?? null;
    }

    /**
     * Alt ağaçtaki tüm eşleşen elemanlar ('*' ad alansız elemanları da kapsar).
     *
     * @return list<self>
     */
    public function hepsi(string $ad): array
    {
        $sonuc = [];
        foreach ($this->kok->getElementsByTagNameNS('*', $ad) as $el) {
            $sonuc[] = new self($el);
        }

        return $sonuc;
    }

    /**
     * Yalnız doğrudan alt elemanlar (iç içe aynı adlı elemanları karıştırmamak için).
     *
     * @return list<self>
     */
    public function cocuklar(?string $ad = null): array
    {
        $sonuc = [];
        foreach ($this->kok->childNodes as $dugum) {
            if ($dugum instanceof DOMElement && ($ad === null || $dugum->localName === $ad)) {
                $sonuc[] = new self($dugum);
            }
        }

        return $sonuc;
    }

    public function cocuk(string $ad): ?self
    {
        return $this->cocuklar($ad)[0] ?? null;
    }

    /** Doğrudan alt elemanın metni; yoksa null. */
    public function deger(string $ad): ?string
    {
        $c = $this->cocuk($ad);
        if ($c === null) {
            return null;
        }
        $metin = trim($c->metin());

        return $metin === '' ? null : $metin;
    }

    public function metin(): string
    {
        return $this->kok->textContent;
    }

    public function oznitelik(string $ad): ?string
    {
        return $this->kok->hasAttribute($ad) ? $this->kok->getAttribute($ad) : null;
    }

    public function ad(): string
    {
        return $this->kok->localName ?? $this->kok->nodeName;
    }

    /** Elemanı düz diziye çevirir (yalnız basit alt elemanlar). */
    public function dizi(): array
    {
        $sonuc = [];
        foreach ($this->kok->childNodes as $dugum) {
            if ($dugum instanceof DOMElement && !self::karmasikMi($dugum)) {
                $sonuc[$dugum->localName] = trim($dugum->textContent);
            }
        }

        return $sonuc;
    }

    private static function karmasikMi(DOMNode $dugum): bool
    {
        foreach ($dugum->childNodes as $c) {
            if ($c instanceof DOMElement) {
                return true;
            }
        }

        return false;
    }
}
