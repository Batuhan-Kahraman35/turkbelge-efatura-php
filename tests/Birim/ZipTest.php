<?php

declare(strict_types=1);

namespace Turkbelge\EFatura\Testler\Birim;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Turkbelge\EFatura\Tasima\Zip;
use Turkbelge\EFatura\Testler\Destek\ZipUretici;

final class ZipTest extends TestCase
{
    /** @return iterable<string, array{0: bool}> */
    public static function yontemler(): iterable
    {
        yield 'deflate' => [true];
        yield 'stored' => [false];
    }

    #[DataProvider('yontemler')]
    public function testArsivAcilir(bool $sikistir): void
    {
        $dosyalar = ['fatura.xml' => '<Invoice>' . str_repeat('ş', 500) . '</Invoice>', 'klasor/fatura.pdf' => "%PDF-1.4\x00\xff"];

        self::assertSame($dosyalar, Zip::ac(ZipUretici::uret($dosyalar, $sikistir)));
    }

    public function testBase64ZipCozulurVeUzantiylaSecilir(): void
    {
        $zip = ZipUretici::uret(['a.html' => '<html/>', 'a.pdf' => '%PDF']);

        self::assertSame('%PDF', Zip::icerik(base64_encode($zip), 'pdf'));
        self::assertSame('<html/>', Zip::icerik(base64_encode($zip), 'HTML'));
        self::assertSame('<html/>', Zip::icerik($zip), 'Uzantı verilmezse ilk dosya');
    }

    public function testZipOlmayanIcerikOlduguGibiDoner(): void
    {
        self::assertSame('<Invoice/>', Zip::icerik(base64_encode('<Invoice/>')));
        self::assertSame('<Invoice/>', Zip::icerik('<Invoice/>'), 'Düz XML base64 sanılmamalı');
        self::assertSame('', Zip::icerik('  '));
    }

    public function testZipArchiveIleUretilenArsivAcilir(): void
    {
        if (!class_exists(\ZipArchive::class)) {
            self::markTestSkipped('ext-zip yok');
        }
        $yol = tempnam(sys_get_temp_dir(), 'zip');
        $z = new \ZipArchive();
        $z->open($yol, \ZipArchive::OVERWRITE);
        $z->addFromString('x.xml', str_repeat('<a>1</a>', 1000));
        $z->close();
        $veri = file_get_contents($yol);
        unlink($yol);

        self::assertSame(str_repeat('<a>1</a>', 1000), Zip::icerik($veri, 'xml'));
    }

    public function testBozukSaglamaToplamiYakalanir(): void
    {
        $zip = ZipUretici::uret(['a.txt' => 'merhaba'], false);
        $bozuk = str_replace('merhaba', 'merhabA', $zip);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('sağlama');
        Zip::ac($bozuk);
    }

    public function testDizinSonuOlmayanVeriReddedilir(): void
    {
        $this->expectException(RuntimeException::class);
        Zip::ac("PK\x03\x04bozuk");
    }
}
