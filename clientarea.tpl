{if !$phoneVerificationEnabled}
    <div class="alert alert-info">Telefon doğrulama modülü şu anda etkin değil.</div>
{else}
    {if $phoneVerificationError}
        {include file="$template/includes/alert.tpl" type="error" msg=$phoneVerificationError}
    {/if}
    {if $phoneVerificationSuccess}
        {include file="$template/includes/alert.tpl" type="success" msg=$phoneVerificationSuccess}
    {/if}

    <div class="card">
        <div class="card-body">
            <h3 class="card-title">Telefon Numarası Doğrulama</h3>
            {if $phoneIsVerified}
                <div class="alert alert-success">Telefon numaranız doğrulanmıştır.</div>
            {elseif $phoneVerificationRequirementMode == 'disabled'}
                <div class="alert alert-info">Telefon doğrulama zorunluluğu şu anda kapalıdır.</div>
            {else}
                {if $phoneVerificationRequirementMode == 'before_order'}
                    <div class="alert alert-warning">Ürün almadan önce telefon numaranızı doğrulamanız gerekiyor.</div>
                {else}
                    <div class="alert alert-warning">Kayıt işlemini tamamlamak için telefon numaranızı doğrulamanız gerekiyor.</div>
                {/if}
            {/if}
            <p>Telefon: <strong>{$phoneNumber|escape}</strong></p>
            {if !$phoneNumber}
                <div class="alert alert-danger">Hesabınızda geçerli bir Türkiye cep telefonu numarası bulunamadı. Önce hesap bilgilerinizi güncelleyin.</div>
            {elseif $phoneVerificationMethods|@count == 0}
                <div class="alert alert-danger">Kullanılabilir bir SMS veya WhatsApp doğrulama kanalı bulunamadı.</div>
            {else}
                <form method="post" action="index.php?m=phone_verification">
                    {if $phoneVerificationToken}<input type="hidden" name="token" value="{$phoneVerificationToken|escape}" />{/if}
                    <div class="form-group">
                        <label for="phoneVerificationMethod">Doğrulama kanalı</label>
                        <select name="phone_verification_method" id="phoneVerificationMethod" class="form-control">
                            {foreach from=$phoneVerificationMethods item=method}
                                <option value="{$method|escape}"{if $method == $phoneVerificationMethod} selected{/if}>{if $method == 'whatsapp'}WhatsApp{else}Netgsm SMS{/if}</option>
                            {/foreach}
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="phoneVerificationCode">Doğrulama kodu</label>
                        <input type="text" name="phone_verification_code" id="phoneVerificationCode" class="form-control" inputmode="numeric" autocomplete="one-time-code" maxlength="6" />
                    </div>
                    <button type="submit" name="phone_verification_send" value="1" class="btn btn-secondary">Kod Gönder</button>
                    <button type="submit" name="phone_verification_verify" value="1" class="btn btn-primary">Kodu Doğrula</button>
                </form>
            {/if}
        </div>
    </div>
{/if}