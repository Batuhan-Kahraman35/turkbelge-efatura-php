<?php

declare(strict_types=1);

namespace Turkbelge\EFatura\Testler\Entegrasyon;

use Turkbelge\EFatura\Ayarlar;
use Turkbelge\EFatura\Hata\TurkbelgeHatasi;
use Turkbelge\EFatura\Istemci;
use Turkbelge\EFatura\Ortam;

final class KimlikEntegrasyonTest extends EntegrasyonTesti
{
    public function testGirisYapilir(): void
    {
        self::assertNotSame('', $this->istemci()->oturum()->oturumNo());
    }

    public function testKayitsizNumaraMukellefDegildir(): void
    {
        self::assertNull($this->istemci()->kimlik()->mukellefSorgula('1111111111'));
    }

    public function testMukellefSorgulanir(): void
    {
        // Sorgulanacak VKN depoya gömülmez: TURKBELGE_TEST_ALICI_VKN ile verilir
        $vkn = (string) getenv('TURKBELGE_TEST_ALICI_VKN');
        if ($vkn === '') {
            self::markTestSkipped('TURKBELGE_TEST_ALICI_VKN tanımlı değil.');
        }

        $m = $this->istemci()->kimlik()->mukellefSorgula($vkn);

        self::assertNotNull($m);
        self::assertNotEmpty($m->unvan);
        self::assertNotNull($m->pkEtiketi());
    }

    public function testYanlisSifreHataKoduDoner(): void
    {
        $yanlis = new Istemci(new Ayarlar('olmayan-kullanici-' . bin2hex(random_bytes(3)), 'yanlis', Ortam::TEST));

        $this->expectException(TurkbelgeHatasi::class);
        $this->expectExceptionCode(TurkbelgeHatasi::OTURUM_GECERSIZ);
        $yanlis->oturum()->oturumNo();
    }
}
