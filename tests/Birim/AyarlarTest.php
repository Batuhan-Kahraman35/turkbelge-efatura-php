<?php

declare(strict_types=1);

namespace Turkbelge\EFatura\Testler\Birim;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Turkbelge\EFatura\Ayarlar;
use Turkbelge\EFatura\Ortam;
use Turkbelge\EFatura\ServisTipi;

final class AyarlarTest extends TestCase
{
    public function testOrtamAdresleriKullanilir(): void
    {
        $test = new Ayarlar('k', 's', Ortam::TEST);
        $canli = new Ayarlar('k', 's', Ortam::CANLI);

        self::assertStringContainsString('efaturawstest', $test->adres(ServisTipi::EFATURA));
        self::assertStringContainsString('/EIArchiveWS/EFaturaArchive', $canli->adres(ServisTipi::EARSIV));
        self::assertStringStartsWith('https://authenticationws.', $canli->adres(ServisTipi::KIMLIK));
    }

    public function testAdresDegistirilebilirVeWsdlEkiAtilir(): void
    {
        $a = new Ayarlar('k', 's', adresler: ['EFATURA' => 'https://ornek.test/EFaturaOIB?wsdl']);

        self::assertSame('https://ornek.test/EFaturaOIB', $a->adres(ServisTipi::EFATURA));
        self::assertStringContainsString('turkbelge', $a->adres(ServisTipi::KIMLIK));
    }

    public function testBilinmeyenServisAnahtariReddedilir(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Ayarlar('k', 's', adresler: ['EIRSALIYE' => 'https://ornek.test']);
    }

    public function testBosKimlikBilgisiReddedilir(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Ayarlar('', 's');
    }
}
