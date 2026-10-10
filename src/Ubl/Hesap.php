<?php

declare(strict_types=1);

namespace Turkbelge\EFatura\Ubl;

/**
 * Faturanın satır ve belge tutarları.
 *
 * Satır tutarları 2 haneye yuvarlanır; belge toplamları yuvarlanmış satırların
 * toplamıdır (görüntüde satırlar ile toplamlar arasında kuruş farkı oluşmaz).
 *
 * Türkbelge portalının kuralı izlenir: belge LineExtensionAmount iskonto öncesi
 * brüt toplam, AllowanceTotalAmount satır iskontolarının toplamı, TaxExclusiveAmount
 * net (matrah) toplamıdır.
 */
final class Hesap
{
    /** @var list<array{brut: float, iskontolar: list<array{iskonto: Iskonto, taban: float, tutar: float}>, matrah: float, kdv: float}> */
    public readonly array $satirlar;

    /** @var array<string, array{oran: float, istisnaKodu: ?string, istisnaAciklama: ?string, matrah: float, kdv: float}> */
    public readonly array $kdvGruplari;

    public readonly float $brutToplam;
    public readonly float $iskontoToplam;
    public readonly float $matrahToplam;
    public readonly float $kdvToplam;
    public readonly float $odenecek;

    /** @param list<Kalem> $kalemler */
    public function __construct(array $kalemler)
    {
        $satirlar = [];
        $gruplar = [];
        foreach ($kalemler as $k) {
            $brut = round($k->miktar * $k->birimFiyat, 2);
            $kalan = $brut;
            $iskontolar = [];
            foreach ($k->iskontolar as $i) {
                $tutar = $i->hesapla($kalan);
                $iskontolar[] = ['iskonto' => $i, 'taban' => $kalan, 'tutar' => $tutar];
                $kalan = round($kalan - $tutar, 2);
            }
            $kdv = round($kalan * $k->kdvOrani / 100, 2);
            $satirlar[] = ['brut' => $brut, 'iskontolar' => $iskontolar, 'matrah' => $kalan, 'kdv' => $kdv];

            $anahtar = self::sayi($k->kdvOrani) . '|' . $k->istisnaKodu;
            $gruplar[$anahtar] ??= ['oran' => $k->kdvOrani, 'istisnaKodu' => $k->istisnaKodu, 'istisnaAciklama' => $k->istisnaAciklama, 'matrah' => 0.0, 'kdv' => 0.0];
            $gruplar[$anahtar]['matrah'] = round($gruplar[$anahtar]['matrah'] + $kalan, 2);
            $gruplar[$anahtar]['kdv'] = round($gruplar[$anahtar]['kdv'] + $kdv, 2);
        }

        $this->satirlar = $satirlar;
        $this->kdvGruplari = $gruplar;
        $this->brutToplam = self::topla(array_column($satirlar, 'brut'));
        $this->matrahToplam = self::topla(array_column($satirlar, 'matrah'));
        $this->kdvToplam = self::topla(array_column($satirlar, 'kdv'));
        $this->iskontoToplam = round($this->brutToplam - $this->matrahToplam, 2);
        $this->odenecek = round($this->matrahToplam + $this->kdvToplam, 2);
    }

    /** @param list<float> $degerler */
    private static function topla(array $degerler): float
    {
        return round(array_sum($degerler), 2);
    }

    /** Ondalık sayıyı sondaki sıfırlar olmadan yazar: 20.0 → "20", 0.125 → "0.125" */
    public static function sayi(float $deger, int $hane = 8): string
    {
        $metin = rtrim(rtrim(number_format($deger, $hane, '.', ''), '0'), '.');

        return $metin === '-0' ? '0' : $metin;
    }

    /** Tutar: 2 hane, -0.00 yazılmaz (şematron geçersiz sayar). */
    public static function tutar(float $deger): string
    {
        $deger = round($deger, 2);

        return number_format(abs($deger) < 0.005 ? 0.0 : $deger, 2, '.', '');
    }
}
