<?php

declare(strict_types=1);

namespace Turkbelge\EFatura\Testler\Birim\Ubl;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Turkbelge\EFatura\Testler\Destek\OrnekFatura;
use Turkbelge\EFatura\Ubl\Adres;
use Turkbelge\EFatura\Ubl\FaturaTipi;
use Turkbelge\EFatura\Ubl\Iskonto;
use Turkbelge\EFatura\Ubl\Kalem;
use Turkbelge\EFatura\Ubl\Senaryo;
use Turkbelge\EFatura\Ubl\Taraf;
use Turkbelge\EFatura\Yardimci\Ettn;
use Turkbelge\EFatura\Yardimci\FaturaNo;

/**
 * Hatalı fatura bilgisinin servise gitmeden yakalandığını doğrular.
 */
final class DogrulamaTest extends TestCase
{
    /** @return iterable<string, array{0: callable, 1: string}> */
    public static function hatalar(): iterable
    {
        yield 'XSLT yok' => [fn () => OrnekFatura::temel(null, ['xslt' => null]), 'XSLT'];
        yield 'kalemsiz' => [fn () => OrnekFatura::temel([]), 'en az bir kalem'];
        yield 'numara/seri yok' => [fn () => OrnekFatura::temel(null, ['seri' => null]), 'numarası veya seri'];
        yield 'geçersiz numara' => [fn () => OrnekFatura::temel(null, ['faturaNo' => 'AB2026000000001']), '9 hane'];
        yield 'geçersiz ETTN' => [fn () => OrnekFatura::temel(null, ['ettn' => 'abc']), 'ETTN'];
        yield 'dövizde kur yok' => [fn () => OrnekFatura::temel(null, ['paraBirimi' => 'USD']), 'kuru zorunlu'];
        yield 'geçersiz para' => [fn () => OrnekFatura::temel(null, ['paraBirimi' => 'TL']), 'ISO 4217'];
        yield 'iadede referans yok' => [fn () => OrnekFatura::temel(null, ['tip' => FaturaTipi::IADE]), 'iade edilen'];
        yield 'e-Arşivde gönderim yok' => [fn () => OrnekFatura::temel(null, ['senaryo' => Senaryo::EARSIV]), 'gönderim şekli'];
        yield 'e-Faturada gönderim' => [fn () => OrnekFatura::temel(null, ['gonderimSekli' => 'KAGIT']), 'yalnız e-Arşiv'];
        yield 'sıfır KDV istisnasız' => [fn () => new Kalem('A', 1, 1, 0), 'istisna kodu'];
        yield 'sıfır miktar' => [fn () => new Kalem('A', 0, 1, 20), 'Miktar'];
        yield 'negatif fiyat' => [fn () => new Kalem('A', 1, -1, 20), 'negatif'];
        yield 'VKN ünvansız' => [fn () => new Taraf('1234567890', new Adres('a', 'b')), 'ünvan'];
        yield 'TCKN adsız' => [fn () => new Taraf('11111111111', new Adres('a', 'b'), 'Ünvan'), 'ad ve soyad'];
        yield 'kimlik uzunluğu' => [fn () => new Taraf('123', new Adres('a', 'b'), 'X'), '10, TCKN 11'];
        yield 'adres ili boş' => [fn () => new Adres('Konak', ' '), 'ilçe ve il'];
        yield 'iskonto oranı' => [fn () => Iskonto::oran(150), '0 ile 100'];
        yield 'satıcı vergi dairesi' => [fn () => OrnekFatura::temel(null, ['satici' => new Taraf('1234567890', new Adres('a', 'b'), 'X')]), 'vergi dairesi'];
    }

    #[DataProvider('hatalar')]
    public function testHataliBilgiReddedilir(callable $olustur, string $mesaj): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($mesaj);
        $olustur();
    }

    public function testEttnUretilirVeGecerlidir(): void
    {
        $a = Ettn::uret();
        self::assertTrue(Ettn::gecerliMi($a));
        self::assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $a, 'v4 ve küçük harf');
        self::assertNotSame($a, Ettn::uret());
        self::assertSame(36, strlen(OrnekFatura::temel(null, ['ettn' => null])->ettn));
    }

    public function testFaturaNumarasi(): void
    {
        self::assertSame('ABC2026000000000', FaturaNo::yerTutucu('abc', 2026));
        self::assertSame('X012026000000042', FaturaNo::olustur('X01', 2026, 42));
        self::assertSame(42, FaturaNo::sira('X012026000000042'));
        self::assertNull(FaturaNo::sira('gecersiz'));
        self::assertFalse(FaturaNo::gecerliMi('abc2026000000001'), 'Küçük harf geçersiz');
    }
}
