<?php

declare(strict_types=1);

namespace Turkbelge\EFatura\Testler\Entegrasyon;

use DateTimeImmutable;
use Turkbelge\EFatura\Model\Fatura;
use Turkbelge\EFatura\Model\FaturaFiltresi;
use Turkbelge\EFatura\Model\Yon;

/**
 * Test hesabındaki mevcut giden faturalar üzerinde okuma işlemlerini dener.
 * Hesapta fatura yoksa testler atlanır. Bu testler hiçbir kayıt oluşturmaz.
 */
final class EFaturaEntegrasyonTest extends EntegrasyonTesti
{
    private static ?Fatura $ornek = null;

    private function ornekFatura(): Fatura
    {
        if (self::$ornek === null) {
            $bugun = new DateTimeImmutable('today');
            // Son 3 yılı yıl yıl tara (servis sınırları için kısa aralıklar)
            for ($i = 0; $i < 3 && self::$ornek === null; $i++) {
                $filtre = new FaturaFiltresi(Yon::GIDEN, $bugun->modify('-' . ($i + 1) . ' year +1 day'), $bugun->modify("-{$i} year"), limit: 1);
                self::$ornek = $this->istemci()->efatura()->liste($filtre)[0] ?? null;
            }
        }
        if (self::$ornek === null) {
            self::markTestSkipped('Test hesabında son 3 yılda giden e-Fatura yok.');
        }

        return self::$ornek;
    }

    public function testListeBasligiDoludur(): void
    {
        $f = $this->ornekFatura();

        self::assertMatchesRegularExpression('/^[A-Z0-9]{3}\d{13}$/', (string) $f->faturaNo);
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', (string) $f->tarih);
        self::assertSame(Yon::GIDEN, $f->yon);
    }

    public function testGetirUblDoner(): void
    {
        $f = $this->istemci()->efatura()->getir($this->ornekFatura()->ettn, Yon::GIDEN);

        self::assertNotNull($f);
        self::assertStringContainsString('urn:oasis:names:specification:ubl:schema:xsd:Invoice-2', (string) $f->icerik);
    }

    public function testGorselPdfDoner(): void
    {
        $pdf = $this->istemci()->efatura()->gorsel($this->ornekFatura()->ettn, Yon::GIDEN, 'PDF');

        self::assertStringStartsWith('%PDF', $pdf);
    }

    public function testTekilVeTopluDurumTutarlidir(): void
    {
        $ettn = $this->ornekFatura()->ettn;
        $tekil = $this->istemci()->efatura()->durum($ettn);
        $toplu = $this->istemci()->efatura()->durumlar([$ettn]);

        self::assertSame($ettn, $tekil->ettn);
        self::assertNotNull($tekil->durumKodu);
        self::assertCount(1, $toplu);
        self::assertSame($tekil->durumKodu, $toplu[0]->durumKodu);
    }

    public function testOlmayanFaturaNullDoner(): void
    {
        self::assertNull($this->istemci()->efatura()->getir('00000000-0000-4000-8000-000000000000', Yon::GIDEN));
    }
}
