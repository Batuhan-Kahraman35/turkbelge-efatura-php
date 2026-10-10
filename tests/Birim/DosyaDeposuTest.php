<?php

declare(strict_types=1);

namespace Turkbelge\EFatura\Testler\Birim;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Turkbelge\EFatura\Oturum\BellekDeposu;
use Turkbelge\EFatura\Oturum\DosyaDeposu;
use Turkbelge\EFatura\Oturum\OturumDeposu;

final class DosyaDeposuTest extends TestCase
{
    private string $dizin;

    protected function setUp(): void
    {
        $this->dizin = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tb-test-' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dizin . DIRECTORY_SEPARATOR . '*') ?: [] as $f) {
            unlink($f);
        }
        if (is_dir($this->dizin)) {
            rmdir($this->dizin);
        }
    }

    /** @return iterable<string, array{0: callable(string): OturumDeposu}> */
    public static function depolar(): iterable
    {
        yield 'dosya' => [static fn (string $d) => new DosyaDeposu($d)];
        yield 'bellek' => [static fn (string $d) => new BellekDeposu()];
    }

    #[DataProvider('depolar')]
    public function testKoyAlSil(callable $olustur): void
    {
        $depo = $olustur($this->dizin);

        self::assertNull($depo->al('a'));
        $depo->koy('a', 'deger', 60);
        self::assertSame('deger', $depo->al('a'));
        self::assertNull($depo->al('b'));
        $depo->sil('a');
        self::assertNull($depo->al('a'));
    }

    #[DataProvider('depolar')]
    public function testSuresiDolanKayitDonmez(callable $olustur): void
    {
        $depo = $olustur($this->dizin);
        $depo->koy('a', 'deger', -1);

        self::assertNull($depo->al('a'));
    }

    public function testDosyaAdindaAnahtarGorunmez(): void
    {
        (new DosyaDeposu($this->dizin))->koy('TEST|kullanici-adi|https://x', 'o', 60);

        $dosyalar = glob($this->dizin . DIRECTORY_SEPARATOR . '*') ?: [];
        self::assertCount(1, $dosyalar);
        self::assertStringNotContainsString('kullanici', basename($dosyalar[0]));
    }
}
