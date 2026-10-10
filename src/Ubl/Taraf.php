<?php

declare(strict_types=1);

namespace Turkbelge\EFatura\Ubl;

use InvalidArgumentException;

/**
 * Satıcı veya alıcı.
 *
 * VKN (10 hane) için ünvan, TCKN (11 hane) için ad ve soyad zorunludur.
 * Gerçek kişiye kesilen faturada GİB, cac:Person (ad/soyad) bekler.
 * Şahıs şirketinde (TCKN + ticari ünvan) ünvan ile ad/soyad birlikte verilebilir.
 */
final class Taraf
{
    public readonly string $vknTckn;

    public function __construct(
        string $vknTckn,
        public readonly Adres $adres,
        public readonly ?string $unvan = null,
        public readonly ?string $ad = null,
        public readonly ?string $soyad = null,
        public readonly ?string $vergiDairesi = null,
        public readonly ?string $telefon = null,
        public readonly ?string $eposta = null,
        public readonly ?string $webSitesi = null,
        /** @var array<string, string> Ek kimlikler, ör. ['MERSISNO' => '...', 'TICARETSICILNO' => '...'] */
        public readonly array $ekKimlikler = [],
    ) {
        $this->vknTckn = preg_replace('/\D/', '', $vknTckn);
        $uzunluk = strlen($this->vknTckn);

        if ($uzunluk === 10 && trim((string) $unvan) === '') {
            throw new InvalidArgumentException('VKN\'li tarafta ünvan zorunludur.');
        }
        if ($uzunluk === 11 && (trim((string) $ad) === '' || trim((string) $soyad) === '')) {
            throw new InvalidArgumentException('TCKN\'li tarafta ad ve soyad zorunludur.');
        }
        if ($uzunluk !== 10 && $uzunluk !== 11) {
            throw new InvalidArgumentException("VKN 10, TCKN 11 haneli olmalı: {$vknTckn}");
        }
    }

    public function tcknMi(): bool
    {
        return strlen($this->vknTckn) === 11;
    }

    public function kimlikTuru(): string
    {
        return $this->tcknMi() ? 'TCKN' : 'VKN';
    }

    public function gorunenAd(): string
    {
        return $this->unvan ?? trim($this->ad . ' ' . $this->soyad);
    }
}
