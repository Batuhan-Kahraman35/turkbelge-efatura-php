# turkbelge-efatura-php

Türkbelge e-Fatura / e-Arşiv web servisleri için framework'ten bağımsız PHP istemcisi.

> ⚠️ Geliştirme aşamasında (v0.1 öncesi). Kimlik, okuma, UBL-TR oluşturucu ve e-Fatura gönderimi test ortamında doğrulandı; e-Arşiv gönderimi test ortamında henüz doğrulanmadı.

## Neden?

Türkbelge servisleri SOAP tabanlıdır ve şemaları **eleman sırasına duyarlıdır**. PHP'nin `SoapClient` sınıfıyla çalışırken sık karşılaşılan sorunlar:

- tek kayıt dönünce dizi yerine nesne gelmesi, `->_` değerleri
- `base64Binary` alanlarda içeriğin iki kez kodlanması ("PAKET AYRIŞTIRILAMADI")
- oturumun süresi dolunca çağrıların sessizce başarısız olması

Bu kütüphane istekleri XSD sırasıyla elle kurar, yanıtları ad alanından bağımsız okur, oturumu önbellekte tutar ve süresi dolduğunda otomatik yeniler.

## Kurulum

```bash
composer require batuhan-kahraman35/turkbelge-efatura-php
```

Gereksinimler: PHP 8.1+, `curl` ve `dom` eklentileri. ZIP içerikler saf PHP ile bellekte açılır; `ext-zip` ve geçici dizin gerekmez.

## Hızlı başlangıç

```php
use Turkbelge\EFatura\Ayarlar;
use Turkbelge\EFatura\Istemci;
use Turkbelge\EFatura\Ortam;
use Turkbelge\EFatura\Oturum\DosyaDeposu;

$istemci = new Istemci(
    new Ayarlar(
        kullanici: getenv('TURKBELGE_KULLANICI'),
        sifre: getenv('TURKBELGE_SIFRE'),
        ortam: Ortam::TEST,          // canlı için Ortam::CANLI
    ),
    new DosyaDeposu('/var/app/oturum'), // web kökünün DIŞINDA bir dizin
);

// Alıcı e-Fatura mükellefi mi?
$mukellef = $istemci->kimlik()->mukellefSorgula('1234567890');

if ($mukellef !== null) {
    echo $mukellef->unvan;        // GİB'deki ünvan
    echo $mukellef->pkEtiketi();  // fatura gönderilecek posta kutusu etiketi
} else {
    // mükellef değil: fatura e-Arşiv olarak kesilir
}
```

> Kullanıcı adı ve şifreyi kod içinde tutmayın; ortam değişkeni, şifreli yapılandırma veya veritabanından okuyun.

## Özellikler

