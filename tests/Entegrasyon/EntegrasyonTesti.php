<?php

declare(strict_types=1);

namespace Turkbelge\EFatura\Testler\Entegrasyon;

use PHPUnit\Framework\TestCase;
use Turkbelge\EFatura\Ayarlar;
use Turkbelge\EFatura\Istemci;
use Turkbelge\EFatura\Ortam;

/**
 * Türkbelge TEST ortamına bağlanan testlerin tabanı.
 *
 * Kimlik bilgileri yalnız ortam değişkeninden okunur, depoya yazılmaz:
 *   TURKBELGE_TEST_KULLANICI, TURKBELGE_TEST_SIFRE
 * Test hesabı için Türkbelge destek ekibine başvurulur.
 */
abstract class EntegrasyonTesti extends TestCase
{
    protected static ?Istemci $istemci = null;

    protected function setUp(): void
    {
        $kullanici = (string) getenv('TURKBELGE_TEST_KULLANICI');
        $sifre = (string) getenv('TURKBELGE_TEST_SIFRE');
        if ($kullanici === '' || $sifre === '') {
            self::markTestSkipped('TURKBELGE_TEST_KULLANICI / TURKBELGE_TEST_SIFRE tanımlı değil.');
        }

        // Sınıf boyunca tek oturum: her testte yeniden giriş yapılmaz
        static::$istemci ??= new Istemci(new Ayarlar($kullanici, $sifre, Ortam::TEST));
    }

    protected function istemci(): Istemci
    {
        return static::$istemci;
    }
}
