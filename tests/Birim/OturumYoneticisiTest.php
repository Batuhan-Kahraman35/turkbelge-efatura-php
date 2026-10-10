<?php

declare(strict_types=1);

namespace Turkbelge\EFatura\Testler\Birim;

use PHPUnit\Framework\TestCase;
use Turkbelge\EFatura\Ayarlar;
use Turkbelge\EFatura\Hata\TurkbelgeHatasi;
use Turkbelge\EFatura\Oturum\BellekDeposu;
use Turkbelge\EFatura\Oturum\OturumYoneticisi;
use Turkbelge\EFatura\ServisTipi;
use Turkbelge\EFatura\Tasima\SoapIstemci;
use Turkbelge\EFatura\Testler\Destek\SahteTasiyici;

final class OturumYoneticisiTest extends TestCase
{
    private SahteTasiyici $tasiyici;
    private BellekDeposu $depo;

    protected function setUp(): void
    {
        $this->tasiyici = new SahteTasiyici();
        $this->depo = new BellekDeposu();
    }

    private function yonetici(): OturumYoneticisi
    {
        $ayarlar = new Ayarlar('kul', 'sif&<re');

        return new OturumYoneticisi($ayarlar, new SoapIstemci($ayarlar, $this->tasiyici), $this->depo);
    }

    public function testGirisBirKezYapilirVeOturumYenidenKullanilir(): void
    {
        $this->tasiyici->ekleGiris('o1')->ekleYanit('A')->ekleYanit('B');
        $y = $this->yonetici();

        $y->cagir(ServisTipi::EFATURA, 'ARequest');
        $y->cagir(ServisTipi::EFATURA, 'BRequest');

        self::assertSame(['LoginRequest', 'ARequest', 'BRequest'], $this->tasiyici->operasyonlar());
        self::assertStringContainsString('<SESSION_ID>o1</SESSION_ID>', $this->tasiyici->sonGovde());
    }

    public function testGiristeSifreKacirilirVeOturumNoEksiBirdir(): void
    {
        $this->tasiyici->ekleGiris();
        $this->yonetici()->oturumNo();

        $govde = $this->tasiyici->sonGovde();
        self::assertStringContainsString('<SESSION_ID>-1</SESSION_ID>', $govde);
        self::assertStringContainsString('<USER_NAME>kul</USER_NAME><PASSWORD>sif&amp;&lt;re</PASSWORD>', $govde);
    }

    public function testOturumDusunceBirKezYenidenGirilir(): void
    {
        $this->tasiyici
            ->ekleGiris('eski')
            ->ekleHata('AResponse', 10004)
            ->ekleGiris('yeni')
            ->ekleYanit('AResponse');

        $this->yonetici()->cagir(ServisTipi::EFATURA, 'ARequest');

        self::assertSame(['LoginRequest', 'ARequest', 'LoginRequest', 'ARequest'], $this->tasiyici->operasyonlar());
        self::assertStringContainsString('<SESSION_ID>yeni</SESSION_ID>', $this->tasiyici->sonGovde());
    }

    public function testIkinciOturumHatasindaTekrarDenenmez(): void
    {
        $this->tasiyici
            ->ekleGiris()
            ->ekleHata('AResponse', 10004)
            ->ekleGiris()
            ->ekleHata('AResponse', 10004);

        $this->expectException(TurkbelgeHatasi::class);
        try {
            $this->yonetici()->cagir(ServisTipi::EFATURA, 'ARequest');
        } finally {
            self::assertCount(4, $this->tasiyici->istekler);
        }
    }

    public function testYanlisSifredeTekIstekAtilir(): void
    {
        // Türkbelge hatalı şifreye de 10004 döner
        $this->tasiyici->ekleHata('LoginResponse', 10004, 'Kullanıcı adı veya şifre hatalı');

        try {
            $this->yonetici()->cagir(ServisTipi::EFATURA, 'ARequest');
            self::fail('İstisna bekleniyordu');
        } catch (TurkbelgeHatasi $e) {
            self::assertSame(10004, $e->hataKodu);
        }
        self::assertCount(1, $this->tasiyici->istekler);
    }

    public function testDigerHatalarYenidenGirisTetiklemez(): void
    {
        $this->tasiyici->ekleGiris()->ekleHata('AResponse', 10003);

        $this->expectExceptionCode(10003);
        try {
            $this->yonetici()->cagir(ServisTipi::EFATURA, 'ARequest');
        } finally {
            self::assertCount(2, $this->tasiyici->istekler);
        }
    }

    public function testCikisOturumuSilerVeHatayiYutar(): void
    {
        $this->tasiyici->ekleGiris()->ekleHata('LogoutResponse', 10004)->ekleGiris('yeni');
        $y = $this->yonetici();
        $y->oturumNo();

        $y->cikis();

        self::assertSame('yeni', $y->oturumNo(), 'Çıkıştan sonra yeni giriş yapılmalı');
        self::assertSame(['LoginRequest', 'LogoutRequest', 'LoginRequest'], $this->tasiyici->operasyonlar());
    }
}
