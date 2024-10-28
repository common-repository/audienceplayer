<?php
/**
 * Copyright (c) 2020, AudiencePlayer
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * - Redistributions of source code must retain the above copyright notice,
 *    this list of conditions and the following disclaimer.
 * - Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED BY THE AUTHOR AND CONTRIBUTORS "AS IS" AND ANY
 * EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE AUTHOR OR CONTRIBUTORS BE LIABLE FOR ANY
 * DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR
 * SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
 * LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY
 * OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH
 * DAMAGE.
 *
 * @license     Berkeley Software Distribution License (BSD-License 2) http://www.opensource.org/licenses/bsd-license.php
 * @author      AudiencePlayer <support@audienceplayer.com>
 * @copyright   AudiencePlayer
 * @link        https://www.audienceplayer.com
 */

namespace AudiencePlayer\AudiencePlayerWordpressPlugin\Resources;

use AudiencePlayer\AudiencePlayerApiClient\Resources\Globals;
use AudiencePlayer\AudiencePlayerWordpressPlugin\Config\Constants;
use DateTime;
use Exception;

trait IntegrationWooCommerceTrait
{

    /**
     * This integration triggers on WooCommerce order mutations and fulfils/revokes AudiencePlayer
     * products and subscriptions accordingly.
     *
     * Tested with:
     * - WooCommerce[7.1.0]
     * - WPSwings Subscriptions For Woocommerce [1.4.6]
     *   > https://wordpress.org/plugins/subscriptions-for-woocommerce/
     *   > https://github.com/wpswings/subscriptions-for-woocommerce
     * - WooCommerce Subscriptions
     *   > https://woocommerce.com/products/woocommerce-subscriptions/
     *   > https://github.com/pronamic/woocommerce-subscriptions
     * - WooCommerce Products [4.6.0]
     *
     * @param $wooCommerceOrderId
     * @return bool
     */
    public function syncWooCommerceOrderToAudiencePlayer($wooCommerceOrderId): bool
    {
        $ret = true;
        $isOrderUpdated = false;
        $logProperties = [
            'woocommerce_subscription_plugin' => null,
        ];

        try {

            if (function_exists('wc_get_order') && function_exists('wc_get_product')) {

                if ($wooCommerceOrder = wc_get_order($wooCommerceOrderId)) {

                    if ($wordpressUserId = $wooCommerceOrder->get_user_id()) {

                        $wooCommerceOrderId = $wooCommerceOrder->get_id();

                        foreach ($wooCommerceOrder->get_items() as $item) {

                            $product = wc_get_product($item->get_product_id());

                            foreach ($product->get_attributes() as $key => $attribute) {

                                if (in_array($key, ['audienceplayer_subscription_id', 'audienceplayer_product_id'])) {

                                    if (
                                        ($valueOptions = $attribute->get_options()) &&
                                        count($valueOptions) === 1 &&
                                        is_numeric($valueOptions[0]) &&
                                        ($audiencePlayerResourceId = intval($valueOptions[0]))
                                    ) {

                                        if ($key === 'audienceplayer_subscription_id') {

                                            $ret = false;
                                            $expiresAt = null;
                                            $isValid = false;
                                            $logProperties['audienceplayer_subscription_id'] = $audiencePlayerResourceId;

                                            if (function_exists('wcs_get_subscriptions_for_order')) {

                                                // ### Plugin: "WOOCOMMERCE SUBSCRIPTIONS"

                                                $logProperties['woocommerce_subscription_plugin'] = 'woocommerce-subscriptions';

                                                $subscriptions_ids = wcs_get_subscriptions_for_order($wooCommerceOrderId, ['order_type' => 'any']);
                                                $logProperties['wordpress_subscription_id'] = $subscriptions_ids;

                                                if (
                                                    count($subscriptions_ids) === 1 &&
                                                    ($subscription = array_shift($subscriptions_ids)) &&
                                                    (get_class($subscription) === 'WC_Subscription')
                                                ) {
                                                    if ($expiresAtString = $subscription->get_date('end') ?: $subscription->get_date('next_payment')) {
                                                        $expiresAt = new DateTime($expiresAtString);
                                                    }

                                                    // WCS => AudiencePlayer status translation: 'active' => 'active'; 'pending-cancel' => 'suspended'
                                                    $subscriptionStatus = $subscription->get_status();
                                                    $isValid = $expiresAt &&
                                                        $expiresAt > (new DateTime()) &&
                                                        in_array($subscriptionStatus, ['active', 'pending-cancel']);
                                                }

                                            } elseif (
                                                get_post_meta($wooCommerceOrderId, 'wps_sfw_order_has_subscription', true) &&
                                                ($wordpressSubscriptionId = get_post_meta($wooCommerceOrderId, 'wps_subscription_id', true))
                                            ) {
                                                // ### Plugin: "WPSWINGS SUBSCRIPTIONS FOR WOOCOMMERCE:

                                                $logProperties['woocommerce_subscription_plugin'] = 'wpswings-subscriptions-for-woocommerce';
                                                $logProperties['wordpress_subscription_id'] = $wordpressSubscriptionId;

                                                if ($wpSwingsNextPaymentDateTimeStamp = intval(get_post_meta($wordpressSubscriptionId, 'wps_next_payment_date', true))) {
                                                    $expiresAt = (new DateTime())->setTimestamp($wpSwingsNextPaymentDateTimeStamp);
                                                }

                                                // WCS => AudiencePlayer status translation: 'active' => 'active';
                                                $subscriptionStatus = get_post_meta($wordpressSubscriptionId, 'wps_subscription_status', true);
                                                $isValid = $expiresAt &&
                                                    $expiresAt > (new DateTime()) &&
                                                    in_array($subscriptionStatus, ['active']);
                                            }

                                            $entitlementAction = $isValid ? Globals::ENTITLEMENT_ACTION_FULFIL : Globals::ENTITLEMENT_ACTION_REVOKE;

                                            $logProperties['expires_at'] = $expiresAt;
                                            $logProperties['wordpress_subscription_is_valid'] = $isValid;
                                            $logProperties['wordpress_subscription_status'] = $subscriptionStatus ?? null;
                                            $logProperties['entitlement_action'] = $entitlementAction;

                                            $ret = $this->syncWordpressUserSubscriptionEntitlementToAudiencePlayer(
                                                $wordpressUserId,
                                                $audiencePlayerResourceId,
                                                $entitlementAction,
                                                $expiresAt
                                            );
                                            $isOrderUpdated = true;

                                        } elseif ($key === 'audienceplayer_product_id') {

                                            // https://woocommerce.com/document/managing-orders/
                                            $productOrderStatus = $wooCommerceOrder->get_status();
                                            $isValid = in_array($productOrderStatus, ['processing', 'completed']);
                                            $entitlementAction = $isValid ? Globals::ENTITLEMENT_ACTION_FULFIL : Globals::ENTITLEMENT_ACTION_REVOKE;

                                            $logProperties['audienceplayer_product_id'] = $audiencePlayerResourceId;
                                            $logProperties['wordpress_product_order_is_valid'] = $isValid;
                                            $logProperties['wordpress_product_order_status'] = $productOrderStatus;
                                            $logProperties['entitlement_action'] = $entitlementAction;

                                            $ret = $this->syncWordpressUserProductEntitlementToAudiencePlayer(
                                                $wordpressUserId,
                                                $audiencePlayerResourceId,
                                                $entitlementAction
                                            );
                                            $isOrderUpdated = true;

                                        } else {
                                            // order line-item is not tagged with an AudiencePlayer resource: Allow pass-through
                                            $ret = true;
                                        }
                                    }
                                }
                            }
                        }
                    } else {
                        $logProperties['msg'] = 'missing wordpress user id for given woocommerce order';
                    }
                } else {
                    $logProperties['msg'] = 'WooCommerce order id could not be fetched';
                }
            } else {
                $logProperties['msg'] = 'WooCommerce is likely not installed or is inactive';
            }

        } catch (Exception $e) {
            $ret = false;
            $logProperties['error_msg'] = $e->getMessage();
            $logProperties['error_code'] = $e->getCode();
        }

        if (false === $ret) {
            // Log exception
            $this->writeLog(
                Constants::LOG_LEVEL_SYSTEM_ERROR,
                'resource.integration.sync-woocommerce-order-to-audienceplayer.fail',
                'Error occurred while synchronising a woocommerce order for user for Wordpress user [#' . strval($wordpressUserId ?? '') . ']',
                array_merge(['wordpress_user_id' => $wordpressUserId ?? null], $logProperties)
            );
        } elseif ($isOrderUpdated) {
            // Log sync
            $this->writeLog(
                Constants::LOG_LEVEL_INFO,
                'resource.integration.sync-woocommerce-order-to-audienceplayer.success',
                'Successfully synchronising a woocommerce order with entitlement action "' . ($entitlementAction ?? '') . '" for user for Wordpress user [#' . strval($wordpressUserId ?? '') . ']',
                array_merge(['wordpress_user_id' => $wordpressUserId ?? null], $logProperties)
            );
        }

        return $ret;
    }

