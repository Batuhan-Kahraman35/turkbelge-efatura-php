<?php

declare(strict_types=1);

namespace Turkbelge\EFatura\Ubl;

use InvalidArgumentException;

/**
 * Fatura satırı. Birim fiyat KDV hariçtir.
 */
final class Kalem
{
    /** @var list<Iskonto> */
    public readonly array $iskontolar;

    /**
     * @param string        $birim         UN/ECE birim kodu: C62 adet, KGM kg, LTR litre, MTR metre, HUR saat, DAY gün, MON ay…
     * @param string|null   $istisnaKodu   %0 KDV'de zorunlu (GİB kod listesi, ör. 301 mal ihracatı, 351 diğer)
     * @param list<Iskonto> $iskontolar
     */
    public function __construct(
        public readonly string $ad,
        public readonly float $miktar,
        public readonly float $birimFiyat,
        public readonly float $kdvOrani,
        public readonly string $birim = 'C62',
        array $iskontolar = [],
        public readonly ?string $istisnaKodu = null,
        public readonly ?string $istisnaAciklama = null,
        public readonly ?string $aciklama = null,
        public readonly ?string $saticiKodu = null,
    ) {
        if (trim($ad) === '') {
            throw new InvalidArgumentException('Kalem adı boş olamaz.');
        }
        if ($miktar <= 0) {
            throw new InvalidArgumentException("Miktar sıfırdan büyük olmalı: {$ad}");
        }
        if ($birimFiyat < 0) {
            throw new InvalidArgumentException("Birim fiyat negatif olamaz: {$ad}");
        }
        if ($kdvOrani < 0 || $kdvOrani > 100) {
            throw new InvalidArgumentException("KDV oranı 0-100 arasında olmalı: {$ad}");
        }
        if ($kdvOrani == 0 && ($istisnaKodu === null || trim($istisnaKodu) === '')) {
            throw new InvalidArgumentException("%0 KDV'li kalemde istisna kodu zorunlu (ör. 351 diğer): {$ad}");
        }
        foreach ($iskontolar as $i) {
            if (!$i instanceof Iskonto) {
                throw new InvalidArgumentException('İskontolar Iskonto nesnesi olmalı.');
            }
        }
        $this->iskontolar = array_values($iskontolar);
    }
}
