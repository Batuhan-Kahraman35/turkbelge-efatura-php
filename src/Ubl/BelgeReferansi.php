<?php

declare(strict_types=1);

namespace Turkbelge\EFatura\Ubl;

use DateTimeImmutable;
use DateTimeInterface;

/**
 * Başka bir belgeye referans: iade edilen fatura, sipariş, irsaliye.
 */
final class BelgeReferansi
{
    public readonly DateTimeImmutable $tarih;

    public function __construct(
        public readonly string $no,
        DateTimeInterface $tarih,
    ) {
        $this->tarih = DateTimeImmutable::createFromInterface($tarih);
    }
}
