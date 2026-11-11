<?php

define('WHMCS', true);
$cc_encryption_hash = 'phone-verification-smoke-test-key';
require_once dirname(__DIR__) . '/phone_verification.php';

$tests = array(
    array('Turkish phone with leading zero', phone_verification_normalize_phone('05012345678'), '905012345678'),
    array('Turkish phone with country code', phone_verification_normalize_phone('+90 (532) 123-45-67'), '905321234567'),
    array('International non-Turkish phone is rejected', phone_verification_normalize_phone('0033 612345678'), ''),
    array('Registration requirement mode', phone_verification_requirement_mode('Kayıt sırasında zorunlu'), 'register'),
    array('Post-registration requirement mode', phone_verification_requirement_mode('Kayıttan sonra (hesap düzenleme ve ürün öncesi)'), 'before_order'),
    array('Disabled requirement mode', phone_verification_requirement_mode('Kapalı'), 'disabled'),
    array('Netgsm delivery mode', phone_verification_delivery_mode('Netgsm SMS'), 'sms'),
    array('WhatsApp delivery mode', phone_verification_delivery_mode('WhatsApp'), 'whatsapp'),
    array('User-selected delivery mode', phone_verification_delivery_mode('Kullanıcı seçsin (SMS veya WhatsApp)'), 'both'),
);

foreach ($tests as $test) {
    if ($test[1] !== $test[2]) {
        fwrite(STDERR, $test[0] . ' failed: expected ' . var_export($test[2], true) . ', got ' . var_export($test[1], true) . PHP_EOL);
        exit(1);
    }
}

if (phone_verification_fingerprint('123456') === hash('sha256', '123456')) {
    fwrite(STDERR, 'OTP fingerprint must be keyed.' . PHP_EOL);
    exit(1);
}

echo count($tests) . ' phone verification smoke tests passed.' . PHP_EOL;