<?php

declare(strict_types=1);

namespace Turkbelge\EFatura\Hata;

use RuntimeException;

/**
 * Servisin ERROR_TYPE ile döndürdüğü iş hatası.
 *
 * Sık görülen kodlar:
 *  10004 geçersiz oturum · 10008 kayıt bulunamadı · 10003 şema/şematron hatası
 *  10009/10017 belge daha önce gönderilmiş · 10010 5MB / 100 belge sınırı · 10018 yetkisiz erişim
 */
class TurkbelgeHatasi extends RuntimeException
{
    public const OTURUM_GECERSIZ = 10004;
    public const KAYIT_BULUNAMADI = 10008;

    public function __construct(
        string $mesaj,
        public readonly int $hataKodu,
        public readonly ?string $ayrinti = null,
        public readonly ?string $islemNo = null,
    ) {
        parent::__construct("Türkbelge hatası [{$hataKodu}]: {$mesaj}", $hataKodu);
    }

    public function oturumHatasiMi(): bool
    {
        return $this->hataKodu === self::OTURUM_GECERSIZ;
    }

    public function kayitYokMu(): bool
    {
        return $this->hataKodu === self::KAYIT_BULUNAMADI;
    }
}
