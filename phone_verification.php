<?php

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

use WHMCS\Database\Capsule;

const PHONE_VERIFICATION_MODULE = 'phone_verification';
const PHONE_VERIFICATION_TABLE = 'mod_phone_verification_clients';
const PHONE_VERIFICATION_OTP_TABLE = 'mod_phone_verification_otps';

function phone_verification_config()
{
    return array(
        'name' => 'Telefon Numarası Doğrulama',
        'description' => 'Netgsm SMS ve mevcut WhatsApp modülü ile telefon numarası OTP doğrulaması.',
        'version' => '1.0.0',
        'author' => 'Custom Module',
        'language' => 'turkish',
        'fields' => array(
            'enabled' => array(
                'FriendlyName' => 'Modül durumu',
                'Type' => 'yesno',
                'Default' => 'on',
                'Description' => 'Telefon doğrulama akışını etkinleştirir.',
            ),
            'requirementmode' => array(
                'FriendlyName' => 'Doğrulama zorunluluğu',
                'Type' => 'dropdown',
                'Options' => 'Kapalı,Kayıt sırasında zorunlu,Kayıttan sonra (hesap düzenleme ve ürün öncesi)',
                'Default' => 'Kayıttan sonra (hesap düzenleme ve ürün öncesi)',
                'Description' => 'Kayıt sonrası modda müşteri hesabını düzenlerken doğrulama yapar ve ürün/sipariş işleminden önce doğrulanmış telefon gerekir.',
            ),
            'deliverymode' => array(
                'FriendlyName' => 'Doğrulama kanalı',
                'Type' => 'dropdown',
                'Options' => 'Netgsm SMS,WhatsApp,Kullanıcı seçsin (SMS veya WhatsApp)',
                'Default' => 'Kullanıcı seçsin (SMS veya WhatsApp)',
                'Description' => 'Netgsm ve WhatsApp addon ayarları/erişimi ayrıca hazır olmalıdır.',
            ),
            'smstemplate' => array(
                'FriendlyName' => 'SMS mesajı',
                'Type' => 'textarea',
                'Rows' => '3',
                'Default' => 'Telefon doğrulama kodunuz: {code}. Bu kod {minutes} dakika geçerlidir.',
                'Description' => '{code} ve {minutes} değişkenleri kullanılabilir.',
            ),
            'whatsapptemplate' => array(
                'FriendlyName' => 'WhatsApp mesajı',
                'Type' => 'textarea',
                'Rows' => '3',
                'Default' => 'Telefon doğrulama kodunuz: {code}. Bu kod {minutes} dakika geçerlidir.',
                'Description' => '{firstname}, {lastname}, {code} ve {minutes} değişkenleri kullanılabilir.',
            ),
            'expireminutes' => array(
                'FriendlyName' => 'Kod geçerlilik süresi (dakika)',
                'Type' => 'text',
                'Size' => '8',
                'Default' => '5',
                'Description' => '1 ile 15 arasında.',
            ),
            'resendseconds' => array(
                'FriendlyName' => 'Yeniden gönderme bekleme süresi (saniye)',
                'Type' => 'text',
                'Size' => '8',
                'Default' => '60',
                'Description' => '15 ile 300 arasında.',
            ),
            'maxattempts' => array(
                'FriendlyName' => 'Maksimum hatalı kod denemesi',
                'Type' => 'text',
                'Size' => '8',
                'Default' => '5',
                'Description' => '3 ile 10 arasında.',
            ),
        ),
    );
}

function phone_verification_activate()
{
    phone_verification_ensure_tables();
    Capsule::table('tbladdonmodules')->updateOrInsert(
        array('module' => PHONE_VERIFICATION_MODULE, 'setting' => 'enabled'),
        array('value' => 'on')
    );

    return array(
        'status' => 'success',
        'description' => 'Telefon doğrulama tabloları hazırlandı. Mevcut Netgsm ve WhatsApp modüllerinin ayarları değiştirilmedi.',
    );
}

