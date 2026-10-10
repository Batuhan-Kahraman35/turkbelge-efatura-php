<?php

declare(strict_types=1);

namespace Turkbelge\EFatura\Model;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;

/**
 * e-Arşiv listeleme ölçütleri (GetEArchiveInvoiceList).
 *
 * e-Arşiv faturaları yalnız gidendir; yön yoktur. Liste taslakları da içerir.
 */
final class EArsivFiltresi
{
    public const EN_COK = 10000;

    public readonly DateTimeImmutable $baslangic;
    public readonly DateTimeImmutable $bitis;

    /**
     * @param string|null $seri Yalnız bu 3 haneli seriyle başlayan faturalar (PREFIX)
     */
    public function __construct(
        DateTimeInterface $baslangic,
        DateTimeInterface $bitis,
        public readonly ?string $seri = null,
        public readonly int $limit = 1000,
    ) {
        $this->baslangic = DateTimeImmutable::createFromInterface($baslangic)->setTime(0, 0);
        $this->bitis = DateTimeImmutable::createFromInterface($bitis)->setTime(23, 59, 59);

        if ($this->bitis < $this->baslangic) {
            throw new InvalidArgumentException('Bitiş tarihi başlangıçtan önce olamaz.');
        }
        if ($limit < 1 || $limit > self::EN_COK) {
            throw new InvalidArgumentException('Limit 1 ile ' . self::EN_COK . ' arasında olmalı.');
        }
        if ($seri !== null && !preg_match('/^[A-Z0-9]{3}$/', $seri)) {
            throw new InvalidArgumentException('Seri 3 haneli büyük harf/rakam olmalı.');
        }
    }

    public function aralikla(DateTimeInterface $baslangic, DateTimeInterface $bitis): self
    {
        return new self($baslangic, $bitis, $this->seri, $this->limit);
    }

    public function gunSayisi(): int
    {
        return (int) $this->baslangic->diff($this->bitis->setTime(0, 0))->days + 1;
    }
}
