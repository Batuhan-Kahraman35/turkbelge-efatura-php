<?php

declare(strict_types=1);

namespace Turkbelge\EFatura\Testler\Destek;

use DateTimeImmutable;
use Turkbelge\EFatura\Ubl\Adres;
use Turkbelge\EFatura\Ubl\FaturaBilgisi;
use Turkbelge\EFatura\Ubl\FaturaTipi;
use Turkbelge\EFatura\Ubl\Kalem;
use Turkbelge\EFatura\Ubl\Senaryo;
use Turkbelge\EFatura\Ubl\Taraf;

/**
 * Testlerde kullanılan örnek taraflar ve faturalar (değerler gerçek değildir).
 */
final class OrnekFatura
{
    public const ETTN = '3f2b1c4d-5e6f-4a7b-8c9d-0e1f2a3b4c5d';
    public const XSLT = '<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform"/>';

    public static function satici(): Taraf
    {
        return new Taraf('1234567890', new Adres('Konak', 'İzmir', 'Örnek Cad. No:1', '1', '35000'), 'ÖRNEK SATICI A.Ş.',
            vergiDairesi: 'Konak', telefon: '02320000000', eposta: 'satis@ornek.com');
    }

    public static function aliciVkn(): Taraf
    {
        return new Taraf('9876543210', new Adres('Çankaya', 'Ankara'), 'ALICI & ORTAKLARI LTD.', vergiDairesi: 'Çankaya');
    }

    public static function aliciTckn(): Taraf
    {
        return new Taraf('11111111111', new Adres('Kadıköy', 'İstanbul'), ad: 'Ayşe', soyad: 'Yılmaz');
    }

    public static function tarih(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-10-10 14:30:00');
    }

    /** @param list<Kalem>|null $kalemler */
    public static function temel(?array $kalemler = null, array $ek = []): FaturaBilgisi
    {
        return new FaturaBilgisi(...array_merge([
            'senaryo' => Senaryo::TEMEL,
            'tip' => FaturaTipi::SATIS,
            'satici' => self::satici(),
            'alici' => self::aliciVkn(),
            'kalemler' => $kalemler ?? [new Kalem('Kalem A', 2, 100, 20)],
            'tarih' => self::tarih(),
            'seri' => 'ABC',
            'ettn' => self::ETTN,
            'xslt' => self::XSLT,
        ], $ek));
    }
}