function phone_verification_deactivate()
{
    try {
        Capsule::table('tbladdonmodules')
            ->where('module', PHONE_VERIFICATION_MODULE)
            ->where('setting', 'enabled')
            ->update(array('value' => 'off'));
    } catch (Throwable $exception) {
        return array(
            'status' => 'error',
            'description' => 'Modül kapatılırken ayarlar güncellenemedi: ' . $exception->getMessage(),
        );
    }
    return array(
        'status' => 'success',
        'description' => 'Modül devre dışı bırakıldı. Doğrulama kayıtları ve müşteri verileri silinmedi.',
    );
}

function phone_verification_upgrade($vars)
{
    try {
        phone_verification_ensure_tables();
        return array(
            'status' => 'success',
            'description' => 'Telefon doğrulama modülü veri yapısı güncellendi.',
        );
    } catch (Throwable $exception) {
        return array(
            'status' => 'error',
            'description' => 'Telefon doğrulama modülü güncellenemedi: ' . $exception->getMessage(),
        );
    }
}

function phone_verification_output($vars)
{
    $settings = phone_verification_settings();
    $methods = phone_verification_methods_for_settings($settings);
    $mode = phone_verification_requirement_mode($settings['requirementmode'] ?? '');

    echo '<h2>Telefon Numarası Doğrulama</h2>';
    echo '<p>Zorunluluk: <strong>' . htmlspecialchars(phone_verification_requirement_label($mode), ENT_QUOTES, 'UTF-8') . '</strong></p>';
    echo '<p>Yapılandırılan kanal: <strong>' . htmlspecialchars($settings['deliverymode'] ?? '', ENT_QUOTES, 'UTF-8') . '</strong></p>';
    echo '<p>Çalışır kanallar: <strong>' . ($methods ? htmlspecialchars(implode(', ', $methods), ENT_QUOTES, 'UTF-8') : 'Bulunamadı') . '</strong></p>';
    echo '<p>Netgsm ayarları mevcut Netgsm addonunun <code>netgsm_sms_settings</code> tablosundan okunur. WhatsApp gönderimi mevcut <code>WhatsAppBridge</code> sınıfı üzerinden yapılır.</p>';
    echo '<p>Modül devre dışı bırakılırken kendi doğrulama kayıtlarını silmez ve mevcut SMS/WhatsApp modüllerinin tablolarına müdahale etmez.</p>';
}

function phone_verification_clientarea($vars)
{
    $settings = phone_verification_settings();
    $mode = phone_verification_requirement_mode($settings['requirementmode'] ?? '');
    $clientId = phone_verification_current_client_id();
    $phone = phone_verification_client_phone($clientId);
    $methods = phone_verification_methods_for_settings($settings);
    $selectedMethod = phone_verification_default_method($settings, $methods);
    $error = '';
    $success = '';
    $status = phone_verification_is_verified($clientId, $phone);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            if (function_exists('check_token')) {
                check_token('WHMCS.default');
            }
            $selectedMethod = phone_verification_choose_method($_POST['phone_verification_method'] ?? '', $settings, $methods);

            if (isset($_POST['phone_verification_send'])) {
                $result = phone_verification_send_code($phone, $selectedMethod, $clientId);
                if (!$result['ok']) {
                    throw new RuntimeException($result['message']);
                }
                $success = 'Doğrulama kodu telefonunuza gönderildi.';
            } elseif (isset($_POST['phone_verification_verify'])) {
                $result = phone_verification_verify_code($phone, $selectedMethod, trim((string) ($_POST['phone_verification_code'] ?? '')), $clientId);
                if (!$result['ok']) {
                    throw new RuntimeException($result['message']);
                }
                $status = true;
                $success = 'Telefon numaranız başarıyla doğrulandı.';
            }
        } catch (Throwable $exception) {
            $error = $exception->getMessage();
        }
    }

    return array(
        'pagetitle' => 'Telefon Numarası Doğrulama',
        'breadcrumb' => array('index.php?m=' . PHONE_VERIFICATION_MODULE => 'Telefon Doğrulama'),
        'templatefile' => 'clientarea',
        'requirelogin' => true,
        'forcessl' => true,
        'vars' => array(
            'phoneVerificationEnabled' => phone_verification_setting_enabled($settings['enabled'] ?? 'off'),
            'phoneNumber' => phone_verification_mask_phone($phone),
            'phoneIsVerified' => $status,
            'phoneVerificationMethods' => $methods,
            'phoneVerificationMethod' => $selectedMethod,
            'phoneVerificationRequirementMode' => $mode,
            'phoneVerificationError' => $error,
            'phoneVerificationSuccess' => $success,
            'phoneVerificationToken' => $vars['token'] ?? (function_exists('generate_token') ? generate_token('plain') : ''),
        ),
    );
}