    public function syncWooCommerceSubscription($wooCommerceSubscription): bool
    {
        try {

            if (($lastOrder = $wooCommerceSubscription->get_last_order()) && $lastOrder->id) {
                return $this->syncWooCommerceOrderToAudiencePlayer($lastOrder->id);
            } else {
                return true;
            }

        } catch (Exception $e) {
            return false;
        }
    }

    public function syncWpSwingsSubscription($wpSwingsSubscriptionId): bool
    {
        try {

            if ($wooCommerceOrderId = get_post_meta(intval($wpSwingsSubscriptionId), 'wps_parent_order', true)) {
                return $this->syncWooCommerceOrderToAudiencePlayer($wooCommerceOrderId);
            } else {
                return true;
            }

        } catch (Exception $e) {
            return false;
        }
    }

    // ### INTERNAL HELPERS ###

    /**
     * @return bool
     */
    protected function isSyncWooCommerceEntitlements(): bool
    {
        return $this->helper->typeCastValue($this->loadConfig('is_synchronise_woocommerce_entitlements') ?: false, 'bool');
    }

    /**
     * @param $self
     * @return void
     */
    protected function deferIntegrationWooCommerceActions($self): void
    {
        if ($this->isSyncWooCommerceEntitlements()) {

            $wooCommerceCallback = function ($wooCommerceOrderId) use ($self) {

                try {
                    return $self->syncWooCommerceOrderToAudiencePlayer($wooCommerceOrderId);
                } catch (\Exception $e) {
                    // WooCommerce order could not be parsed
                    $this->writeLog(
                        Constants::LOG_LEVEL_SYSTEM_ERROR,
                        'resource.bootstrap.action-woocommerce-order-status-change',
                        'Error occurred while parsing a woocommerce order or order-id',
                        ['woocommerce_order_id' => $wooCommerceOrderId, 'error_msg' => $e->getMessage(), 'error_code' => $e->getCode()]
                    );
                }

                return null;
            };

            // WOOCOMMERCE

            // "woocommerce_order_status_changed": Runs immediately after an order has received a status change.
            \add_action('woocommerce_order_status_changed', $wooCommerceCallback, 200, 1);

            // WOOCOMMERCE SUBSCRIPTIONS

            // "woocommerce_subscription_status_updated": Runs when status on any WC order has changed.
            \add_action('woocommerce_subscription_status_updated', function ($subscription) use ($self) {
                return $self->syncWooCommerceSubscription($subscription, 200, 1);
            });

            // WPSWINGS SUBSCRIPTIONS

            // "wps_sfw_cancel_failed_susbcription/wps_sfw_expire_subscription_scheduler":
            // Run when subscription status has changed (Note typo in source code "susbcription"!).
            \add_action('wps_sfw_cancel_failed_susbcription', function ($result, $order_id, $subscription_id) use ($self) {
                return $self->syncWpSwingsSubscription($subscription_id);
            }, 200, 1);
            \add_action('wps_sfw_expire_subscription_scheduler', function ($subscription_id) use ($self) {
                return $self->syncWpSwingsSubscription($subscription_id);
            }, 200, 1);
            \add_action('wps_sfw_subscription_cancel', function ($subscription_id) use ($self) {
                return $self->syncWpSwingsSubscription($subscription_id);
            }, 200, 1);

        }
    }

}
