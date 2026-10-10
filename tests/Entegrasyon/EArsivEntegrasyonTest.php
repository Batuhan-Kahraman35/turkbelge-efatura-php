<?php

declare(strict_types=1);

namespace Turkbelge\EFatura\Testler\Entegrasyon;

use DateTimeImmutable;
use Turkbelge\EFatura\Model\EArsivDurumu;
use Turkbelge\EFatura\Model\EArsivFatura;
use Turkbelge\EFatura\Model\EArsivFiltresi;

/**
 * Test hesabındaki mevcut e-Arşiv faturaları üzerinde okuma işlemleri.
 * Hiçbir kayıt oluşturmaz. Taslak olmayan fatura yoksa içerik testleri atlanır.
 */
final class EArsivEntegrasyonTest extends EntegrasyonTesti
{
    /** @var list<EArsivFatura>|null */
    private static ?array $faturalar = null;

    /** @return list<EArsivFatura> */
    private function faturalar(): array
    {
        if (self::$faturalar === null) {
            self::$faturalar = [];
            $bugun = new DateTimeImmutable('today');
            for ($i = 0; $i < 5 && count(self::$faturalar) === 0; $i++) {
                $filtre = new EArsivFiltresi($bugun->modify('-' . ($i + 1) . ' year +1 day'), $bugun->modify("-{$i} year"), limit: 20);
                self::$faturalar = $this->istemci()->earsiv()->liste($filtre);
            }
        }
        if (self::$faturalar === []) {
            self::markTestSkipped('Test hesabında son 5 yılda e-Arşiv fatura yok.');
        }

        return self::$faturalar;
    }

    private function raporlanmisFatura(): EArsivFatura
    {
        foreach ($this->faturalar() as $f) {
            if (!$f->taslakMi()) {
                return $f;
            }
        }
        self::markTestSkipped('Test hesabında taslak olmayan e-Arşiv fatura yok (taslaklarda içerik alınamıyor).');
    }

    public function testListeBasligiDoludur(): void
    {
        $f = $this->faturalar()[0];

        self::assertMatchesRegularExpression('/^[A-Z0-9]{3}\d{13}$/', (string) $f->faturaNo);
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', (string) $f->tarih);
        self::assertNotSame('', $f->ettn);
    }

    public function testOlmayanFaturaninDurumuBulunamadidir(): void
    {
        $d = $this->istemci()->earsiv()->durum('00000000-0000-4000-8000-000000000000');

        self::assertSame(EArsivDurumu::BULUNAMADI, $d->durumKodu);
        self::assertFalse($d->bulunduMu());
    }

    public function testOlmayanFaturaNullDoner(): void
    {
        self::assertNull($this->istemci()->earsiv()->getir('00000000-0000-4000-8000-000000000000'));
    }

    public function testGetirUblDoner(): void
    {
        $f = $this->istemci()->earsiv()->getir($this->raporlanmisFatura()->ettn);

        self::assertStringContainsString('urn:oasis:names:specification:ubl:schema:xsd:Invoice-2', (string) $f?->icerik);
    }

    public function testGorselPdfDoner(): void
    {
        self::assertStringStartsWith('%PDF', $this->istemci()->earsiv()->gorsel($this->raporlanmisFatura()->ettn, 'PDF'));
    }
}