function phone_verification_ensure_tables()
{
    if (!Capsule::schema()->hasTable(PHONE_VERIFICATION_TABLE)) {
        Capsule::schema()->create(PHONE_VERIFICATION_TABLE, function ($table) {
            $table->increments('id');
            $table->unsignedInteger('client_id')->unique();
            $table->string('phone_hash', 64);
            $table->string('phone_last4', 4)->nullable();
            $table->string('method', 20);
            $table->dateTime('verified_at');
            $table->timestamps();
        });
    }

    if (!Capsule::schema()->hasTable(PHONE_VERIFICATION_OTP_TABLE)) {
        Capsule::schema()->create(PHONE_VERIFICATION_OTP_TABLE, function ($table) {
            $table->increments('id');
            $table->unsignedInteger('client_id')->default(0);
            $table->string('session_key', 64)->nullable();
            $table->string('phone_hash', 64);
            $table->string('code_hash', 64);
            $table->string('reference_code', 64)->unique();
            $table->string('method', 20);
            $table->unsignedInteger('attempts')->default(0);
            $table->string('status', 20)->default('pending');
            $table->dateTime('created_at');
            $table->dateTime('expires_at');
            $table->dateTime('verified_at')->nullable();
            $table->index(array('client_id', 'status'));
            $table->index(array('session_key', 'status'));
        });
    }
}

function phone_verification_settings()
{
    try {
        return Capsule::table('tbladdonmodules')
            ->where('module', PHONE_VERIFICATION_MODULE)
            ->pluck('value', 'setting')
            ->all();
    } catch (Throwable $exception) {
        return array();
    }
}

function phone_verification_setting_enabled($value)
{
    return !in_array(strtolower(trim((string) $value)), array('', '0', 'off', 'no', 'false'), true);
}

function phone_verification_requirement_mode($value)
{
    $value = trim((string) $value);
    if (strpos($value, 'Kayıt sırasında') === 0) {
        return 'register';
    }
    if (strpos($value, 'Kayıttan sonra') === 0) {
        return 'before_order';
    }
    return 'disabled';
}

function phone_verification_requirement_label($mode)
{
    if ($mode === 'register') {
        return 'Kayıt sırasında zorunlu';
    }
    if ($mode === 'before_order') {
        return 'Kayıttan sonra, ürün öncesi zorunlu';
    }
    return 'Kapalı';
}

function phone_verification_delivery_mode($value)
{
    $value = trim((string) $value);
    if (strpos($value, 'Netgsm') === 0) {
        return 'sms';
    }
    if (strpos($value, 'WhatsApp') === 0) {
        return 'whatsapp';
    }
    return 'both';
}

function phone_verification_available_methods()
{
    $methods = array();
    if (phone_verification_netgsm_ready()) {
        $methods[] = 'sms';
    }
    if (phone_verification_whatsapp_ready()) {
        $methods[] = 'whatsapp';
    }
    return $methods;
}

function phone_verification_methods_for_settings($settings)
{
    $available = phone_verification_available_methods();
    $configured = phone_verification_delivery_mode($settings['deliverymode'] ?? '');
    if ($configured === 'sms') {
        return in_array('sms', $available, true) ? array('sms') : array();
    }
    if ($configured === 'whatsapp') {
        return in_array('whatsapp', $available, true) ? array('whatsapp') : array();
    }
    return $available;
}

function phone_verification_method_label($method)
{
    return $method === 'whatsapp' ? 'WhatsApp' : 'Netgsm SMS';
}

function phone_verification_default_method($settings, $methods)
{
    $configured = phone_verification_delivery_mode($settings['deliverymode'] ?? '');
    if ($configured === 'sms' && in_array('sms', $methods, true)) {
        return 'sms';
    }
    if ($configured === 'whatsapp' && in_array('whatsapp', $methods, true)) {
        return 'whatsapp';
    }
    return $methods[0] ?? 'sms';
}

