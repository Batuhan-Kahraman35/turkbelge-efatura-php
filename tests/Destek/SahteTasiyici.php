<?php

declare(strict_types=1);

namespace Turkbelge\EFatura\Testler\Destek;

use RuntimeException;
use Turkbelge\EFatura\Tasima\Tasiyici;

/**
 * Ağa çıkmadan sıradaki hazır yanıtı döndürür ve gönderilen istekleri kaydeder.
 */
final class SahteTasiyici implements Tasiyici
{
    /** @var list<array{adres: string, govde: string}> */
    public array $istekler = [];

    /** @var list<array{0: int, 1: string}> */
    private array $yanitlar = [];

    public function ekle(string $govde, int $httpKodu = 200): self
    {
        $this->yanitlar[] = [$httpKodu, $govde];

        return $this;
    }

    /** Body içine verilen elemanı SOAP zarfına sarar. Alt elemanlar xmlns="" ile gelir (servisteki gibi). */
    public function ekleYanit(string $kokEleman, string $icXml = '', string $adAlani = 'http://schemas.i2i.com/ei/wsdl'): self
    {
        return $this->ekle(
            '<?xml version="1.0" encoding="UTF-8"?>'
            . '<S:Envelope xmlns:S="http://schemas.xmlsoap.org/soap/envelope/"><S:Body>'
            . '<ns2:' . $kokEleman . ' xmlns:ns2="' . $adAlani . '">' . $icXml . '</ns2:' . $kokEleman . '>'
            . '</S:Body></S:Envelope>'
        );
    }

    public function ekleGiris(string $oturumNo = 'oturum-1'): self
    {
        return $this->ekleYanit('LoginResponse', '<SESSION_ID xmlns="">' . $oturumNo . '</SESSION_ID>');
    }

    public function ekleHata(string $kokEleman, int $kod, string $mesaj = 'hata'): self
    {
        return $this->ekleYanit(
            $kokEleman,
            '<ERROR_TYPE xmlns=""><INTL_TXN_ID>1</INTL_TXN_ID><ERROR_CODE>' . $kod . '</ERROR_CODE>'
            . '<ERROR_SHORT_DES>' . $mesaj . '</ERROR_SHORT_DES></ERROR_TYPE>'
        );
    }

    public function gonder(string $adres, string $govde): array
    {
        $this->istekler[] = ['adres' => $adres, 'govde' => $govde];
        if ($this->yanitlar === []) {
            throw new RuntimeException('Sahte taşıyıcıda yanıt kalmadı: ' . $govde);
        }

        return array_shift($this->yanitlar);
    }

    public function sonGovde(): string
    {
        return $this->istekler[array_key_last($this->istekler)]['govde'];
    }

    /** İstek gövdelerinin kök eleman adları, ör. ['LoginRequest', 'CheckUserRequest'] */
    public function operasyonlar(): array
    {
        return array_map(
            static fn (array $i) => preg_match('/<ns:(\w+)/', $i['govde'], $m) ? $m[1] : '?',
            $this->istekler,
        );
    }
}
