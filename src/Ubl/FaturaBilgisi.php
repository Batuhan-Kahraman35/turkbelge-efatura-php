<?php

declare(strict_types=1);

namespace Turkbelge\EFatura\Ubl;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use Turkbelge\EFatura\Yardimci\Ettn;
use Turkbelge\EFatura\Yardimci\FaturaNo;

/**
 * UBL-TR faturası üretmek için gereken bilgiler.
 *
 * Fatura numarası verilmezse seriden yer tutucu üretilir (ABC2026000000000);
 * gerçek numarayı gönderim sırasında servis atar.
 */
final class FaturaBilgisi
{
    public readonly string $ettn;
    public readonly string $faturaNo;
    public readonly DateTimeImmutable $tarih;
    /** @var list<Kalem> */
    public readonly array $kalemler;

    /**
     * @param list<Kalem>          $kalemler
     * @param list<string>         $notlar
     * @param list<BelgeReferansi> $iadeEdilenler  İADE tipinde zorunlu
     * @param list<BelgeReferansi> $irsaliyeler
     * @param float|null           $dovizKuru      TRY dışı para biriminde zorunlu (1 birim = ? TL)
     * @param string|null          $gonderimSekli  Yalnız e-Arşiv: ELEKTRONIK veya KAGIT
     * @param string|null          $xslt           Görüntü şablonu (XSLT) içeriği; faturaya gömülür, zorunludur
     */
    public function __construct(
        public readonly Senaryo $senaryo,
        public readonly FaturaTipi $tip,
        public readonly Taraf $satici,
        public readonly Taraf $alici,
        array $kalemler,
        ?DateTimeInterface $tarih = null,
        ?string $faturaNo = null,
        public readonly ?string $seri = null,
        ?string $ettn = null,
        public readonly string $paraBirimi = 'TRY',
        public readonly ?float $dovizKuru = null,
        public readonly array $notlar = [],
        public readonly array $iadeEdilenler = [],
        public readonly ?BelgeReferansi $siparis = null,
        public readonly array $irsaliyeler = [],
        public readonly ?Odeme $odeme = null,
        public readonly ?string $gonderimSekli = null,
        public readonly ?string $xslt = null,
        public readonly ?string $xsltAdi = null,
    ) {
        if ($kalemler === []) {
            throw new InvalidArgumentException('Faturada en az bir kalem olmalı.');
        }
        $this->kalemler = array_values($kalemler);
        $this->tarih = DateTimeImmutable::createFromInterface($tarih ?? new DateTimeImmutable());
        $this->ettn = strtolower($ettn ?? Ettn::uret());

        if (!Ettn::gecerliMi($this->ettn)) {
            throw new InvalidArgumentException("Geçersiz ETTN: {$this->ettn}");
        }

        if ($faturaNo !== null) {
            if (!FaturaNo::gecerliMi($faturaNo)) {
                throw new InvalidArgumentException("Fatura numarası 3 harf/rakam + yıl + 9 hane olmalı: {$faturaNo}");
            }
            $this->faturaNo = $faturaNo;
        } elseif ($seri !== null) {
            $this->faturaNo = FaturaNo::yerTutucu($seri, (int) $this->tarih->format('Y'));
        } else {
            throw new InvalidArgumentException('Fatura numarası veya seri verilmeli.');
        }

        if (!preg_match('/^[A-Z]{3}$/', $paraBirimi)) {
            throw new InvalidArgumentException("Para birimi ISO 4217 kodu olmalı: {$paraBirimi}");
        }
        if ($paraBirimi !== 'TRY' && ($dovizKuru === null || $dovizKuru <= 0)) {
            throw new InvalidArgumentException("{$paraBirimi} faturada döviz kuru zorunlu.");
        }
        if ($tip === FaturaTipi::IADE && $iadeEdilenler === []) {
            throw new InvalidArgumentException('İade faturasında iade edilen fatura (iadeEdilenler) zorunlu.');
        }
        if ($senaryo->earsivMi()) {
            if (!in_array($gonderimSekli, ['ELEKTRONIK', 'KAGIT'], true)) {
                throw new InvalidArgumentException('e-Arşiv faturada gönderim şekli ELEKTRONIK veya KAGIT olmalı.');
            }
        } elseif ($gonderimSekli !== null) {
            throw new InvalidArgumentException('Gönderim şekli yalnız e-Arşiv faturada kullanılır.');
        }
        if ($satici->tcknMi() === false && $satici->vergiDairesi === null) {
            throw new InvalidArgumentException('Satıcının vergi dairesi zorunlu.');
        }
        // GİB: her belgede görüntü şablonu (XSLT) bulunmalı. e-Arşiv servisi şablonsuz faturayı
        // "Belge içerisinde şablon bulunamamıştır" (10013) ile reddeder.
        if ($xslt === null || trim($xslt) === '') {
            throw new InvalidArgumentException('Faturaya görüntü şablonu (XSLT) gömülmesi zorunlu: xslt parametresi.');
        }
    }
}
