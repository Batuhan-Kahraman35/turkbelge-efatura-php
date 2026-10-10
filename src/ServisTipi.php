<?php

declare(strict_types=1);

namespace Turkbelge\EFatura;

/**
 * Türkbelge web servisleri ve SOAP ad alanları.
 */
enum ServisTipi: string
{
    case KIMLIK = 'KIMLIK';
    case EFATURA = 'EFATURA';
    case EARSIV = 'EARSIV';

    public function adAlani(): string
    {
        return match ($this) {
            self::KIMLIK, self::EFATURA => 'http://schemas.i2i.com/ei/wsdl',
            self::EARSIV => 'http://schemas.i2i.com/ei/wsdl/archive',
        };
    }
}
