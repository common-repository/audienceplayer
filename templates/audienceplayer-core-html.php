<?php
/*
 * Include core HTML/PHP templates necessary for basic operations (modal EmbedPlayer, dialogues)
 */
?>

<div id="audienceplayer-modal-generic-loader" class="audienceplayer-modal audienceplayer-modal-generic-loader"></div>

<!-- html elements for the AudiencePlayer modal EmbedPlayer -->
<div id="audienceplayer-modal-video-player" class="audienceplayer-modal audienceplayer-modal-video-player">

    <div class="media-player">
        <div class="media-player__layer media-player__meta-container media-player__meta meta-wrapper">
            <div class="meta-title"></div>
            <div class="meta-element"></div>
        </div>
        <div class="media-player__layer media-player__overlay"></div>
        <div class="media-player__layer media-player__loader vjs-waiting">
            <div class="vjs-loading-spinner"></div>
        </div>
        <div class="audienceplayer-modal-video-player-wrapper media-player__layer media-player__video-player"></div>
    </div>
</div>

<!-- html elements for the AudiencePlayer modal dialogs -->
<div id="audienceplayer-modal-dialog-alert" class="audienceplayer-modal audienceplayer-modal-alert">
    <div class="content">
        <button class="close"><i class="audienceplayer-fa close"></i></button>
        <div class="message"></div>

        <div class="value-input-container">
        <div class="value-input"><input id="audienceplayer-modal-dialog-alert-value-input" class="value-character-input" /></div>
        <div class="value-validation-message"></div>
        </div>

        <div class="buttons">
            <button class="cancel"><?php echo $AudiencePlayerWordpressPlugin->fetchTranslations('button_cancel'); ?></button>
            <button class="ok"><?php echo $AudiencePlayerWordpressPlugin->fetchTranslations('button_ok'); ?></button>
        </div>
    </div>
</div>

<div id="audienceplayer-modal-dialog-purchase" class="audienceplayer-modal audienceplayer-modal-purchase">
    <div class="content">
        <button class="close"><i class="audienceplayer-fa close"></i></button>
        <div class="message"></div>

        <div class="voucher">
            <div class="voucher-description"><?php echo $AudiencePlayerWordpressPlugin->fetchTranslations('dialogue_information_validate_voucher_code'); ?></div>
            <div class="voucher-input">
                <input class="voucher-code-input">
                <button class="voucher-validate-button"><?php echo $AudiencePlayerWordpressPlugin->fetchTranslations('button_redeem_voucher'); ?></button>
            </div>
            <div class="voucher-code-validation-result"></div>
        </div>

        <div class="buttons">
            <button class="ok"><?php echo $AudiencePlayerWordpressPlugin->fetchTranslations('button_payment_accept_complete'); ?></button>
        </div>

        <div class="price"></div>
    </div>
</div>
