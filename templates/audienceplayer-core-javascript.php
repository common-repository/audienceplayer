<?php
$scriptEmbedQsa = '?ver=' . $AudiencePlayerWordpressPlugin::fetchPluginCacheString();
$audiencePlayerBearerToken = $AudiencePlayerWordpressPlugin->fetchUserBearerToken();
$isWordpressUserLoggedIn = \is_user_logged_in();
$isAudiencePlayerUserLoggedIn = $audiencePlayerBearerToken || false;
?>
<script type="module">

    // Expand config with necessary credentials
    window.AudiencePlayerLib = {};

    // Api properties
    window.AudiencePlayerLib.apiBaseUrl = '<?php echo $AudiencePlayerWordpressPlugin->fetchApiBaseUrl(); ?>';
    window.AudiencePlayerLib.siteBaseUrl = '<?php echo \site_url(); ?>';
    window.AudiencePlayerLib.chromecastReceiverAppId = '<?php echo $AudiencePlayerWordpressPlugin->fetchChromecastReceiverAppId(); ?>';
    window.AudiencePlayerLib.projectId = '<?php echo $AudiencePlayerWordpressPlugin->fetchProjectId(); ?>';
    window.AudiencePlayerLib.paymentProviderId = '<?php echo $AudiencePlayerWordpressPlugin->fetchPaymentProviderId(); ?>';
    window.AudiencePlayerLib.userBearerToken = '<?php echo $audiencePlayerBearerToken; ?>';
    window.AudiencePlayerLib.userBearerTokenTtl = <?php echo ($AudiencePlayerWordpressPlugin->fetchUserBearerTokenTtl() - 60) * 1000; ?>;
    window.AudiencePlayerLib.currentUrl = '<?php echo $AudiencePlayerWordpressPlugin->fetchCurrentUrl(); ?>';
    window.AudiencePlayerLib.loginUrlWithRedirect = '<?php echo $AudiencePlayerWordpressPlugin->fetchLoginUrl(true); ?>';
    window.AudiencePlayerLib.isLoggedIn = <?php echo $isWordpressUserLoggedIn ? 'true' : 'false'; ?>;
    window.AudiencePlayerLib.isSynchronised = <?php echo $isAudiencePlayerUserLoggedIn ? 'true' : 'false'; ?>;
    window.AudiencePlayerLib.tokenParameter = window.AudiencePlayerLib.userBearerToken ? {token: window.AudiencePlayerLib.userBearerToken} : {};
    window.AudiencePlayerLib.isNomadicWatchingEnabled = <?php echo $AudiencePlayerWordpressPlugin->isNomadicWatchingEnabled() ? 'true' : 'false'; ?>;
    window.AudiencePlayerLib.isPlayerSpeedRatesEnabled = <?php echo $AudiencePlayerWordpressPlugin->isPlayerSpeedRatesEnabled() ? 'true' : 'false'; ?>;

    // Translations
    window.AudiencePlayerLib.translations = {
        <?php
        $translations = $AudiencePlayerWordpressPlugin->fetchTranslations(null, '', [], false);

        foreach ($translations as $key => $value) {
            echo $key . ': \'' . $AudiencePlayerWordpressPlugin->helper->sanitiseJsString($value) . '\',';
        }
        ?>
    };

    // Import external dependencies
    import {
        EmbedPlayer,
        ChromecastControls
    } from '<?php echo $AudiencePlayerWordpressPlugin->fetchEmbedPlayerLibraryBaseUrl(); ?>/bundle.js<?php echo $scriptEmbedQsa; ?>';

    const containerEl = document.querySelector('.media-player');
    const splashEl = document.querySelector('.media-player__overlay');
    const metaEl = document.querySelector('.media-player__meta');

    // Video player options
    const closeEl = document.createElement('div');
    closeEl.className = 'vjs-custom-overlay';
    closeEl.innerHTML = '<div class="close-button"><i class="fa-chevron-left fa"></i></div>';
    closeEl.addEventListener('click', () => {
        window.AudiencePlayerCore.closeModal(true);
    });

    window.AudiencePlayerLib.videoPlayerOptions = {
        inactivityTimeout: 3000,
        autoplay: true,
        overlay: {element: metaEl},
        customOverlay: {element: closeEl}
    };
    if (window.AudiencePlayerLib.isPlayerSpeedRatesEnabled) {
        window.AudiencePlayerLib.videoPlayerOptions.playbackRates = [0.5, 0.75, 1, 1.25, 1.5, 2];
    }

    // Initialize player
    const initParam = {
        selector: '.media-player__video-player',
        options: window.AudiencePlayerLib.videoPlayerOptions,
    }
    window.AudiencePlayerLib.EmbedPlayer = new EmbedPlayer({
        projectId: window.AudiencePlayerLib.projectId,
        apiBaseUrl: window.AudiencePlayerLib.apiBaseUrl,
        chromecastReceiverAppId: window.AudiencePlayerLib.chromecastReceiverAppId
    });
    window.AudiencePlayerLib.EmbedPlayer.initVideoPlayer(initParam);

    if (window.AudiencePlayerLib.chromecastReceiverAppId) {

        window.AudiencePlayerLib.EmbedPlayer
            .initChromecast()
            .then(() => {
                const controls = new ChromecastControls(
                    window.AudiencePlayerLib.EmbedPlayer.getCastPlayer(),
                    window.AudiencePlayerLib.EmbedPlayer.getCastPlayerController()
                );

                // add the chromecast button
                window.AudiencePlayerLib.EmbedPlayer.appendChromecastButton('#cast-wrapper');

                const castSender = window.AudiencePlayerLib.EmbedPlayer.getCastSender();
                castSender.onConnectedListener(({connected, friendlyName}) => {
                    window.AudiencePlayerLib.EmbedPlayer.initVideoPlayer({
                        ...initParam,
                    });
                    // "friendlyName" can be used to display in the front-end if so desired
                    containerEl.classList.remove(connected ? 'media-player--video' : 'media-player--chromecast');
                    containerEl.classList.add(connected ? 'media-player--chromecast' : 'media-player--video');
                });
            })
            .catch(e => {
                containerEl.classList.add('media-player--video');
            });
    }
