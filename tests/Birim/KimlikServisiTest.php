<?php

declare(strict_types=1);

namespace Turkbelge\EFatura\Testler\Birim;

use PHPUnit\Framework\TestCase;
use Turkbelge\EFatura\Ayarlar;
use Turkbelge\EFatura\Hata\TurkbelgeHatasi;
use Turkbelge\EFatura\Istemci;
use Turkbelge\EFatura\Testler\Destek\SahteTasiyici;

final class KimlikServisiTest extends TestCase
{
    private SahteTasiyici $tasiyici;
    private Istemci $istemci;

    protected function setUp(): void
    {
        $this->tasiyici = new SahteTasiyici();
        $this->istemci = new Istemci(new Ayarlar('k', 's'), tasiyici: $this->tasiyici);
    }

    private static function kullanici(string $alias, ?string $birim, string $silindi = 'N', string $belge = 'INVOICE'): string
    {
        return '<USER xmlns=""><IDENTIFIER>1234567890</IDENTIFIER><ALIAS>' . $alias . '</ALIAS>'
            . '<TITLE>ORNEK A.S.</TITLE><TYPE>OZEL</TYPE><REGISTER_TIME>2020-01-01T00:00:00</REGISTER_TIME>'
            . ($birim !== null ? '<UNIT>' . $birim . '</UNIT>' : '')
            . '<DELETED>' . $silindi . '</DELETED><DOCUMENT_TYPE>' . $belge . '</DOCUMENT_TYPE></USER>';
    }

    public function testMukellefVeEtiketleriOkunur(): void
    {
        $this->tasiyici->ekleGiris()->ekleYanit('CheckUserResponse',
            self::kullanici('urn:mail:gb@ornek.com', 'GB')
            . self::kullanici('urn:mail:eski@ornek.com', 'PK', silindi: 'Y')
            . self::kullanici('urn:mail:irsaliye@ornek.com', 'PK', belge: 'DESPATCHADVICE')
            . self::kullanici('urn:mail:pk@ornek.com', 'PK')
        );

        $m = $this->istemci->kimlik()->mukellefSorgula('123 456 7890');

        self::assertNotNull($m);
        self::assertSame('1234567890', $m->vkn);
        self::assertSame('ORNEK A.S.', $m->unvan);
        self::assertSame('OZEL', $m->tur);
        self::assertCount(2, $m->etiketler, 'Silinmiş ve başka belge türü etiketleri atlanmalı');
        self::assertSame('urn:mail:pk@ornek.com', $m->pkEtiketi());
        self::assertSame(['urn:mail:gb@ornek.com'], $m->gbEtiketleri());
        self::assertStringContainsString(
            '<USER><IDENTIFIER>1234567890</IDENTIFIER></USER><DOCUMENT_TYPE>INVOICE</DOCUMENT_TYPE>',
            $this->tasiyici->sonGovde(),
        );
    }

    public function testBirimYoksaEtiketAdindanPkBulunur(): void
    {
        $this->tasiyici->ekleGiris()->ekleYanit('CheckUserResponse',
            self::kullanici('urn:mail:defaultgb@ornek.com', null) . self::kullanici('urn:mail:defaultpk@ornek.com', null)
        );

        self::assertSame('urn:mail:defaultpk@ornek.com', $this->istemci->kimlik()->mukellefSorgula('1234567890')?->pkEtiketi());
    }

    public function testKayitYokHatasiMukellefDegilDemektir(): void
    {
        $this->tasiyici->ekleGiris()->ekleHata('CheckUserResponse', 10008, 'Kayıt bulunamadı');

        self::assertNull($this->istemci->kimlik()->mukellefSorgula('1111111111'));
    }

    public function testBosYanitMukellefDegilDemektir(): void
    {
        $this->tasiyici->ekleGiris()->ekleYanit('CheckUserResponse');

        self::assertNull($this->istemci->kimlik()->mukellefSorgula('1111111111'));
    }

    public function testServisHatasiYutulmaz(): void
    {
        // Hata "mükellef değil" sayılırsa mükellefe yanlışlıkla e-Arşiv kesilir
        $this->tasiyici->ekleGiris()->ekleHata('CheckUserResponse', -1, 'Sunucu hatası');

        $this->expectException(TurkbelgeHatasi::class);
        $this->istemci->kimlik()->mukellefSorgula('1234567890');
    }

    public function testGecersizNumaradaServiseGidilmez(): void
    {
        self::assertNull($this->istemci->kimlik()->mukellefSorgula('12345'));
        self::assertSame([], $this->tasiyici->istekler);
    }

    public function testGecersizUrunReddedilir(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->istemci->kimlik()->yetkiliMi('XYZ');
    }
}
