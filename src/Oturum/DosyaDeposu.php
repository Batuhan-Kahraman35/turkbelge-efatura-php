<?php

declare(strict_types=1);

namespace Turkbelge\EFatura\Oturum;

use RuntimeException;

/**
 * Oturumu dosyada saklar; web istekleri arasında yeniden giriş yapılmasını önler.
 *
 * Dizin web kökünün DIŞINDA olmalıdır: oturum numarası, süresi dolana kadar
 * hesap adına işlem yapmaya yeter.
 */
final class DosyaDeposu implements OturumDeposu
{
    public function __construct(private readonly string $dizin)
    {
        if (!is_dir($dizin) && !@mkdir($dizin, 0700, true) && !is_dir($dizin)) {
            throw new RuntimeException("Oturum dizini oluşturulamadı: {$dizin}");
        }
    }

    public function al(string $anahtar): ?string
    {
        $yol = $this->yol($anahtar);
        $icerik = is_file($yol) ? @file_get_contents($yol) : false;
        if ($icerik === false) {
            return null;
        }
        $veri = json_decode($icerik, true);
        if (!is_array($veri) || ($veri['bitis'] ?? 0) <= time()) {
            return null;
        }

        return (string) $veri['deger'];
    }

    public function koy(string $anahtar, string $deger, int $saniye): void
    {
        $yol = $this->yol($anahtar);
        file_put_contents($yol, json_encode(['deger' => $deger, 'bitis' => time() + $saniye]), LOCK_EX);
        @chmod($yol, 0600);
    }

    public function sil(string $anahtar): void
    {
        @unlink($this->yol($anahtar));
    }

    private function yol(string $anahtar): string
    {
        return rtrim($this->dizin, '/\\') . DIRECTORY_SEPARATOR . 'turkbelge-' . hash('sha256', $anahtar) . '.json';
    }
}