| Servis | Metot | Durum |
|---|---|---|
| Kimlik | `mukellefSorgula()` | ✅ |
| Kimlik | `cikis()` | ✅ |
| Kimlik | `hesapBilgisi()`, `yetkiliMi()` | 🧪 Deneysel: WSDL'de var, dokümanda yok; test ortamında çalışmıyor |
| e-Fatura | `liste()`, `tumu()`, `getir()`, `gorsel()` (PDF/HTML/XML), `durum()`, `durumlar()`, `sayim()` | ✅ |
| e-Fatura | `gonder()`, `gonderXml()`, `taslakYukle()`, `taslakSil()` | ✅ |
| e-Arşiv | `liste()`, `tumu()`, `durum()`, `durumlar()` (500'erli otomatik parçalanır) | ✅ |
| e-Arşiv | `getir()`, `gorsel()` | 🧪 Canlıda kullanılan istek biçimi; test ortamında taslak dışı kayıt olmadığı için henüz doğrulanmadı |
| e-Arşiv | `gonder()` (taslak / e-posta), `iptal()` | 🧪 Şemaya göre yazıldı; test hesabında e-Arşiv serisi tanımlı olmadığı için doğrulanmadı |
| UBL-TR | `UblOlusturucu`: satış, iade, istisna; TEMEL / TİCARİ / e-Arşiv; döviz, ardışık satır iskontosu, TCKN, IBAN, sipariş/irsaliye referansı, XSLT gömme | ✅ GİB XSD ile doğrulanır |
| UBL-TR | tevkifat, ihracat, özel matrah, internet satışı | 🚧 |

### e-Fatura okuma

```php
use Turkbelge\EFatura\Model\FaturaFiltresi;
use Turkbelge\EFatura\Model\Yon;

$efatura = $istemci->efatura();

// Gelen faturalar (yalnız başlık). Tamamı için tumu(): limit dolarsa aralığı kendisi böler.
$filtre = new FaturaFiltresi(Yon::GELEN, new DateTime('2026-09-01'), new DateTime('2026-09-30'));
foreach ($efatura->tumu($filtre) as $fatura) {
    echo $fatura->faturaNo, ' ', $fatura->gonderenUnvan, ' ', $fatura->tutar, ' ', $fatura->paraBirimi, PHP_EOL;
}

$ubl = $efatura->getir($ettn, Yon::GELEN)?->icerik;     // UBL XML
$pdf = $efatura->gorsel($ettn, Yon::GELEN, 'PDF');      // PDF ikili veri (ZIP bellekte açılır)

// GİB portalından iptal edilmiş mi? Bu bilgi yalnız tekil durum sorgusunda gelir.
$durum = $efatura->durum($ettn);
if ($durum->iptalEdildiMi()) { /* ... */ }
```

### e-Arşiv okuma

```php
use Turkbelge\EFatura\Model\EArsivDurumu;
use Turkbelge\EFatura\Model\EArsivFiltresi;

$earsiv = $istemci->earsiv();

foreach ($earsiv->tumu(new EArsivFiltresi(new DateTime('2026-09-01'), new DateTime('2026-09-30'), seri: 'ABC')) as $f) {
    if ($f->taslakMi() || $f->iptalEdildiMi()) {
        continue;
    }
    echo $f->faturaNo, ' ', $f->aliciUnvan, ' ', $f->tutar, PHP_EOL;
}

$pdf = $earsiv->gorsel($ettn, 'PDF');

$durum = $earsiv->durum($ettn);
$durum->durumKodu === EArsivDurumu::RAPORLANDI;   // 100 kuyrukta · 105 taslak · 120 raporlanacak · 130 raporlandı · 150 iptal
```

### UBL-TR fatura oluşturma

```php
use Turkbelge\EFatura\Ubl\{Adres, FaturaBilgisi, FaturaTipi, Iskonto, Kalem, Senaryo, Taraf, UblOlusturucu};

$satici = new Taraf('1234567890', new Adres('Konak', 'İzmir', 'Örnek Cad. No:1'), 'ÖRNEK A.Ş.', vergiDairesi: 'Konak');
$alici  = new Taraf('11111111111', new Adres('Kadıköy', 'İstanbul'), ad: 'Ayşe', soyad: 'Yılmaz');

$fatura = new FaturaBilgisi(
    senaryo: Senaryo::EARSIV,
    tip: FaturaTipi::SATIS,
    satici: $satici,
    alici: $alici,
    kalemler: [
        new Kalem('Ürün', miktar: 2, birimFiyat: 100, kdvOrani: 20, iskontolar: [Iskonto::oran(10)]),
        new Kalem('Kargo', 1, 50, 20),
    ],
    seri: 'EAR',                 // numarayı servis atar: EAR2026000000000 yer tutucusu yazılır
    gonderimSekli: 'ELEKTRONIK', // e-Arşivde zorunlu
    xslt: file_get_contents('sablon.xslt'), // görüntü şablonu: her faturada zorunlu
);

$xml = (new UblOlusturucu())->olustur($fatura);
echo $fatura->ettn;
```

- Eleman sırası GİB UBL-TR 1.2.1 şemasıyla birebir aynıdır; testler üretilen XML'i resmi XSD'ye karşı doğrular.
- Tutarlar satır bazında 2 haneye yuvarlanır, toplamlar yuvarlanmış satırların toplamıdır.
- İskonto Türkbelge portalının kuralıyla yazılır: belge `LineExtensionAmount` brüt, `AllowanceTotalAmount` toplam iskonto, `TaxExclusiveAmount` net.
- Hatalı bilgi (ör. %0 KDV'de istisna kodu yok, iade faturasında referans yok, dövizde kur yok) servise gitmeden `InvalidArgumentException` ile yakalanır.

### Gönderim

```php
// e-Fatura: alıcı mükellefse
$mukellef = $istemci->kimlik()->mukellefSorgula($alici->vknTckn);
$sonuc = $istemci->efatura()->gonder($fatura, $mukellef->pkEtiketi());
echo $sonuc->faturaNo;   // servisin atadığı numara, ör. ABC2026000000001

// Taslak: GİB'e gitmez, portaldan onaylanabilir
$istemci->efatura()->taslakYukle($fatura);
$istemci->efatura()->taslakSil($fatura->ettn);

// e-Arşiv: alıcı mükellef değilse (senaryo EARSIV)
$sonuc = $istemci->earsiv()->gonder($fatura, epostalar: ['musteri@ornek.com']);
$istemci->earsiv()->iptal($sonuc->ettn, $sonuc->faturaNo, new DateTime(), 'Hatalı tutar');
```

> **XSLT şablonu zorunludur.** GİB her faturada görüntü şablonu bekler; e-Arşiv servisi şablonsuz faturayı
> "Belge içerisinde şablon bulunamamıştır" hatasıyla reddeder. Entegratör portalınızdaki şablonu veya GİB UBL-TR
> paketindeki `general.xslt` dosyasını kullanabilirsiniz.

> **Seri öneki.** Numara servise bıraktığınızda (seri: 'ABC') kullandığınız seri hesabınızda tanımlı olmalıdır;
> e-Fatura ve e-Arşiv için ayrı seriler tanımlanır. Tanımsız seride servis 10013 döner.

## Oturum

- Oturum servis tarafında 8 saat geçerlidir; kütüphane güvenlik payıyla **7 saat** saklar.
- Süresi dolmuş oturumda (hata `10004`) otomatik yeniden giriş yapıp çağrıyı **bir kez** tekrarlar.
- Türkbelge hatalı şifreye de `10004` döner; bu durumda yeniden deneme yapılmaz.
- Depolar: `BellekDeposu` (varsayılan, süreç boyunca), `DosyaDeposu`, ya da `OturumDeposu` arayüzüyle kendi önbelleğiniz (Redis, PSR-16, Laravel Cache…).

## Hatalar

| İstisna | Ne zaman |
|---|---|
| `Hata\TurkbelgeHatasi` | Servisin döndürdüğü iş hatası. `$e->hataKodu`, `$e->ayrinti`, `$e->islemNo` |
| `Hata\BaglantiHatasi` | Ağ hatası, XML olmayan yanıt, SOAP Fault. `$e->httpKodu` |

Sık görülen kodlar: `10004` geçersiz oturum / hatalı şifre · `10008` kayıt bulunamadı · `10003` şema hatası · `10010` 5MB veya 100 belge sınırı · `10009` / `10017` belge daha önce gönderilmiş.

## Ortamlar

| | Test | Canlı |
|---|---|---|
| Kimlik | `efaturawstest.turkbelge.com.tr/AuthenticationWS` | `authenticationws.turkbelge.com.tr/AuthenticationWS` |
| e-Fatura | `efaturawstest.turkbelge.com.tr/EFaturaOIB` | `efaturaws.turkbelge.com.tr/EFaturaOIB` |
| e-Arşiv | `earsivwstest.turkbelge.com.tr/EIArchiveWS/EFaturaArchive` | `earsivws.turkbelge.com.tr/EIArchiveWS/EFaturaArchive` |

Farklı adres gerekirse: `new Ayarlar(..., adresler: ['EFATURA' => 'https://...'])`.

Test hesabı için Türkbelge destek ekibine başvurun. Servis dokümantasyonu: https://dev.turkbelge.com.tr

## Geliştirme

```bash
composer install
composer ubl-xsd              # GİB UBL-TR şemalarını indirir (XSD doğrulama testleri için, depoya girmez)
composer test                 # ağa çıkmayan birim testleri

# Türkbelge test ortamına bağlanan testler
export TURKBELGE_TEST_KULLANICI=...
export TURKBELGE_TEST_SIFRE=...
export TURKBELGE_TEST_ALICI_VKN=...   # isteğe bağlı
composer test-entegrasyon
```

## Lisans

MIT. Ayrıntılar: [LICENSE](LICENSE)

## Geliştirici

[Batuhan KAHRAMAN](https://github.com/Batuhan-Kahraman35)
