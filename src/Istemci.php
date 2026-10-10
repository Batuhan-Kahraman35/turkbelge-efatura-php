<?php

declare(strict_types=1);

namespace Turkbelge\EFatura;

use Turkbelge\EFatura\Oturum\BellekDeposu;
use Turkbelge\EFatura\Oturum\OturumDeposu;
use Turkbelge\EFatura\Oturum\OturumYoneticisi;
use Turkbelge\EFatura\Servis\EArsivServisi;
use Turkbelge\EFatura\Servis\EFaturaServisi;
use Turkbelge\EFatura\Servis\KimlikServisi;
use Turkbelge\EFatura\Tasima\CurlTasiyici;
use Turkbelge\EFatura\Tasima\SoapIstemci;
use Turkbelge\EFatura\Tasima\Tasiyici;

/**
 * Kütüphanenin giriş noktası.
 *
 * ```php
 * $istemci = new Istemci(new Ayarlar('kullanici', 'sifre', Ortam::TEST));
 * $mukellef = $istemci->kimlik()->mukellefSorgula('1234567890');
 * ```
 */
final class Istemci
{
    private readonly OturumYoneticisi $oturum;
    private ?KimlikServisi $kimlik = null;
    private ?EFaturaServisi $efatura = null;
    private ?EArsivServisi $earsiv = null;

    public function __construct(
        private readonly Ayarlar $ayarlar,
        ?OturumDeposu $depo = null,
        ?Tasiyici $tasiyici = null,
    ) {
        $soap = new SoapIstemci($ayarlar, $tasiyici ?? new CurlTasiyici($ayarlar));
        $this->oturum = new OturumYoneticisi($ayarlar, $soap, $depo ?? new BellekDeposu());
    }

    public function kimlik(): KimlikServisi
    {
        return $this->kimlik ??= new KimlikServisi($this->oturum);
    }

    public function efatura(): EFaturaServisi
    {
        return $this->efatura ??= new EFaturaServisi($this->oturum);
    }

    public function earsiv(): EArsivServisi
    {
        return $this->earsiv ??= new EArsivServisi($this->oturum, $this->ayarlar->testMi());
    }

    public function ayarlar(): Ayarlar
    {
        return $this->ayarlar;
    }

    public function oturum(): OturumYoneticisi
    {
        return $this->oturum;
    }
}
