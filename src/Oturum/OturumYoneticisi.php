<?php

declare(strict_types=1);

namespace Turkbelge\EFatura\Oturum;

use Turkbelge\EFatura\Ayarlar;
use Turkbelge\EFatura\Hata\BaglantiHatasi;
use Turkbelge\EFatura\Hata\TurkbelgeHatasi;
use Turkbelge\EFatura\ServisTipi;
use Turkbelge\EFatura\Tasima\SoapIstemci;
use Turkbelge\EFatura\Tasima\Xml;
use Turkbelge\EFatura\Tasima\XmlOkuyucu;

/**
 * Oturum açar, önbellekte tutar ve süresi dolmuş oturumda bir kez yeniden dener.
 *
 * Servis oturumu 8 saat geçerli tutar; güvenlik payı için 7 saat saklanır.
 */
final class OturumYoneticisi
{
    public const OTURUM_OMRU = 25200;

    public function __construct(
        private readonly Ayarlar $ayarlar,
        private readonly SoapIstemci $soap,
        private readonly OturumDeposu $depo,
    ) {
    }

    /** Oturumlu bir servis çağrısı; geçersiz oturumda (10004) yeniden giriş yapıp bir kez tekrarlar. */
    public function cagir(ServisTipi $servis, string $kokEleman, string $icXml = ''): XmlOkuyucu
    {
        // Giriş try dışında: hatalı şifre de 10004 döner, yeniden denenmemeli
        $oturumNo = $this->oturumNo();
        try {
            return $this->soap->cagir($servis, $kokEleman, $icXml, $oturumNo);
        } catch (TurkbelgeHatasi $e) {
            if (!$e->oturumHatasiMi()) {
                throw $e;
            }

            return $this->soap->cagir($servis, $kokEleman, $icXml, $this->oturumNo(yenile: true));
        }
    }

    public function oturumNo(bool $yenile = false): string
    {
        $anahtar = $this->anahtar();
        if (!$yenile) {
            $kayitli = $this->depo->al($anahtar);
            if ($kayitli !== null) {
                return $kayitli;
            }
        }

        $yanit = $this->soap->cagir(
            ServisTipi::KIMLIK,
            'LoginRequest',
            Xml::elemanlar(['USER_NAME' => $this->ayarlar->kullanici, 'PASSWORD' => $this->ayarlar->sifre]),
            '-1',
        );

        $oturumNo = $yanit->deger('SESSION_ID');
        if ($oturumNo === null || $oturumNo === '-1') {
            throw new BaglantiHatasi('Giriş yanıtında oturum numarası yok.');
        }

        $this->depo->koy($anahtar, $oturumNo, self::OTURUM_OMRU);

        return $oturumNo;
    }

    /** Oturumu kapatır ve önbellekten siler. Hata olursa yutulur. */
    public function cikis(): void
    {
        $anahtar = $this->anahtar();
        $oturumNo = $this->depo->al($anahtar);
        $this->depo->sil($anahtar);
        if ($oturumNo === null) {
            return;
        }
        try {
            $this->soap->cagir(ServisTipi::KIMLIK, 'LogoutRequest', '', $oturumNo);
        } catch (TurkbelgeHatasi|BaglantiHatasi) {
            // Oturum zaten düşmüş olabilir
        }
    }

    private function anahtar(): string
    {
        return $this->ayarlar->ortam->value . '|' . $this->ayarlar->kullanici . '|' . $this->ayarlar->adres(ServisTipi::KIMLIK);
    }
}
