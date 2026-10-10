<?php

declare(strict_types=1);

namespace Turkbelge\EFatura\Testler\Birim\Ubl;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Turkbelge\EFatura\Ubl\Hesap;
use Turkbelge\EFatura\Ubl\Iskonto;
use Turkbelge\EFatura\Ubl\Kalem;

final class HesapTest extends TestCase
{
    public function testArdisikIskontoTurkbelgeOrnegiyleAyni(): void
    {
        // Türkbelge "Satırda Çoklu İskontolu" örneği: 10 → %10 → 9 → %20 → 7.20, KDV %18
        $h = new Hesap([new Kalem('Ürün', 1, 10, 18, iskontolar: [Iskonto::oran(10), Iskonto::oran(20)])]);

        $s = $h->satirlar[0];
        self::assertSame([10.0, 9.0], array_column($s['iskontolar'], 'taban'));
        self::assertSame([1.0, 1.8], array_column($s['iskontolar'], 'tutar'));
        self::assertSame(7.2, $s['matrah']);
        self::assertSame(1.3, $s['kdv']);
        self::assertSame([10.0, 2.8, 7.2, 1.3, 8.5], [$h->brutToplam, $h->iskontoToplam, $h->matrahToplam, $h->kdvToplam, $h->odenecek]);
    }

    public function testKdvOranlarinaGoreGruplanir(): void
    {
        $h = new Hesap([
            new Kalem('A', 1, 100, 20),
            new Kalem('B', 1, 50, 10),
            new Kalem('C', 2, 25, 20),
            new Kalem('D', 1, 30, 0, istisnaKodu: '351'),
        ]);

        self::assertCount(3, $h->kdvGruplari);
        $g20 = $h->kdvGruplari['20|'];
        self::assertSame([150.0, 30.0], [$g20['matrah'], $g20['kdv']]);
        self::assertSame('351', $h->kdvGruplari['0|351']['istisnaKodu']);
        self::assertSame([230.0, 35.0, 265.0], [$h->matrahToplam, $h->kdvToplam, $h->odenecek]);
    }

    public function testToplamlarYuvarlanmisSatirlarinToplamidir(): void
    {
        // 3 × 33.333 = 99.999 → 100.00; KDV 20.00. Satır ve toplam arasında kuruş farkı olmamalı.
        $h = new Hesap([new Kalem('A', 3, 33.333, 20), new Kalem('B', 1, 0.005, 20)]);

        self::assertSame(100.0, $h->satirlar[0]['matrah']);
        self::assertSame(0.01, $h->satirlar[1]['matrah']);
        self::assertSame(round($h->satirlar[0]['kdv'] + $h->satirlar[1]['kdv'], 2), $h->kdvToplam);
    }

    public function testTutarIskontosuVeSiniri(): void
    {
        $h = new Hesap([new Kalem('A', 3, 50, 10, iskontolar: [Iskonto::tutar(15)])]);
        self::assertSame(135.0, $h->satirlar[0]['matrah']);

        $this->expectException(InvalidArgumentException::class);
        new Hesap([new Kalem('A', 1, 10, 20, iskontolar: [Iskonto::tutar(11)])]);
    }

    public function testSayiVeTutarBicimi(): void
    {
        self::assertSame('20', Hesap::sayi(20.0));
        self::assertSame('0.125', Hesap::sayi(0.125));
        self::assertSame('33.333', Hesap::sayi(33.333));
        self::assertSame('0', Hesap::sayi(-0.0));
        self::assertSame('0.00', Hesap::tutar(-0.001), '-0.00 şematronda geçersiz');
        self::assertSame('1.01', Hesap::tutar(1.005));
        self::assertSame('1234.50', Hesap::tutar(1234.5));
    }
}
