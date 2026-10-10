<?php

declare(strict_types=1);

namespace Turkbelge\EFatura\Testler\Birim;

use PHPUnit\Framework\TestCase;
use Turkbelge\EFatura\Tasima\Xml;
use Turkbelge\EFatura\Tasima\XmlOkuyucu;

final class XmlOkuyucuTest extends TestCase
{
    public function testAdAlanindanBagimsizOkur(): void
    {
        $x = XmlOkuyucu::metinden(
            '<a:Kok xmlns:a="urn:a"><USER xmlns=""><ALIAS>pk1</ALIAS></USER><USER xmlns=""><ALIAS>pk2</ALIAS><ALT><ALIAS>ic</ALIAS></ALT></USER></a:Kok>'
        );

        self::assertNotNull($x);
        self::assertSame('Kok', $x->ad());
        self::assertCount(2, $x->cocuklar('USER'));
        self::assertSame('pk2', $x->cocuklar('USER')[1]->deger('ALIAS'), 'deger() yalnız doğrudan alt elemana bakmalı');
        self::assertCount(3, $x->hepsi('ALIAS'));
        self::assertSame(['ALIAS' => 'pk2'], $x->cocuklar('USER')[1]->dizi());
    }

    public function testBosDegerNullDoner(): void
    {
        $x = XmlOkuyucu::metinden('<k><A>  </A><B>1</B></k>');

        self::assertNull($x->deger('A'));
        self::assertNull($x->deger('YOK'));
        self::assertSame('1', $x->deger('B'));
    }

    public function testGecersizXmlNullDoner(): void
    {
        self::assertNull(XmlOkuyucu::metinden('<html>kapanmamış'));
        self::assertNull(XmlOkuyucu::metinden(''));
    }

    public function testElemanYazarkenKacirirVeBosAlaniAtlar(): void
    {
        self::assertSame('<P>a&amp;b&lt;c&gt;&quot;</P>', Xml::eleman('P', 'a&b<c>"'));
        self::assertSame('', Xml::eleman('P', null));
        self::assertSame('<A>1</A><C>x</C>', Xml::elemanlar(['A' => 1, 'B' => null, 'C' => 'x']));
    }
}