function phone_verification_choose_method($requested, $settings, $methods)
{
    $configured = phone_verification_delivery_mode($settings['deliverymode'] ?? '');
    if ($configured !== 'both') {
        $requested = $configured;
    }
    if (!in_array($requested, $methods, true)) {
        throw new InvalidArgumentException('Seçilen telefon doğrulama kanalı hazır değil.');
    }
    return $requested;
}

function phone_verification_current_client_id()
{
    return isset($_SESSION['uid']) && is_numeric($_SESSION['uid']) ? (int) $_SESSION['uid'] : 0;
}

function phone_verification_client_id_from_vars($vars)
{
    foreach (array('userid', 'clientid', 'client_id') as $key) {
        if (isset($vars[$key]) && is_numeric($vars[$key]) && (int) $vars[$key] > 0) {
            return (int) $vars[$key];
        }
    }
    return phone_verification_current_client_id();
}

function phone_verification_extract_phone($vars = array())
{
    $phone = $vars['phonenumber'] ?? $_POST['phonenumber'] ?? '';
    return trim((string) $phone);
}

function phone_verification_client_phone($clientId)
{
    if (!$clientId) {
        return '';
    }
    try {
        return (string) Capsule::table('tblclients')->where('id', (int) $clientId)->value('phonenumber');
    } catch (Throwable $exception) {
        return '';
    }
}

function phone_verification_normalize_phone($phone)
{
    $phone = preg_replace('/[^0-9]/', '', (string) $phone);
    if (strpos($phone, '00') === 0) {
        $phone = substr($phone, 2);
    }
    if (strlen($phone) === 11 && $phone[0] === '0') {
        $phone = substr($phone, 1);
    }
    if (strlen($phone) === 10 && $phone[0] === '5') {
        $phone = '90' . $phone;
    }
    return preg_match('/^905[0-9]{9}$/', $phone) ? $phone : '';
}

function phone_verification_phone_hash($phone)
{
    return phone_verification_fingerprint(phone_verification_normalize_phone($phone));
}

function phone_verification_fingerprint($value)
{
    global $cc_encryption_hash;
    if (!isset($cc_encryption_hash) || !is_string($cc_encryption_hash) || $cc_encryption_hash === '') {
        throw new RuntimeException('WHMCS encryption key is unavailable.');
    }
    return hash_hmac('sha256', (string) $value, $cc_encryption_hash);
}

function phone_verification_mask_phone($phone)
{
    $normalized = phone_verification_normalize_phone($phone);
    return $normalized ? '+90 *** *** ' . substr($normalized, -4) : '';
}

function phone_verification_netgsm_ready()
{
    try {
        if (!Capsule::schema()->hasTable('netgsm_sms_settings')) {
            return false;
        }
        $settings = Capsule::table('netgsm_sms_settings')->first();
        return $settings && trim((string) $settings->usercode) !== '' && trim((string) $settings->password) !== '' && trim((string) $settings->defaultmsgheader) !== '';
    } catch (Throwable $exception) {
        return false;
    }
}

function phone_verification_whatsapp_ready()
{
    try {
        if (!Capsule::schema()->hasTable('whatsapp_settings')) {
            return false;
        }
        require_once __DIR__ . '/../whatsapp/WhatsAppClient.php';
        require_once __DIR__ . '/../whatsapp/WhatsAppBridge.php';
        return class_exists('WhatsAppBridge') && WhatsAppBridge::isActive();
    } catch (Throwable $exception) {
        return false;
    }
}

function phone_verification_scope_key($clientId)
{
    if ($clientId) {
        return null;
    }
    return session_id() ? hash('sha256', session_id()) : null;
}

function phone_verification_get_pending($phone, $method, $clientId)
{
    $query = Capsule::table(PHONE_VERIFICATION_OTP_TABLE)
        ->where('phone_hash', phone_verification_phone_hash($phone))
        ->where('method', $method)
        ->where('status', 'pending')
        ->where('expires_at', '>', date('Y-m-d H:i:s'));
    if ($clientId) {
        $query->where('client_id', (int) $clientId);
    } else {
        $query->where('session_key', phone_verification_scope_key(0));
    }
    return $query->orderBy('id', 'desc')->first();
}

