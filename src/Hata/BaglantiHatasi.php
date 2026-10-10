<?php

declare(strict_types=1);

namespace Turkbelge\EFatura\Hata;

use RuntimeException;

/**
 * Ağ, HTTP veya SOAP katmanı hatası (servise ulaşılamadı, yanıt çözülemedi, SOAP Fault).
 */
class BaglantiHatasi extends RuntimeException
{
    public function __construct(string $mesaj, public readonly ?int $httpKodu = null)
    {
        parent::__construct($mesaj);
    }
}
