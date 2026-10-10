<?php

declare(strict_types=1);

namespace Turkbelge\EFatura;

use InvalidArgumentException;

/**
 * İstemci ayarları.
 *
 * Kullanıcı adı ve şifre kod içinde tutulmamalı; ortam değişkeni, şifreli
 * yapılandırma veya veritabanından okunup buraya verilmelidir.
 */
final class Ayarlar
{
    /**
     * @param array<string, string> $adresler Servis bazında adres değişikliği: ['KIMLIK' => 'https://...']
     */
    public function __construct(
        public readonly string $kullanici,
        #[\SensitiveParameter] public readonly string $sifre,
        public readonly Ortam $ortam = Ortam::TEST,
        public readonly string $uygulamaAdi = 'turkbelge-efatura-php',
        public readonly ?string $kanalAdi = null,
        public readonly int $zamanAsimi = 120,
        public readonly int $baglantiZamanAsimi = 15,
        public readonly array $adresler = [],
    ) {
        if ($kullanici === '' || $sifre === '') {
            throw new InvalidArgumentException('Kullanıcı adı ve şifre boş olamaz.');
        }
        foreach (array_keys($adresler) as $anahtar) {
            if (ServisTipi::tryFrom($anahtar) === null) {
                throw new InvalidArgumentException("Bilinmeyen servis anahtarı: {$anahtar}");
            }
        }
    }

    public function adres(ServisTipi $servis): string
    {
        $adres = $this->adresler[$servis->value] ?? $this->ortam->adres($servis);

        // WSDL adresi verilmişse servis adresine çevir
        return preg_replace('/\?wsdl$/i', '', $adres);
    }

    public function testMi(): bool
    {
        return $this->ortam === Ortam::TEST;
    }

}
