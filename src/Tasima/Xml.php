<?php

declare(strict_types=1);

namespace Turkbelge\EFatura\Tasima;

/**
 * İstek XML'i üretirken kullanılan küçük yardımcılar.
 *
 * Türkbelge şemaları eleman sırasına duyarlıdır; istekler bu yüzden
 * SoapClient yerine XSD sırasıyla elle kurulur.
 */
final class Xml
{
    public static function kacir(string $deger): string
    {
        return htmlspecialchars($deger, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    /** Değer null ise eleman hiç yazılmaz (minOccurs=0 alanlar için). */
    public static function eleman(string $ad, string|int|float|null $deger): string
    {
        if ($deger === null || $deger === '') {
            return '';
        }

        return "<{$ad}>" . self::kacir((string) $deger) . "</{$ad}>";
    }

    /** @param array<string, string|int|float|null> $alanlar Sıra korunur */
    public static function elemanlar(array $alanlar): string
    {
        $xml = '';
        foreach ($alanlar as $ad => $deger) {
            $xml .= self::eleman($ad, $deger);
        }

        return $xml;
    }
}
