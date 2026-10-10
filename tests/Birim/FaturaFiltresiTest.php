<?php

declare(strict_types=1);

namespace Turkbelge\EFatura\Testler\Birim;

use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Turkbelge\EFatura\Model\FaturaFiltresi;
use Turkbelge\EFatura\Model\Yon;

final class FaturaFiltresiTest extends TestCase
{
    public function testSaatSifirlanirVeGunSayilir(): void
    {
        $f = new FaturaFiltresi(Yon::GELEN, new DateTimeImmutable('2024-01-01 15:30'), new \DateTime('2024-01-31 23:59'));

        self::assertSame('2024-01-01 00:00', $f->baslangic->format('Y-m-d H:i'));
        self::assertSame(31, $f->gunSayisi());
    }

    public function testTersAralikReddedilir(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new FaturaFiltresi(Yon::GELEN, new DateTimeImmutable('2024-02-01'), new DateTimeImmutable('2024-01-01'));
    }

    public function testLimitSiniriDenetlenir(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new FaturaFiltresi(Yon::GELEN, new DateTimeImmutable('2024-01-01'), new DateTimeImmutable('2024-01-01'), limit: 25001);
    }

    public function testAraliklaDigerOlcutlerKorunur(): void
    {
        $f = new FaturaFiltresi(Yon::GIDEN, new DateTimeImmutable('2024-01-01'), new DateTimeImmutable('2024-12-31'), '123', false, true, true, 50);
        $y = $f->aralikla(new DateTimeImmutable('2024-03-01'), new DateTimeImmutable('2024-03-02'));

        self::assertSame([Yon::GIDEN, '123', false, true, true, 50, 2], [$y->yon, $y->karsiVkn, $y->okunanlarDahil, $y->olusturmaTarihi, $y->taslaklar, $y->limit, $y->gunSayisi()]);
    }
}