function phone_verification_send_code($phone, $method, $clientId = 0)
{
    phone_verification_ensure_tables();
    $settings = phone_verification_settings();
    $normalized = phone_verification_normalize_phone($phone);
    if ($normalized === '') {
        return array('ok' => false, 'message' => 'Geçerli bir Türkiye cep telefonu numarası girilmelidir.');
    }

    try {
        $method = phone_verification_choose_method($method, $settings, phone_verification_available_methods());
    } catch (Throwable $exception) {
        return array('ok' => false, 'message' => $exception->getMessage());
    }

    $resendSeconds = max(15, min(300, (int) ($settings['resendseconds'] ?? 60)));
    $scopeKey = phone_verification_scope_key($clientId);
    $latest = Capsule::table(PHONE_VERIFICATION_OTP_TABLE)
        ->where('phone_hash', phone_verification_phone_hash($normalized))
        ->where('method', $method)
        ->whereIn('status', array('pending', 'verified'))
        ->orderBy('id', 'desc')
        ->first();
    if ($latest && time() - strtotime($latest->created_at) < $resendSeconds) {
        return array('ok' => false, 'message' => 'Yeni kod göndermek için biraz bekleyin.');
    }

    Capsule::table(PHONE_VERIFICATION_OTP_TABLE)
        ->where('phone_hash', phone_verification_phone_hash($normalized))
        ->where('method', $method)
        ->where('status', 'pending')
        ->update(array('status' => 'expired'));

    $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $minutes = max(1, min(15, (int) ($settings['expireminutes'] ?? 5)));
    $message = phone_verification_message($method, $code, $minutes, $clientId, $settings);

    $now = date('Y-m-d H:i:s');
    $referenceCode = bin2hex(random_bytes(32));
    $otpId = Capsule::table(PHONE_VERIFICATION_OTP_TABLE)->insertGetId(array(
        'client_id' => (int) $clientId,
        'session_key' => $scopeKey,
        'phone_hash' => phone_verification_phone_hash($normalized),
        'code_hash' => phone_verification_fingerprint($code),
        'reference_code' => $referenceCode,
        'method' => $method,
        'attempts' => 0,
        'status' => 'pending',
        'created_at' => $now,
        'expires_at' => date('Y-m-d H:i:s', time() + ($minutes * 60)),
    ));

    $provider = $method === 'whatsapp'
        ? phone_verification_send_whatsapp($normalized, $message)
        : phone_verification_send_netgsm($normalized, $message);
    if (!$provider['ok']) {
        Capsule::table(PHONE_VERIFICATION_OTP_TABLE)->where('id', $otpId)->update(array('status' => 'failed'));
        return $provider;
    }
    return array('ok' => true, 'message' => 'Kod gönderildi.');
}

function phone_verification_verify_code($phone, $method, $code, $clientId = 0)
{
    phone_verification_ensure_tables();
    $normalized = phone_verification_normalize_phone($phone);
    if ($normalized === '' || !preg_match('/^[0-9]{6}$/', $code)) {
        return array('ok' => false, 'message' => 'Telefon numarası veya doğrulama kodu geçersiz.');
    }

    $settings = phone_verification_settings();
    try {
        $method = phone_verification_choose_method($method, $settings, phone_verification_available_methods());
    } catch (Throwable $exception) {
        return array('ok' => false, 'message' => $exception->getMessage());
    }
    $otp = phone_verification_get_pending($normalized, $method, $clientId);
    if (!$otp) {
        return array('ok' => false, 'message' => 'Aktif doğrulama kodu bulunamadı. Yeni kod isteyin.');
    }

    $maxAttempts = max(3, min(10, (int) ($settings['maxattempts'] ?? 5)));
    if ((int) $otp->attempts >= $maxAttempts) {
        Capsule::table(PHONE_VERIFICATION_OTP_TABLE)->where('id', $otp->id)->update(array('status' => 'expired'));
        return array('ok' => false, 'message' => 'Çok fazla hatalı deneme nedeniyle kod iptal edildi.');
    }
    if (!hash_equals((string) $otp->code_hash, phone_verification_fingerprint($code))) {
        $attempts = (int) $otp->attempts + 1;
        Capsule::table(PHONE_VERIFICATION_OTP_TABLE)->where('id', $otp->id)->update(array(
            'attempts' => $attempts,
            'status' => $attempts >= $maxAttempts ? 'expired' : 'pending',
        ));
        return array('ok' => false, 'message' => 'Doğrulama kodu hatalı.');
    }

    Capsule::table(PHONE_VERIFICATION_OTP_TABLE)->where('id', $otp->id)->update(array(
        'status' => 'verified',
        'verified_at' => date('Y-m-d H:i:s'),
    ));
    if ($clientId) {
        phone_verification_mark_client_verified($clientId, $normalized, $method);
    } else {
        if (!isset($_SESSION['phone_verification_verified']) || !is_array($_SESSION['phone_verification_verified'])) {
            $_SESSION['phone_verification_verified'] = array();
        }
        $_SESSION['phone_verification_verified'][phone_verification_phone_hash($normalized)] = array(
            'method' => $method,
            'verified_at' => time(),
        );
    }
    return array('ok' => true, 'message' => 'Telefon numarası doğrulandı.');
}

