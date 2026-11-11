# WHMCS Telefon Numarası Doğrulama

Bu bağımsız addon, WHMCS çekirdek ve tema dosyalarına dokunmadan müşteri telefon numarası için OTP doğrulaması sağlar. Mevcut Netgsm ve WhatsApp addonlarının ayarlarını kullanır; onların dosyalarını değiştirmez.

## Kurulum

1. `modules/addons/phone_verification` klasörünü WHMCS kurulumundaki aynı konuma yükleyin.
2. **Setup > Addon Modules** bölümünden **Telefon Numarası Doğrulama** addonunu etkinleştirin.
3. Doğrulama zorunluluğu ve kullanılacak kanalı ayarlayın.
4. Netgsm kullanılacaksa mevcut Netgsm addonunda kullanıcı kodu, şifre ve SMS başlığı ayarlı olmalıdır.
5. WhatsApp kullanılacaksa mevcut WhatsApp addonunda servis çalışır ve bağlı durumda olmalıdır.

## Zorunluluk modları

- **Kapalı:** Telefon doğrulama zorunluluğu uygulanmaz.
- **Kayıt sırasında zorunlu:** Kayıt formunda telefon numarası gönderildiğinde kod gönderilir; müşteri doğru kodu girmeden kayıt tamamlanmaz.
- **Kayıttan sonra (hesap düzenleme ve ürün öncesi):** Müşteri hesabı oluşturulabilir. Müşteri hesabını düzenlerken veya Telefon Doğrulama sayfasından kod isteyebilir. Doğrulanmış telefon olmadan checkout/sipariş engellenir.

Kayıt sırasında ve hesap düzenleme formlarında doğrulama kanalı ile OTP alanı hook üzerinden forma eklenir. Tema dosyası değiştirilmez. İlk gönderimde kod gönderilir; kod girilerek form ikinci kez gönderilir.

## Kanallar

- **Netgsm SMS:** `netgsm_sms_settings` tablosundaki mevcut Netgsm ayarlarını kullanır ve Netgsm OTP XML endpointine güvenli TLS doğrulamasıyla istek gönderir.
- **WhatsApp:** Mevcut `WhatsAppBridge::sendSync()` sınıfını kullanır. WhatsApp addonunun kendi OTP tablosu kullanılmaz; bu addon kendi OTP kayıtlarını tutar.
- **Kullanıcı seçsin:** İki kanal hazırsa müşteri formdan SMS veya WhatsApp seçebilir. Yalnızca biri hazırsa yalnızca hazır kanal gösterilir.

## Veri ve güvenlik

OTP kodu düz metin saklanmaz; WHMCS `cc_encryption_hash` anahtarıyla HMAC-SHA-256 özeti tutulur. Doğrulama tablosunda telefonun kendisi tutulmaz, yalnızca telefon özeti ve son dört hanesi tutulur. Kod süresi, yeniden gönderme beklemesi ve hatalı deneme sayısı modül ayarlarından sınırlanır. Netgsm hesabı ve WhatsApp API anahtarı bu addon tarafından kopyalanmaz.

Telefon numarası kişisel veri olduğundan HTTPS, erişim yetkileri, yedekler, KVKK aydınlatma ve saklama politikası ayrıca değerlendirilmelidir.

## Yerel smoke test

PHP CLI bulunan geliştirme ortamında `php modules/addons/phone_verification/tests/smoke.php` komutuyla telefon biçimi ve yapılandırma modu testleri çalıştırılabilir. Netgsm/WhatsApp'a gerçek mesaj göndermez.