</script>
<script>
    <?php

    /*
     * Create global error message accessible to unsupported browsers
     */
    echo 'window.AudiencePlayerBrowserNotSupportedError = \'' .
        $AudiencePlayerWordpressPlugin->helper()
            ->sanitiseJsString($AudiencePlayerWordpressPlugin->fetchTranslations('dialogue_not_optimised_for_current_browser')) .
        '\';';

    /*
     * Handle redirect/afterPurchase action after user returns from external payment provider url
     * N.B. the purchase modal action handlers based on "purchase_product_id" and "purchase_subscription_id" are located
     * in the respective shortcode templates
     */
    if ($orderId = intval($_REQUEST['user_payment_account_order_id'] ?? 0)) {

        if ($AudiencePlayerWordpressPlugin->isOrderValidated($orderId)) {

            if ($_REQUEST['after_purchase_action'] ?? null) {
                echo $AudiencePlayerWordpressPlugin->executeJsShortCodeAction($_REQUEST['after_purchase_action']);
            } else {
                // Open success modal and remove query-parameter from current URL
                echo 'jQuery(function ($) {
                    window.AudiencePlayerCore.openModalAlert(null, window.AudiencePlayerCore.parseTranslationKey("dialogue_payment_successfully_completed"));
                    window.AudiencePlayerCore.removeQsaParametersFromCurrentLocation(["user_payment_account_order_id"]);
                });';
            }

        } else {
            // Open failure modal, and only remove query-parameter if User closes modal
            echo 'jQuery(function ($) {
                window.AudiencePlayerCore.openModalAlert(
                    null,
                    window.AudiencePlayerCore.parseTranslationKey("dialogue_payment_not_validated"),
                    window.AudiencePlayerCore.parseTranslationKey("button_retry"),
                    window.AudiencePlayerCore.reloadCurrentPage,
                    window.AudiencePlayerCore.parseTranslationKey("button_close"),
                    function(){window.AudiencePlayerCore.removeQsaParametersFromCurrentLocation(["user_payment_account_order_id"])}
                );
            });';
        }
    }
    ?>
</script>
