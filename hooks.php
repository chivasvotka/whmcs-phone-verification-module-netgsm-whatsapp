<?php

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

require_once __DIR__ . '/phone_verification.php';

add_hook('ClientAreaPrimaryNavbar', 1, function ($menu) {
    $settings = phone_verification_settings();
    if (!phone_verification_setting_enabled($settings['enabled'] ?? 'off') || phone_verification_requirement_mode($settings['requirementmode'] ?? '') === 'disabled') {
        return;
    }
    $account = $menu->getChild('Account');
    if ($account) {
        $account->addChild('Telefon Doğrulama', array(
            'uri' => 'index.php?m=' . PHONE_VERIFICATION_MODULE,
            'order' => 91,
        ));
    }
});

add_hook('ClientAreaFooterOutput', 1, function ($vars) {
    $settings = phone_verification_settings();
    if (!phone_verification_setting_enabled($settings['enabled'] ?? 'off') || phone_verification_requirement_mode($settings['requirementmode'] ?? '') === 'disabled') {
        return '';
    }
    $methods = phone_verification_methods_for_settings($settings);
    $configured = phone_verification_delivery_mode($settings['deliverymode'] ?? '');
    $options = '';
    foreach ($methods as $method) {
        $selected = ($configured !== 'both' && $configured === $method) || ($configured === 'both' && $method === $methods[0]);
        $options .= '<option value="' . htmlspecialchars($method, ENT_QUOTES, 'UTF-8') . '"' . ($selected ? ' selected' : '') . '>' . htmlspecialchars(phone_verification_method_label($method), ENT_QUOTES, 'UTF-8') . '</option>';
    }
    $mode = phone_verification_requirement_mode($settings['requirementmode'] ?? '');
    if (!$methods || ($mode === 'register' && phone_verification_current_client_id()) || ($mode !== 'register' && !phone_verification_current_client_id())) {
        return '';
    }
    $intro = $mode === 'register'
        ? 'Kayıt işlemini tamamlamak için telefon numaranızı doğrulayın.'
        : 'Telefon numaranızı değiştirdiyseniz yeni numarayı doğrulamanız gerekir.';
    $boxHtml = '<strong>Telefon doğrulama</strong><p>' . $intro . '</p><label>Doğrulama kanalı</label><select name="phone_verification_method" class="form-control">' . $options . '</select><label style="margin-top:8px">Doğrulama kodu</label><input type="text" name="phone_verification_code" class="form-control" inputmode="numeric" autocomplete="one-time-code" maxlength="6" placeholder="6 haneli kod">';
    $boxHtmlJson = json_encode($boxHtml, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    return '<script>(function(){function addPhoneVerification(){document.querySelectorAll("form").forEach(function(form){var phone=form.querySelector("input[name=phonenumber]");if(!phone||form.querySelector(".phone-verification-addon")){return;}var box=document.createElement("div");box.className="phone-verification-addon alert alert-info";box.style.marginTop="15px";box.innerHTML=' . $boxHtmlJson . ';form.appendChild(box);});}if(document.readyState==="loading"){document.addEventListener("DOMContentLoaded",addPhoneVerification);}else{addPhoneVerification();}})();</script>';
});

add_hook('ClientDetailsValidation', 1, function ($vars) {
    $clientId = phone_verification_client_id_from_vars($vars);
    $isRegistration = !$clientId;
    return phone_verification_validate_submission($vars, $isRegistration, $clientId);
});

add_hook('ShoppingCartValidateCheckout', 1, function ($vars) {
    $settings = phone_verification_settings();
    if (!phone_verification_setting_enabled($settings['enabled'] ?? 'off')) {
        return array();
    }
    $mode = phone_verification_requirement_mode($settings['requirementmode'] ?? '');
    if ($mode !== 'before_order') {
        return array();
    }
    $clientId = phone_verification_current_client_id();
    if (!$clientId) {
        return array('Ürün/sipariş işleminden önce müşteri hesabınıza giriş yapıp telefon numaranızı doğrulamanız gerekir.');
    }
    $phone = phone_verification_client_phone($clientId);
    if (!phone_verification_is_verified($clientId, $phone) && !phone_verification_recent_verified($clientId, $phone)) {
        return array('Ürün/sipariş işleminden önce telefon numaranızı doğrulamanız gerekir. Hesabınızdaki Telefon Doğrulama sayfasını kullanın.');
    }
    return array();
});

add_hook('ClientAdd', 1, function ($vars) {
    $clientId = (int) ($vars['client_id'] ?? $vars['userid'] ?? 0);
    if (!$clientId || isset($_SESSION['adminid'])) {
        return;
    }
    $settings = phone_verification_settings();
    if (phone_verification_requirement_mode($settings['requirementmode'] ?? '') !== 'register') {
        return;
    }
    $phone = phone_verification_client_phone($clientId);
    if (phone_verification_recent_verified(0, $phone)) {
        phone_verification_mark_client_verified($clientId, $phone, 'registration');
    }
});

add_hook('ClientEdit', 1, function ($vars) {
    $clientId = (int) ($vars['userid'] ?? 0);
    if (!$clientId) {
        return;
    }
    $phone = phone_verification_client_phone($clientId);
    if (!phone_verification_normalize_phone($phone)) {
        phone_verification_clear_client($clientId);
        return;
    }
    if (phone_verification_is_verified($clientId, $phone)) {
        return;
    }
    if (phone_verification_recent_verified($clientId, $phone)) {
        phone_verification_mark_client_verified($clientId, $phone, 'account_edit');
    } else {
        phone_verification_clear_client($clientId);
    }
});
