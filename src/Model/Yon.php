<?php

declare(strict_types=1);

namespace Turkbelge\EFatura\Model;

/**
 * Fatura yönü: GELEN (alınan) veya GIDEN (gönderilen).
 */
enum Yon: string
{
    case GELEN = 'IN';
    case GIDEN = 'OUT';
}