function phone_verification_message($method, $code, $minutes, $clientId, $settings)
{
    $template = $method === 'whatsapp'
        ? ($settings['whatsapptemplate'] ?? '')
        : ($settings['smstemplate'] ?? '');
    if ($template === '') {
        $template = 'Telefon doğrulama kodunuz: {code}. Bu kod {minutes} dakika geçerlidir.';
    }
    $client = $clientId ? Capsule::table('tblclients')->where('id', (int) $clientId)->first() : null;
    return str_replace(
        array('{firstname}', '{lastname}', '{code}', '{minutes}'),
        array($client->firstname ?? '', $client->lastname ?? '', $code, $minutes),
        $template
    );
}

function phone_verification_send_netgsm($phone, $message)
{
    if (!function_exists('curl_init')) {
        return array('ok' => false, 'message' => 'SMS doğrulaması için PHP cURL eklentisi gereklidir.');
    }
    try {
        $settings = Capsule::table('netgsm_sms_settings')->first();
        if (!$settings || trim((string) $settings->usercode) === '' || trim((string) $settings->password) === '' || trim((string) $settings->defaultmsgheader) === '') {
            return array('ok' => false, 'message' => 'Netgsm ayarları eksik veya Netgsm modülü etkin değil.');
        }
        $xml = '<?xml version="1.0" encoding="UTF-8"?><mainbody><header>'
            . '<usercode>' . phone_verification_xml($settings->usercode) . '</usercode>'
            . '<password>' . phone_verification_xml($settings->password) . '</password>'
            . '<msgheader>' . phone_verification_xml($settings->defaultmsgheader) . '</msgheader>'
            . '</header><body><msg><![CDATA[' . str_replace(']]>', ']]]]><![CDATA[>', $message) . ']]></msg><no>' . phone_verification_xml($phone) . '</no></body></mainbody>';
        $handle = curl_init('https://api.netgsm.com.tr/sms/send/otp');
        curl_setopt_array($handle, array(
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $xml,
            CURLOPT_HTTPHEADER => array('Content-Type: text/xml; charset=UTF-8'),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ));
        $body = curl_exec($handle);
        $error = curl_error($handle);
        curl_close($handle);
        if ($body === false || $error !== '') {
            return array('ok' => false, 'message' => 'Netgsm SMS servisine ulaşılamadı.');
        }
        if (!preg_match('/^\s*00(?:\s|$)/', (string) $body)) {
            return array('ok' => false, 'message' => 'Netgsm SMS gönderimini kabul etmedi.');
        }
        return array('ok' => true, 'message' => 'SMS gönderildi.');
    } catch (Throwable $exception) {
        return array('ok' => false, 'message' => 'Netgsm SMS ayarları okunamadı.');
    }
}

function phone_verification_send_whatsapp($phone, $message)
{
    try {
        require_once __DIR__ . '/../whatsapp/WhatsAppClient.php';
        require_once __DIR__ . '/../whatsapp/WhatsAppBridge.php';
        if (!class_exists('WhatsAppBridge') || !WhatsAppBridge::isActive()) {
            return array('ok' => false, 'message' => 'WhatsApp modülü etkin veya bağlı değil.');
        }
        $result = WhatsAppBridge::sendSync($phone, $message);
        return !empty($result['success'])
            ? array('ok' => true, 'message' => 'WhatsApp mesajı gönderildi.')
            : array('ok' => false, 'message' => 'WhatsApp mesajı gönderilemedi.');
    } catch (Throwable $exception) {
        return array('ok' => false, 'message' => 'WhatsApp modülüne ulaşılamadı.');
    }
}

