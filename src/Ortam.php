<?php

declare(strict_types=1);

namespace Turkbelge\EFatura;

/**
 * Türkbelge servis ortamları.
 *
 * Adresler yayınlanan WSDL'lerden doğrulanmıştır. Farklı bir adres gerekirse
 * Ayarlar::$adresler ile servis bazında değiştirilebilir.
 */
enum Ortam: string
{
    case TEST = 'TEST';
    case CANLI = 'CANLI';

    public function adres(ServisTipi $servis): string
    {
        return match ($this) {
            self::TEST => match ($servis) {
                ServisTipi::KIMLIK => 'https://efaturawstest.turkbelge.com.tr/AuthenticationWS',
                ServisTipi::EFATURA => 'https://efaturawstest.turkbelge.com.tr/EFaturaOIB',
                ServisTipi::EARSIV => 'https://earsivwstest.turkbelge.com.tr/EIArchiveWS/EFaturaArchive',
            },
            self::CANLI => match ($servis) {
                ServisTipi::KIMLIK => 'https://authenticationws.turkbelge.com.tr/AuthenticationWS',
                ServisTipi::EFATURA => 'https://efaturaws.turkbelge.com.tr/EFaturaOIB',
                ServisTipi::EARSIV => 'https://earsivws.turkbelge.com.tr/EIArchiveWS/EFaturaArchive',
            },
        };
    }
}
