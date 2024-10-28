<?php

$content = $content ?? '';

if ($AudiencePlayerWordpressPlugin->isAuthenticated() && \is_user_logged_in()) {

    $result = $AudiencePlayerWordpressPlugin->api()->query
        ->UserDetails()
        ->properties(['
            id
            phone
            notifiable
            is_phone_verified
            user_subscriptions {
                id
                status
                subscription_id
                switch_to_subscription_id
                suspendable_at
                expires_at
                invoiced_at
                is_valid
                is_suspendable
                is_account_method_changeable
                is_switchable
                subscription {id title description_short}
                account {id method method_details}
            }
            orders {id description currency_symbol amount status status_name installed_at}
            ', ''])
        ->execute();

    $AudiencePlayerWordpressPlugin->pushPublicDebugStack('[audienceplayer_shortcode_user_account] > userDetails', $AudiencePlayerWordpressPlugin->api()->fetchLastOperationQuery(true), $AudiencePlayerWordpressPlugin->api()->fetchLastOperationResult());

    if ($result->isSuccessful() && ($user = ($result->getData(true)))) {

        // assemble authentication and authorisation actions
        $actionAuthentication = $AudiencePlayerWordpressPlugin->parseJsShortCodeAction(($args['authentication_action'] ?? null) ?: $AudiencePlayerWordpressPlugin->fetchLoginUrl(true));

        $userSubscription = null;
        $switchToSubscriptionId = null;
        $switchToSubscriptionTitle = null;
        $validUserSubscription = null;
        $expiresAt = '-';
        $invoicedAt = '-';
        $isSuspendable = false;
        $isSuspended = false;
        $isSwitchable = false;
        $isValidAccountPaymentMethod = false;
        $isNotifiable = $user->notifiable === 'none' ? false : true;

        foreach ($user->user_subscriptions as $userSubscription) {
            if ($userSubscription->is_valid) {
                $validUserSubscription = $userSubscription;
                $expiresAt = $AudiencePlayerWordpressPlugin->parseDateTimeValue(($validUserSubscription->suspendable_at ?? null) ?: ($validUserSubscription->expires_at ?? null));
                $invoicedAt = $AudiencePlayerWordpressPlugin->parseDateTimeValue(($validUserSubscription->invoiced_at ?? null) ?: ($validUserSubscription->invoiced_at ?? null));
                $isSuspendable = $validUserSubscription->is_suspendable ?? true;
                $isSuspended = $validUserSubscription->status === 'suspended';
                $isSwitchable = $userSubscription->is_switchable;
                $isValidAccountPaymentMethod = boolval($validUserSubscription->account->method ?? false);
                $switchToSubscriptionId = $userSubscription->switch_to_subscription_id && $userSubscription->switch_to_subscription_id !== $userSubscription->id ? $userSubscription->switch_to_subscription_id : null;
                break;
            }
        }

        $content .= '<div class="audienceplayer-user-account ' . ($args['class'] ?? '') . '">';
        $content .= '<div class="wrapper">';

        /* ### subscription info ##################################################################################### */
        if ($args['show_subscription_info']) {

            // If a switchable userSubscription exists, parse the subscription-switch section
            if (
                $userSubscription && $isSwitchable &&
                ($paymentProviderId = $AudiencePlayerWordpressPlugin->fetchPaymentProviderId())
            ) {
                // Fetch available subscriptions to switch to
                $result = $AudiencePlayerWordpressPlugin->api()->query
                    ->SubscriptionList([$paymentProviderId])
                    ->execute();
                $AudiencePlayerWordpressPlugin->pushPublicDebugStack('[audienceplayer_shortcode_user_account > SubscriptionList]', $AudiencePlayerWordpressPlugin->api()->fetchLastOperationQuery(true), $AudiencePlayerWordpressPlugin->api()->fetchLastOperationResult());

                // Proceed only if subscriptions are found
                if ($result->isSuccessful() && ($subscriptions = ($result->getData(true)->items))) {

                    // Order and prune fetched result by given ids
                    if (isset($args['subscription_ids']) && $args['subscription_ids']) {
                        $subscriptions = $AudiencePlayerWordpressPlugin->orderResultByIds($subscriptions, $args['subscription_ids']);
                    }

                    // Proceed if subscription(s) to switch to exist, other than current userSubscription->subscription_id)
                    if (count($subscriptions) > 1 || $subscriptions[0]->id !== $userSubscription->subscription_id) {

                        $contentModalSubscriptions = '';

                        foreach ($subscriptions as $subscription) {

                            // Mark $subscription as current if it matches switchToSubscriptionId,
                            // or when not set, check if it matches current userSubscription->subscription_id
                            if ($switchToSubscriptionId) {
                                $isSwitchToOrCurrentSubscriptionId = $subscription->id === $switchToSubscriptionId;
                            } elseif ($subscription->id === $userSubscription->subscription_id) {
                                $isSwitchToOrCurrentSubscriptionId = true;
                            } else {
                                $isSwitchToOrCurrentSubscriptionId = false;
                            }

                            // Fetch the subscription title for the subscription to switch to (if set)
                            if ($switchToSubscriptionId && $subscription->id === $switchToSubscriptionId) {
                                $switchToSubscriptionTitle = $subscription->title;
                            }

                            $timeUnitLabel = $AudiencePlayerWordpressPlugin->fetchTranslations('label_time_unit_per_' . $subscription->time_unit . '_' . $subscription->frequency) ?:
                                $AudiencePlayerWordpressPlugin->fetchTranslations('label_time_unit_per_' . $subscription->time_unit);
                            $description = ($args['show_description']) ?
                                '<div class="description">' . $subscription->description . '</div>' :
                                '';
                            $buttonId = 'btn_' . md5('switch_subscription_button_' . microtime(true)) . '_' . $subscription->id;
                            $onclickAction = '';

                            $buttomHtml = $isSwitchToOrCurrentSubscriptionId ?
                                '<button id="' . $buttonId . '" class="switch-subscription-button disabled" disabled>' .
                                ($args['label'] ?: $AudiencePlayerWordpressPlugin->fetchTranslations('button_select_subscription')) .
                                '</button>'
                                :
                                '<button id="' . $buttonId . '" class="switch-subscription-button" value="' . ($subscription->id === $userSubscription->subscription_id ? 0 : $subscription->id) . '" onclick="' . $onclickAction . '">' .
                                ($args['label'] ?: $AudiencePlayerWordpressPlugin->fetchTranslations('button_select_subscription')) .
                                '</button>';

                            $contentModalSubscriptions .=
                                '<div class="banner subscription-id-' . $subscription->id . ' ' . ($isSwitchToOrCurrentSubscriptionId ? 'is-popular-choice' : '') . '">' .
                                '<div class="wrapper">' . (
                                $isSwitchToOrCurrentSubscriptionId ?
                                    '<div class="popular-choice active">' . $AudiencePlayerWordpressPlugin->fetchTranslations('label_current_user_subscription') . '</div>' :
                                    '<div class="popular-choice"></div>'
                                ) .
                                '<div class="title"><h6>' . $subscription->title . '</h6></div>' .
                                '<div class="price">' . $subscription->currency_symbol . ' ' . $subscription->price_per_installment . '</div>' .
                                '<div class="time-unit">' . $timeUnitLabel . '</div>' .
                                $description .
                                $buttomHtml .
                                '</div>' .
                                '</div>';
                        }

                        if ($contentModalSubscriptions) {
                            $content .= '<div id="audienceplayer-modal-dialog-subscription-switch" class="audienceplayer-modal audienceplayer-modal audienceplayer-modal-subscription-switch">
                                <div class="content">
                                    <button class="close"><i class="audienceplayer-fa close"></i></button>
                                    <div class="message">' . $AudiencePlayerWordpressPlugin->fetchTranslations('dialogue_switch_user_subscription') . '</div>
                                    <div class="subscription-switch-banners">
                                    ' . $contentModalSubscriptions . '
                                    </div>
                                </div>
                            </div>';
                        }

                        // Only allow switch-flow if a valid payment method is configured, else ensure a modal is displayed
                        if ($isValidAccountPaymentMethod) {

                            // Fill the subscription-switch link with enabled flow
                            $contentSwitchSubscriptionModalLink = '<div class="message">' . (
                                    '<a href="javascript:void(0);" onclick="window.AudiencePlayerCore.openModalSubscriptionSwitch(this, ' . $userSubscription->id . ');">' .
                                    $AudiencePlayerWordpressPlugin->fetchTranslations('link_switch_user_subscription') .
                                    '</a>'
                                ) . '</div>';

                        } else {

                            // Fill the subscription-switch link with enabled flow with error message
                            $contentSwitchSubscriptionModalLink = '<div class="message">' . (
                                    '<a href="javascript:void(0);" onclick="window.AudiencePlayerCore.openModalAlert(null, window.AudiencePlayerCore.parseTranslationKey(\'dialogue_payment_method_not_configured\'));">' .
                                    $AudiencePlayerWordpressPlugin->fetchTranslations('link_switch_user_subscription') .
                                    '</a>'
                                ) . '</div>';
                        }


                        $contentSwitchSubscriptionNotification = '<div class="message">' . (
                            $switchToSubscriptionId ?
                                $AudiencePlayerWordpressPlugin->fetchTranslations('dialogue_user_subscription_will_switch_at_renewal', '', [
                                    'subscription_title' => $switchToSubscriptionTitle,
                                ]) :
                                ''
                            ) . '</div>';
                    }
                }
            }

            // Prepare main content for subscription_info
            $title = (isset($validUserSubscription, $validUserSubscription->subscription)) ?
                ($validUserSubscription->subscription->title ?? '-') : '-';

            $content .=
                '<div class="subscription-info section">' .
                '<div class="wrapper">' .

                '<div class="description subsection">' .
                '<div class="label">' . $AudiencePlayerWordpressPlugin->fetchTranslations('label_subscription') . '</div>' .
                '<div class="value">' . $title . '</div>' .
                ($contentSwitchSubscriptionModalLink ?? '') .
                '</div>' .

                '<div class="expires-at subsection">' .
                '<div class="label">' . $AudiencePlayerWordpressPlugin->fetchTranslations('label_valid_until') . '</div>' .
                '<div class="value">' . $expiresAt . '</div>' .
                '</div>' .

                '<div class="invoiced-at subsection">' .
                '<div class="label">' . $AudiencePlayerWordpressPlugin->fetchTranslations('label_date_next_invoice') . '</div>' .
                '<div class="value ' . ($isSuspended ? 'disabled' : '') . '">' . $invoicedAt . '</div>' .
                ($contentSwitchSubscriptionNotification ?? '') .
                '</div>' .

                '</div>' .
                '</div>';
        }

        /* ### voucher redeem ######################################################################################## */
        if ($args['show_voucher_redeem']) {
            $content .=
                '<div class="voucher-redeem section">' .
                '<div class="wrapper">' .

                '<div class="label">' . $AudiencePlayerWordpressPlugin->fetchTranslations('label_redeem_voucher_code') . '</div>' .

                '<div class="voucher">' .
                '<div class="input"><input class="voucher-validate-input" id="audienceplayer-user-account-validate-voucher-input" /></div>' .
                '<div class="button"><button class="voucher-validate-button" id="audienceplayer-user-account-validate-voucher-button">' . $AudiencePlayerWordpressPlugin->fetchTranslations('button_redeem_voucher') . '</button></div>' .
                '</div>' .

                '</div>' .
                '</div>';

            $content .= '<script>jQuery(function($){' .
                '$("#audienceplayer-user-account-validate-voucher-button").click(function(){' .
                'window.AudiencePlayerCore.redeemUserAccountVoucherCode(' .
                'this,' .
                '$("#audienceplayer-user-account-validate-voucher-input").val(),' .
                $AudiencePlayerWordpressPlugin->fetchPaymentProviderId() . ',' .
                ($validUserSubscription ? $validUserSubscription->subscription->id : 0) . ',' .
                ($validUserSubscription ? $validUserSubscription->id : 0) .
                ');' .
                '});' .
                '});</script>';
        }

        /* ### subscription suspend ################################################################################## */
        if ($args['show_subscription_suspend'] && $isSuspendable) {

            $content .=
                '<div class="subscription-suspend section">' .
                '<div class="wrapper">' .

                '<div class="label">' . $AudiencePlayerWordpressPlugin->fetchTranslations('label_renew_subscription') . '</div>' .
                '<div class="description">' . $AudiencePlayerWordpressPlugin->fetchTranslations('dialogue_information_renew_suspend_subscription') . '</div>' .
                '<div class="value">' .
                '<div class="audienceplayer-toggle-input"><div class="toggle-input">' .
                '<input id="audienceplayer-user-subscription-is-suspend-' . $validUserSubscription->id . '" name="audienceplayer_user_subscription_is_suspend" type="checkbox" ' . ($isSuspended ? '' : 'checked') . ' />' .
                '<label for="audienceplayer-user-subscription-is-suspend-' . $validUserSubscription->id . '" ' .
                'data-off="' . $AudiencePlayerWordpressPlugin->fetchTranslations('form_toggle_recurring_subscription_off') . '" ' .
                'data-on="' . $AudiencePlayerWordpressPlugin->fetchTranslations('form_toggle_recurring_subscription_on') . '" ' .
                '></label>' .
                '</div></div>' .
                '</div>' .

                '</div>' .
                '</div>';

            $content .= '<script>jQuery(function($){' .
                '$("#audienceplayer-user-subscription-is-suspend-' . $validUserSubscription->id . '").click(function(){' .
                'window.AudiencePlayerCore.toggleUserSubscriptionStatus(this,' . $validUserSubscription->id . ',this.checked,' . $actionAuthentication . ');' .
                '});' .
                '});</script>';
        }

        /* ### notifiable ############################################################################################ */
        if ($args['show_notifiable']) {

            $content .=
                '<div class="subscription-notifiable section">' .
                '<div class="wrapper">' .

                '<div class="label">' . $AudiencePlayerWordpressPlugin->fetchTranslations('label_sms_notifications') . '</div>' .
                '<div class="description">' . $AudiencePlayerWordpressPlugin->fetchTranslations('dialogue_activate_sms_notifications') . '</div>' .
                '<div class="value">' .
                '<div class="audienceplayer-toggle-input"><div class="toggle-input">' .
                '<input id="audienceplayer-user-is-notifiable-' . $user->id . '" name="audienceplayer_user_is_notifiable" type="checkbox" ' . ($isNotifiable ? 'checked' : '') . ' />' .
                '<label for="audienceplayer-user-is-notifiable-' . $user->id . '" ' .
                'data-off="' . $AudiencePlayerWordpressPlugin->fetchTranslations('form_toggle_off') . '" ' .
                'data-on="' . $AudiencePlayerWordpressPlugin->fetchTranslations('form_toggle_on') . '" ' .
                '></label>' .
                '<input id="audienceplayer-user-phone-' . $user->id . '" name="audienceplayer_user_phone" type="hidden" value="' . $user->phone . '" />' .
                '</div></div>' .
                '</div>' .

                '</div>' .
                '</div>';

            $content .= '<script>jQuery(function($){' .
                '$("#audienceplayer-user-is-notifiable-' . $user->id . '").click(function(){' .
                'window.AudiencePlayerCore.toggleUserNotifiableStatus(this, this.checked, $("#audienceplayer-user-phone-' . $user->id . '"), ' . $actionAuthentication . ');' .
                '});' .
                '});</script>';
        }

        /* ### payment method ######################################################################################## */
        if ($args['show_payment_method'] && ($validUserSubscription->is_account_method_changeable ?? null)) {

            if ($isValidAccountPaymentMethod) {

                $accountDetails =
                    $validUserSubscription->account->method . '<br />' .
                    $validUserSubscription->account->method_details . '<br />' .
                    '<div><a href="javascript:void(0);" onclick="window.AudiencePlayerCore.openModalPurchase(this, \'payment_method\',' .
                    $validUserSubscription->account->id . ',' .
                    'window.AudiencePlayerCore.CONFIG.translations.dialogue_payment_method_change_cost_explanation,' .
                    'false' .
                    ');">' . $AudiencePlayerWordpressPlugin->fetchTranslations('dialogue_payment_method_change') . '</a></div>';

            } else {

                $accountDetails =
                    $AudiencePlayerWordpressPlugin->fetchTranslations('dialogue_payment_method_not_configured') .
                    '<div><a href="javascript:void(0);" onclick="window.AudiencePlayerCore.openModalPurchase(this, \'payment_method\',' .
                    $validUserSubscription->account->id . ',' .
                    'window.AudiencePlayerCore.CONFIG.translations.dialogue_payment_method_change_cost_explanation,' .
                    'false' .
                    ');">' . $AudiencePlayerWordpressPlugin->fetchTranslations('dialogue_payment_method_add') . '</a></div>';
            }

            $content .=
                '<div class="payment-method section">' .
                '<div class="wrapper">' .
                '<div class="label">' . $AudiencePlayerWordpressPlugin->fetchTranslations('label_payment_method') . '</div>' .
                '<div class="description">' . $accountDetails . '</div>' .
                '</div>' .
                '</div>';
        }

        /* ### payment orders ######################################################################################## */
        if ($args['show_payment_orders'] && ($user->orders ?? null)) {

            $content .=
                '<div class="payment-orders section">' .
                '<div class="wrapper">' .
                '<div class="label">' . $AudiencePlayerWordpressPlugin->fetchTranslations('label_orders') . '</div>';

            $content .=
                '<table class="table" cellpadding="0" cellspacing="0" border="0">' .
                '<tr class="header">' .
                '<td>' . $AudiencePlayerWordpressPlugin->fetchTranslations('label_order_table_product') . '</td>' .
                '<td>' . $AudiencePlayerWordpressPlugin->fetchTranslations('label_order_table_amount') . '</td>' .
                '<td>' . $AudiencePlayerWordpressPlugin->fetchTranslations('label_order_table_date') . '</td>' .
                '<td>' . $AudiencePlayerWordpressPlugin->fetchTranslations('label_order_table_status') . '</td>' .
                '</tr>';

            foreach ($user->orders as $order) {

                $content .=
                    '<tr class="body">' .
                    '<td class="description">' . $order->description . '</td>' .
                    '<td class="amount">' . $order->currency_symbol . ' ' . $order->amount . '</td>' .
                    '<td class="date">' . $AudiencePlayerWordpressPlugin->parseDateTimeValue($order->installed_at) . '</td>' .
                    '<td class="status">' . $AudiencePlayerWordpressPlugin->fetchTranslations('label_order_table_status_' . $order->status, $order->status_name ?: $order->status) . '</td>' .
                    '</tr>';
            }

            $content .= '</table>';
            $content .= '</div>';
            $content .= '</div>';

        }

        /* ############################################################################################################### */

        $content .= '</div>';
        $content .= '</div>';

    } elseif (($code = $result->getFirstErrorCode()) > 500) {
        $msg = 'query error occurred, code: ' . $code;
        $AudiencePlayerWordpressPlugin->pushPublicDebugStack('[audienceplayer_shortcode_user_account]', $msg);
        \trigger_error('Warning in shortcode [audienceplayer_shortcode_user_account]: ' . $msg, E_USER_WARNING);
    }

} elseif (!current_user_can('manage_options')) {
    $msg = 'user is not authenticated, contents cannot be displayed';
    $AudiencePlayerWordpressPlugin->pushPublicDebugStack('[audienceplayer_shortcode_user_account]', $msg);
    \trigger_error('Warning in shortcode [audienceplayer_shortcode_user_account]: ' . $msg, E_USER_WARNING);
}

$AudiencePlayerWordpressPlugin->pushPublicDebugStack('[audienceplayer_shortcode_user_account]', $AudiencePlayerWordpressPlugin->api()->fetchLastOperationQuery(true), $AudiencePlayerWordpressPlugin->api()->fetchLastOperationResult());