function phone_verification_xml($value)
{
    return htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

function phone_verification_is_verified($clientId, $phone)
{
    if (!$clientId) {
        return false;
    }
    try {
        $record = Capsule::table(PHONE_VERIFICATION_TABLE)->where('client_id', (int) $clientId)->first();
        return $record && phone_verification_normalize_phone($phone) !== '' && hash_equals((string) $record->phone_hash, phone_verification_phone_hash($phone));
    } catch (Throwable $exception) {
        return false;
    }
}

function phone_verification_mark_client_verified($clientId, $phone, $method)
{
    $normalized = phone_verification_normalize_phone($phone);
    if (!$clientId || !$normalized) {
        return false;
    }
    Capsule::table(PHONE_VERIFICATION_TABLE)->updateOrInsert(
        array('client_id' => (int) $clientId),
        array(
            'phone_hash' => phone_verification_phone_hash($normalized),
            'phone_last4' => substr($normalized, -4),
            'method' => $method,
            'verified_at' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        )
    );
    return true;
}

function phone_verification_recent_verified($clientId, $phone)
{
    $normalized = phone_verification_normalize_phone($phone);
    if (!$normalized) {
        return false;
    }
    $phoneHash = phone_verification_phone_hash($normalized);
    if (!$clientId && isset($_SESSION['phone_verification_verified'][$phoneHash])) {
        $sessionRecord = $_SESSION['phone_verification_verified'][$phoneHash];
        if (isset($sessionRecord['verified_at']) && (int) $sessionRecord['verified_at'] > time() - 900) {
            return true;
        }
        unset($_SESSION['phone_verification_verified'][$phoneHash]);
    }
    $query = Capsule::table(PHONE_VERIFICATION_OTP_TABLE)
        ->where('phone_hash', $phoneHash)
        ->where('status', 'verified')
        ->where('verified_at', '>', date('Y-m-d H:i:s', time() - 900));
    if ($clientId) {
        $query->where('client_id', (int) $clientId);
    } else {
        $query->where('session_key', phone_verification_scope_key(0));
    }
    return (bool) $query->first();
}

function phone_verification_clear_client($clientId)
{
    if ($clientId) {
        Capsule::table(PHONE_VERIFICATION_TABLE)->where('client_id', (int) $clientId)->delete();
    }
}

function phone_verification_validate_submission($vars, $isRegistration, $clientId)
{
    $settings = phone_verification_settings();
    if (!phone_verification_setting_enabled($settings['enabled'] ?? 'off') || isset($_SESSION['adminid'])) {
        return array();
    }
    $mode = phone_verification_requirement_mode($settings['requirementmode'] ?? '');
    if ($isRegistration && $mode !== 'register') {
        return array();
    }
    if (!$isRegistration && $mode !== 'before_order') {
        return array();
    }
    $phone = phone_verification_extract_phone($vars);
    $normalized = phone_verification_normalize_phone($phone);
    if ($normalized === '') {
        return $isRegistration || $clientId ? array('Telefon doğrulaması için geçerli bir Türkiye cep telefonu numarası zorunludur.') : array();
    }
    if ($clientId && phone_verification_is_verified($clientId, $normalized)) {
        return array();
    }
    $method = $_POST['phone_verification_method'] ?? phone_verification_default_method(
        $settings,
        phone_verification_methods_for_settings($settings)
    );
    $code = trim((string) ($_POST['phone_verification_code'] ?? ''));
    if ($code === '') {
        $result = phone_verification_send_code($normalized, $method, $clientId);
        return $result['ok']
            ? array('Telefonunuza doğrulama kodu gönderildi. Kodu girip formu tekrar gönderin.')
            : array($result['message']);
    }
    $result = phone_verification_verify_code($normalized, $method, $code, $clientId);
    return $result['ok'] ? array() : array($result['message']);
}
