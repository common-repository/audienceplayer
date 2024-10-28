<?php

$content = $content ?? '';

if ($paymentProviderId = $AudiencePlayerWordpressPlugin->fetchPaymentProviderId()) {

    $result = $AudiencePlayerWordpressPlugin->api()->query
        ->SubscriptionList([$paymentProviderId])
        ->execute();

    if ($result->isSuccessful() && ($subscriptions = ($result->getData(true)->items))) {

        $recommendedSubscriptionId = intval($args['recommended_subscription_id'] ?? 0);

        $content .= '<div class="audienceplayer-purchase-subscriptions ' . ($args['class'] ?? '') . '">';

        // Order and prune fetched result by given ids
        $subscriptions = $AudiencePlayerWordpressPlugin->orderResultByIds($subscriptions, $args['subscription_ids']);

        foreach ($subscriptions as $subscription) {

            // assemble authentication and authorisation actions
            $actionAuthentication = $AudiencePlayerWordpressPlugin->helper()->populateTemplateString(
                $AudiencePlayerWordpressPlugin->parseJsShortCodeAction($args['authentication_action'] ?:
                    $AudiencePlayerWordpressPlugin->fetchLoginUrl(\add_query_arg(['purchase_subscription_id' => $subscription->id], $AudiencePlayerWordpressPlugin->fetchCurrentUrl()))),
                ['subscription_id' => $subscription->id],
                true
            );
            $actionAuthorisation = $AudiencePlayerWordpressPlugin->parseJsShortCodeAction($args['authorisation_action'] ?: '');
            $actionAfterPurchase = $AudiencePlayerWordpressPlugin->parseJsShortCodeAction($args['after_purchase_action'] ?: '', false);

            if ($recommendedSubscriptionId && $subscription->id === $recommendedSubscriptionId) {
                $isPopularChoice = true;
                $popularChoiceLabel = ($args['recommended_subscription_label'] ?? null) ?: $AudiencePlayerWordpressPlugin->fetchTranslations('label_preferred_subscription_ribbon');
            } else {
                $isPopularChoice = false;
                $popularChoiceLabel = '';
            }

            // assemble price
            $price = $subscription->currency_symbol . ' ' . $subscription->price;
            // assemble time-unit display ("per month, per year")
            $timeUnitLabel = $AudiencePlayerWordpressPlugin->fetchTranslations('label_time_unit_per_' . $subscription->time_unit . '_' . $subscription->frequency) ?:
                $AudiencePlayerWordpressPlugin->fetchTranslations('label_time_unit_per_' . $subscription->time_unit);

            // assemble description
            $description = ($args['show_description']) ?
                '<div class="description">' . $subscription->description . '</div>' :
                '';

            $onclickAction = "window.AudiencePlayerCore.openModalPurchase(this, 'subscription', " .
                $subscription->id . ',' .
                "'" . $AudiencePlayerWordpressPlugin->helper->sanitiseJsString($subscription->description_short ?: $subscription->description) . "'," .
                "'" . $price . "'," .
                (($args['show_voucher_validation'] ?? false) ? 'true' : 'false') . ',' .
                $actionAuthentication . ',' .
                $actionAuthorisation . ',' .
                $actionAfterPurchase .
                ');';

            $buttonId = 'btn_' . md5('purchase_subscription_button_' . microtime(true)) . '_' . $subscription->id;

            $content .=
                '<div class="banner subscription-id-' . $subscription->id . ' ' . ($isPopularChoice ? 'is-popular-choice' : '') . '">' .
                '<div class="wrapper">' .
                '<div class="popular-choice ' . ($isPopularChoice ? 'active' : '') . '">' . $popularChoiceLabel . '</div>' .
                '<div class="title"><h6>' . $subscription->title . '</h6></div>' .
                '<div class="price">' . $subscription->currency_symbol . ' ' . $subscription->price . '</div>' .
                '<div class="time-unit">' . $timeUnitLabel . '</div>' .
                $description .
                '<button id="' . $buttonId . '" class="purchase-subscription-button" onclick="' . $onclickAction . '">' .
                ($args['label'] ?: $AudiencePlayerWordpressPlugin->fetchTranslations('button_select_subscription')) .
                '</button>' .
                '</div>' .
                '</div>';

            if (intval($_REQUEST['purchase_subscription_id'] ?? 0) === $subscription->id && $onclickAction) {
                $content .= '<script>';
                // When a mutation with redirect-path (e.g. to Mollie) is fired, the query-string parameter "purchase_subscription_id" is automatically removed from the redirect-path
                // Also remove the query-string parameter from the current URL, should the redirect-path not be used (e.g. when fulfilling a zero-sum voucher, which simply triggers a page-reload)
                $content .= 'jQuery(function($){window.AudiencePlayerCore.removeQsaParametersFromCurrentLocation(["purchase_subscription_id"]);});';
                // Now trigger the purchase-subscription action
                $content .= 'jQuery(function($){$("#' . $buttonId . '")[0].onclick();});';
                // Unset the query-string parameter to avoid further usage in PHP
                unset($_REQUEST['purchase_subscription_id'], $_POST['purchase_subscription_id']);
                $content .= '</script>';
            }

        }

        $content .= '</div>';


    } elseif (($code = $result->getFirstErrorCode()) > 500) {
        $msg = 'query error occurred, code: ' . $code;
        $AudiencePlayerWordpressPlugin->pushPublicDebugStack('[audienceplayer_shortcode_purchase_subscriptions]', $msg);
        \trigger_error('Warning in shortcode [audienceplayer_shortcode_purchase_subscriptions]: ' . $msg, E_USER_WARNING);
    }

} else {
    $msg = '"payment_provider_id" is not configured in the Admin';
    $AudiencePlayerWordpressPlugin->pushPublicDebugStack('[audienceplayer_shortcode_purchase_subscriptions]', $msg);
    \trigger_error('Warning in shortcode [audienceplayer_shortcode_purchase_subscriptions]: ' . $msg, E_USER_WARNING);
}

$AudiencePlayerWordpressPlugin->pushPublicDebugStack('[audienceplayer_shortcode_purchase_subscriptions]', $AudiencePlayerWordpressPlugin->api()->fetchLastOperationQuery(true), $AudiencePlayerWordpressPlugin->api()->fetchLastOperationResult());
