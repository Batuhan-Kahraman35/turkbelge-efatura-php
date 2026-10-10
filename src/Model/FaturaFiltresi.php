<?php

declare(strict_types=1);

namespace Turkbelge\EFatura\Model;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;

/**
 * e-Fatura listeleme ölçütleri (GetInvoice INVOICE_SEARCH_KEY).
 *
 * Servis sınırları: yalnız başlık istendiğinde istek başına en çok 25.000,
 * içerikle birlikte en çok 100 fatura. LIMIT verilmezse servis 10 döndürür.
 */
final class FaturaFiltresi
{
    public const EN_COK_BASLIK = 25000;
    public const EN_COK_ICERIK = 100;

    public readonly DateTimeImmutable $baslangic;
    public readonly DateTimeImmutable $bitis;

    /**
     * @param bool        $okunanlarDahil false verilirse yalnız okunmamış faturalar döner ve
     *                                    dönenler servis tarafında okundu işaretlenir
     * @param string|null $karsiVkn       Gelende gönderen, gidende alıcı VKN/TCKN
     * @param bool        $olusturmaTarihi Tarih aralığı fatura tarihi yerine sisteme düşme tarihine uygulanır
     */
    public function __construct(
        public readonly Yon $yon,
        DateTimeInterface $baslangic,
        DateTimeInterface $bitis,
        public readonly ?string $karsiVkn = null,
        public readonly bool $okunanlarDahil = true,
        public readonly bool $olusturmaTarihi = false,
        public readonly bool $taslaklar = false,
        public readonly int $limit = 1000,
    ) {
        $this->baslangic = DateTimeImmutable::createFromInterface($baslangic)->setTime(0, 0);
        $this->bitis = DateTimeImmutable::createFromInterface($bitis)->setTime(0, 0);

        if ($this->bitis < $this->baslangic) {
            throw new InvalidArgumentException('Bitiş tarihi başlangıçtan önce olamaz.');
        }
        if ($limit < 1 || $limit > self::EN_COK_BASLIK) {
            throw new InvalidArgumentException('Limit 1 ile ' . self::EN_COK_BASLIK . ' arasında olmalı.');
        }
    }

    public function aralikla(DateTimeInterface $baslangic, DateTimeInterface $bitis): self
    {
        return new self($this->yon, $baslangic, $bitis, $this->karsiVkn, $this->okunanlarDahil, $this->olusturmaTarihi, $this->taslaklar, $this->limit);
    }

    public function gunSayisi(): int
    {
        return (int) $this->baslangic->diff($this->bitis)->days + 1;
    }
}
