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

namespace AudiencePlayer\AudiencePlayerWordpressPlugin;

use AudiencePlayer\AudiencePlayerApiClient\AudiencePlayerApiClient;
use AudiencePlayer\AudiencePlayerApiClient\Resources\Globals;
use AudiencePlayer\AudiencePlayerWordpressPlugin\Config\Constants;
use AudiencePlayer\AudiencePlayerWordpressPlugin\Resources\AdminSetupTrait;
use AudiencePlayer\AudiencePlayerWordpressPlugin\Resources\AjaxTrait;
use AudiencePlayer\AudiencePlayerWordpressPlugin\Resources\BootstrapTrait;
use AudiencePlayer\AudiencePlayerWordpressPlugin\Resources\CacheTrait;
use AudiencePlayer\AudiencePlayerWordpressPlugin\Resources\DebugTrait;
use AudiencePlayer\AudiencePlayerWordpressPlugin\Resources\Helper;
use AudiencePlayer\AudiencePlayerWordpressPlugin\Resources\IntegrationWooCommerceTrait;
use AudiencePlayer\AudiencePlayerWordpressPlugin\Resources\LogTrait;
use AudiencePlayer\AudiencePlayerWordpressPlugin\Resources\MaintenanceTrait;
use AudiencePlayer\AudiencePlayerWordpressPlugin\Resources\ShortCodeTrait;
use AudiencePlayer\AudiencePlayerWordpressPlugin\Resources\UserSyncTrait;
use AudiencePlayer_AdminPageFramework;

class AudiencePlayerWordpressPlugin extends AudiencePlayer_AdminPageFramework
{
    use AdminSetupTrait;
    use AjaxTrait;
    use BootstrapTrait;
    use CacheTrait;
    use LogTrait;
    use MaintenanceTrait;
    use ShortCodeTrait;
    use UserSyncTrait;
    use DebugTrait;
    use IntegrationWooCommerceTrait;

    protected static $instance;
    protected static $pluginPath, $pluginFolderName, $pluginUrl;
    protected $helper;
    protected $api;
    protected $translations;
    protected $genericPageArgs;
    protected $currentAudiencePlayerUserObject;

    /**
     * AudiencePlayerWordpressPlugin constructor.
     * @param null $isOptionKey
     * @param null $sCallerPath
     * @param string $sCapability
     * @param string $sTextDomain
     */
    public function __construct($isOptionKey = null, $sCallerPath = null, string $sCapability = 'manage_options', string $sTextDomain = Constants::TRANSLATION_TEXT_DOMAIN)
    {
        parent::__construct($isOptionKey, $sCallerPath, $sCapability, $sTextDomain);

        $this->helper = new Helper();
    }

    /**
     * @return AudiencePlayerWordpressPlugin
     */
    public static function init()
    {
        if (!self::$instance) {

            self::$instance = new AudiencePlayerWordpressPlugin();

            self::$instance->initAudiencePlayerApiClient();

            self::$instance->initAudiencePlayerApiProjectConfig();

            // hydrate the default translations, to be later overwritten in audienceplayer-core-translations.php
            self::$instance->setTranslations(self::$instance->fetchDefaultTranslations());
        }

        return self::$instance;
    }


    /**
     * @return string
     */
    public static function fetchPluginVersion()
    {
        return Constants::PLUGIN_VERSION;
    }

    /**
     * @param string $relativePath
     * @return string
     */
    public static function fetchPluginPath(string $relativePath = '')
    {
        if (!self::$pluginPath) {
            self::$pluginPath = dirname(__DIR__, 3) . '/';
        }

        return self::$pluginPath . ltrim($relativePath, '/');
    }

    /**
     * @param string $file
     * @return string
     */
    public static function fetchPluginFile($file = 'audienceplayer.php')
    {
        return self::fetchPluginPath($file);
    }

    /**
     * @return string
     */
    public static function fetchPluginFolderName()
    {
        if (!self::$pluginFolderName) {
            $pluginPathComponents = explode(DIRECTORY_SEPARATOR, rtrim(self::fetchPluginPath(), '/'));
            self::$pluginFolderName = end($pluginPathComponents) ?: 'audienceplayer';
        }

        return self::$pluginFolderName;
    }

    /**
     * @param string $relativePath
     * @return string
     */
    public static function fetchPluginUrl(string $relativePath = '')
    {
        if (!self::$pluginUrl) {
            self::$pluginUrl = plugins_url() . '/' . self::fetchPluginFolderName() . '/';
        }

        return self::$pluginUrl . ltrim($relativePath, '/');
    }

    public static function fetchPluginDomain()
    {
        $parts = parse_url(\home_url());
        return $parts['host'] ?? 'domain';
    }


    public static function fetchPluginCacheString()
    {
        return self::fetchPluginVersion() . '-' . self::fetchPluginDomain();
    }

    /**
     * @TODO: force return type ": AudiencePlayerApiClient" as soon as PHP7.3 is minimum supported version
     * @return AudiencePlayerApiClient
     */
    public function api()
    {
        return $this->api;
    }

    /**
     * @return Helper
     */
    public function helper()
    {
        return $this->helper;
    }

    /**
     * @return bool
     */
    public function isPublicJsConsoleDebug()
    {
        // @TODO: only allow over Https and abstract into protected method
        return
            $this->helper->typeCastValue($this->loadConfig('is_enable_js_console_debug') ?: false, 'bool') ||
            $this->validateOauthQueryStringCredentials();
    }

    /**
     * @return bool
     */
    public function isAuthenticated()
    {
        return $this->fetchUserBearerToken() ? true : false;
    }

    public function isOrderValidated($orderId)
    {
        $ret = false;

        if (isset($this->validatedOrders[intval($orderId)])) {
            $ret = boolval($this->validatedOrders[intval($orderId)]->validation_result ?? false);
        }

        return $ret;
    }

    public function fetchValidatedOrder($orderId)
    {
        $ret = (object)[];

        if (isset($this->validatedOrders[intval($orderId)])) {
            $ret = $this->validatedOrders[intval($orderId)];
        }

        return $ret;
    }

    /**
     * @return string
     */
    public function fetchApiBaseUrl()
    {
        return rtrim(strval($this->loadConfig('api_base_url') ?: ''), '/');
    }

    /**
     * @return string
     */
    public function fetchProjectId()
    {
        return intval($this->loadConfig('project_id') ?? 0);
    }

    /**
     * @return string
     */
    public function fetchPaymentProviderId()
    {
        return intval($this->loadConfig('payment_provider_id') ?? 0);
    }

    /**
     * @return string
     */
    public function fetchChromecastReceiverAppId()
    {
        if ($this->helper->typeCastValue($this->loadConfig('is_enable_chromecast') ?: false, 'bool')) {
            $platform = $this->loadProjectConfig('platform');
            return trim(strval($platform->chromecast_receiver_app_id ?? ''));
        } else {
            return '';
        }
    }

    /**
     * @param string $articleType
     * @return bool
     */
    public function isNomadicWatchingEnabled(string $articleType = 'film')
    {
        $key = in_array(strtolower($articleType), ['series', 'season', 'episode']) ? 'has_article_series_nomadics' : 'has_article_nomadics';
        return $this->helper->typeCastValue($this->loadProjectConfig($key) ?? true, 'bool');
    }

    /**
     * @return array|bool|float|int|string
     */
    public function isPlayerSpeedRatesEnabled()
    {
        $platform = $this->loadProjectConfig('platform');
        return $this->helper->typeCastValue($platform->is_support_player_playback_speed_change ?? true, 'bool');
    }

    /**
     * @return string
     */
    public function fetchUserBearerToken()
    {
        return $this->api() ?
            $this->api()->fetchBearerToken(Globals::OAUTH_ACCESS_AS_AGENT_USER) :
            '';
    }

    /**
     * @return string
     */
    public function fetchUserBearerTokenTtl()
    {
        return intval($this->bearerTokenTtl ?? 3600);
    }

    public function fetchCurrentAudiencePlayerUser($withProperties = ['id,name,email'], $isForceSync = false)
    {
        if ($this->fetchWordpressUserProperty(0, 'audienceplayer_user_id')) {

            if (
                $this->currentAudiencePlayerUserObject &&
                ($this->currentAudiencePlayerUserObject->user ?? null) &&
                $this->currentAudiencePlayerUserObject->properties === $withProperties
            ) {

                return $this->currentAudiencePlayerUserObject->user;

            } else {

                $result = $this->api->query->UserDetails()->properties($withProperties)->execute();

                if ($result->isSuccessful() && ($user = $result->getData(true))) {

                    $this->currentAudiencePlayerUserObject = (object)[
                        'user' => $user,
                        'properties' => $withProperties,
                    ];

                    return $this->currentAudiencePlayerUserObject->user;

                } elseif (
                    $isForceSync &&
                    $result->getFirstErrorCode() === Constants::STATUS_NOT_AUTHENTICATED_CODE &&
                    $this->syncAudiencePlayerUserAuthentication(\get_current_user_id(), [], false, true)
                ) {
                    return $this->fetchCurrentAudiencePlayerUser($withProperties, false);
                }
            }

        }

        return null;
    }

    /**
     * @return string
     */
    public function fetchCurrentUrl()
    {
        global $wp;
        return ($wp->query_string ?? null) ? \home_url() . '?' . $wp->query_string : \home_url();
    }

    /**
     * @param string $redirectUrl
     * @param array $withParams
     * @return string
     */
    public function fetchLoginUrl($redirectUrl = '', $withParams = [])
    {
        $url = \wp_login_url();

        if (true === $redirectUrl) {
            $redirectUrl = $this->fetchCurrentUrl();
        }

        if ($redirectUrl) {
            $withParams['redirect_to'] = urlencode($redirectUrl);
        }

        if ($withParams) {
            foreach ($withParams as $param => $value) {
                $url .= (false === strstr($url, '?') ? '?' : '&') . $param . '=' . $value;
            }
        }

        return $url;
    }

    public function fetchRemoteEmbedPlayerLibraryBaseUrl()
    {
        return rtrim(strval($this->loadConfig('embed_player_library_base_url') ?: 'https://static.audienceplayer.com/embed-player'), '/');
    }

    /**
     * @return string
     */
    public function fetchEmbedPlayerLibraryBaseUrl()
    {
        return rtrim(strval(self::fetchPluginUrl('/static/audienceplayer-embed-player') ?: $this->fetchRemoteEmbedPlayerLibraryBaseUrl()), '/');
    }

    /**
     * @param array $metas
     * @param string $key
     * @param null $fallbackValue
     * @return null
     */
    public function fetchMetaValueForKey(array $metas, string $key, $fallbackValue = null)
    {
        foreach ($metas as $meta) {

            if ($key === ($meta->key ?? null)) {
                return $meta->value ?? $fallbackValue;
            }
        }

        return $fallbackValue;
    }

    /**
     * @param array $images
     * @param string $aspectRatioProfile
     * @param int $fallbackIndex
     * @return mixed|null
     */
    public function fetchImageWithAspectRatio(array $images, string $aspectRatioProfile = '16x9', int $fallbackIndex = 0)
    {
        foreach ($images as $image) {

            if (($image->aspect_ratio_profile ?? null) === $aspectRatioProfile) {
                return $image;
            }
        }

        return $images[$fallbackIndex] ?? null;
    }

    /**
     * @param $value
     * @param bool $isIncludeTime
     * @param string $fallbackValue
     * @return false|string
     */
    public function parseDateTimeValue($value, $isIncludeTime = false, $fallbackValue = '-')
    {
        $format = $isIncludeTime ? 'Y-m-d H:i' : 'Y-m-d';
        return $value ? date($format, strtotime($value)) : $fallbackValue;
    }

    /**
     * @param string $action
     * @param bool $isCreateCallable
     * @return string
     */
    public function parseJsShortCodeAction(string $action, $isCreateCallable = true)
    {
        $ret = 'null';

        if ($action = trim($action)) {

            if ($url = $this->helper->validateAbsoluteOrRelativeUrl($action)) {

                $ret = $isCreateCallable ?
                    'function(data){window.location.href=\'' . $url . '\';}' :
                    '\'' . $this->helper->sanitiseJsString(urlencode($url)) . '\'';

            } elseif ($method = $this->helper->sluggifyString($action, '/[^A-Za-z0-9_]/i', true, false)) {

                $ret = $isCreateCallable ?
                    'function(data){' . $method . '();}' :
                    '\'' . $method . '\'';
            }

        }

        return $ret;
    }

    /**
     * @param string $action
     * @return string
     */
    public function executeJsShortCodeAction(string $action)
    {
        $ret = '';

        if ($url = $this->helper->validateAbsoluteOrRelativeUrl($action)) {
            $ret = 'window.location.href=\'' . $action . '\';';
        } elseif ($method = $this->helper->sluggifyString($action, '/[^A-Za-z0-9_]/i', true, false)) {
            $ret = $method . '();';
        }

        return $ret;
    }

    /**
     * @param $result
     * @param $ids
     * @param string $key
     * @return array
     */
    public function orderResultByIds($result, $ids, $key = 'id')
    {
        $ret = [];

        if ($ids) {
            foreach ($ids as $id) {

                foreach ($result as $item) {
                    if ($id === ($item->{$key} ?? $item[$key] ?? null)) {
                        array_push($ret, $item);
                    }
                }
            }
        } else {
            $ret = $result;
        }

        return $ret;
    }

    /**
     * @param $parameter
     * @return null
     */
    public function fetchQueryUrlSlugPart($parameter)
    {
        $arr = explode('/', $_SERVER['REQUEST_URI']);
        for ($i = 0; $i < count($arr); $i++) {
            if ($arr[$i] === $parameter) {
                return $arr[$i + 1] ?? null;
            }
        }

        return null;
    }

    /**
     * @return mixed
     */
    public function fetchGenericPageArgs()
    {
        return $this->genericPageArgs;
    }

    /**
     * @param $type
     * @return mixed
     */
    public function fetchArticleTypeBaseSlug($type)
    {
        return $this->loadConfig('article_detail_' . $type . '_url_slug') ?: $type;
    }


    public function hydrateProjectTranslations()
    {
        $languageTags = $this->loadProjectConfig('language_tags');
        $isEmpty = empty($this->translations);

        if ($languageTags) {
            foreach ($languageTags as $languageTag) {
                if ($isEmpty || isset($this->translations[$languageTag->key])) {
                    $this->translations[$languageTag->key] = $languageTag->value;
                }
            }
        }
    }

    /**
     * @param $key
     * @param null $value
     */
    public function setTranslations($key, $value = null)
    {
        if (is_array($key)) {
            foreach ($key as $k => $v) {
                $this->translations[$k] = $v;
            }
        } elseif (is_string($key)) {
            $this->translations[$key] = $value;
        }
    }

    /**
     * @param null $key
     * @param string $fallbackValue
     * @param array $data
     * @param bool $isRemoveEmptyTerms
     * @return array|string
     */
    public function fetchTranslations($key = null, $fallbackValue = '', $data = [], $isRemoveEmptyTerms = true)
    {
        if ($key) {
            return is_array($key) ?
                $this->helper->arrayOnly($this->translations, $key) :
                $this->helper->populateTemplateString($this->translations[$key] ?? $fallbackValue, $data, $isRemoveEmptyTerms);
        } else {
            $helper = $this->helper;
            return array_map(function ($value) use ($helper, $data, $isRemoveEmptyTerms) {
                return $helper->populateTemplateString($value, $data, $isRemoveEmptyTerms);
            }, $this->translations);
        }
    }

    /**
     * @return array
     */
    public function fetchDefaultTranslations()
    {
        return [
            'button_activate' => 'Activate device',
            'button_cancel' => 'Cancel',
            'button_close' => 'Close',
            'button_login' => 'Login',
            'button_logout' => 'Logout',
            'button_next' => 'Continue',
            'button_ok' => 'Ok',
            'button_payment_accept_complete' => 'Accept and complete',
            'button_play' => 'Play',
            'button_play_trailer' => 'Play trailer',
            'button_purchase_product' => 'Purchase',
            'button_redeem_voucher' => 'Validate',
            'button_retry' => 'Retry',
            'button_select_subscription' => 'Purchase',
            'button_signup_now' => 'Sign up',
            'button_switch_user_subscription' => 'Switch',
            'dialogue_activate_sms_notifications' => 'Activate SMS-notifications if available.',
            'dialogue_claim_device_explanation' => 'Enter the code displayed on your TV-screen and pair your device',
            'dialogue_device_confirm_unpairing' => 'Please confirm removing this paired device',
            'dialogue_device_not_unpaired' => 'Your paired device was NOT removed, please try again.',
            'dialogue_device_successfully_paired' => 'Your device was successfully paired to your account',
            'dialogue_device_successfully_unpaired' => 'Your paired device was successfully removed.',
            'dialogue_email_address_exists_conflict' => 'E-mail address could not be changed, likely an account with this e-mail address already exists! If this e-mail address is yours, try to reset your password.',
            'dialogue_error_occurred' => 'An error has occurred',
            'dialogue_error_phone_number_pairing_code_not_valid' => 'Error, the code is not valid.',
            'dialogue_information_renew_suspend_subscription' => 'Continue subscription renewal.',
            'dialogue_information_validate_voucher_code' => 'If you have a voucher code, you can claim it here.',
            'dialogue_not_optimised_for_current_browser' => 'Unfortunately this browser is not supported, please upgrade and/or switch to a different browser (e.g. Chrome or Safari)',
            'dialogue_notification_enabling_not_authorised' => 'Activating notifications is not allowed for your current account or subscription.',
            'dialogue_pairing_code_not_valid' => 'Pairing code is not valid',
            'dialogue_parental' => 'parental indication',
            'dialogue_parental_indication_12' => 'Potentially harmful up to 12 years',
            'dialogue_parental_indication_14' => 'Potentially harmful up to 14 years',
            'dialogue_parental_indication_16' => 'Potentially harmful up to 16 years',
            'dialogue_parental_indication_18' => 'Potentially harmful up to 18 years',
            'dialogue_parental_indication_6' => 'Potentially harmful up to 6 years',
            'dialogue_parental_indication_9' => 'Potentially harmful up to 9 years',
            'dialogue_parental_indication_A' => 'Anxiety',
            'dialogue_parental_indication_AL' => 'All ages',
            'dialogue_parental_indication_D' => 'Discrimination',
            'dialogue_parental_indication_G' => 'Violence',
            'dialogue_parental_indication_S' => 'Sex',
            'dialogue_parental_indication_T' => 'Coarse language',
            'dialogue_parental_indication_V' => 'Drugs en/or alcohol abuse',
            'dialogue_payment_method_add' => 'Add payment method.',
            'dialogue_payment_method_change' => 'Change payment method.',
            'dialogue_payment_method_change_cost_explanation' => 'Click "OK" to change your payment method, you will be charged a one-time fee of € 0.01. In case there are still outstanding payments, they will be settled as well.',
            'dialogue_payment_method_not_configured' => 'Payment method is not available.',
            'dialogue_payment_not_validated' => 'We have not (yet) been able to validate the payment.',
            'dialogue_payment_required_for_video' => 'Payment is required to view this content.',
            'dialogue_payment_successfully_completed' => 'Payment successfully validated.',
            'dialogue_phone_number_change_request' => 'Your phone number has not (yet) been verified. Press "continue" to receive a text message with verification code.',
            'dialogue_phone_number_verified' => 'Validation successfully completed, your settings have been saved.',
            'dialogue_phone_number_verify_via_text_message' => 'Enter the validation code you received.',
            'dialogue_product_purchased_without_payment' => 'Purchase successfully completed, payment not (yet) necessary.',
            'dialogue_subscription_already_valid' => 'User subscription is already valid.',
            'dialogue_switch_user_subscription' => 'Would you like to switch your subscription? Please select one of the subscriptions to switch to. On the date on which your current subscription will be renewed, you will switch to the new subscription of your choice.',
            'dialogue_user_not_authenticated' => 'User is not authenticated.',
            'dialogue_user_not_authorised' => 'User is not authorised.',
            'dialogue_user_not_synchronised' => 'User is not synchronised, please contact support.',
            'dialogue_user_subscription_will_switch_at_renewal' => '→ At the next renewal date, your subscription will switch to "{{ subscription_title }}"',
            'dialogue_video_not_available_in_region' => 'The content is not available in your current country or region.',
            'dialogue_voucher_code_invalid' => 'Voucher code is not valid.',
            'dialogue_voucher_code_not_applicable' => 'Voucher code is not valid or cannot be applied on current subscription.',
            'dialogue_voucher_code_applicable_subscription_unknown' => 'This voucher can only be applied to an already existing subscription or when purchasing a new subscription.',
            'form_toggle_off' => 'Off',
            'form_toggle_on' => 'On',
            'form_toggle_recurring_subscription_off' => 'Suspend',
            'form_toggle_recurring_subscription_on' => 'Renew',
            'form_validation_error_phone_number' => 'Phone number not valid, please use the format "+31612345678"',
            'form_validation_error_phone_number_fill_first' => 'Enter the phone number on which you\'d like to receive text messages.',
            'label_current_user_subscription' => 'Current',
            'label_date' => 'Date added',
            'label_date_next_invoice' => 'Next payment at',
            'label_id' => 'ID',
            'label_meta_director' => 'director',
            'label_meta_duration' => 'duration',
            'label_meta_year' => 'year',
            'label_name' => 'name',
            'label_order_table_amount' => 'amount',
            'label_order_table_date' => 'date',
            'label_order_table_product' => 'description',
            'label_order_table_status' => 'status',
            'label_orders' => 'Orders',
            'label_pair_device' => 'Pair a new device',
            'label_paired_devices' => 'Paired devices',
            'label_payment_method' => 'Payment method',
            'label_preferred_subscription_ribbon' => 'popular choice',
            'label_redeem_voucher_code' => 'Redeem voucher',
            'label_renew_subscription' => 'Renew subscription',
            'label_sms_notifications' => 'SMS notifications',
            'label_subscription' => 'Subscription',
            'label_time_unit_per_day' => '/ day',
            'label_time_unit_per_month' => '/ month',
            'label_time_unit_per_month_2' => '/ two months',
            'label_time_unit_per_month_3' => '/ quarter',
            'label_time_unit_per_month_6' => '/ half year',
            'label_time_unit_per_week' => '/ week',
            'label_time_unit_per_year' => '/ year',
            'label_valid_until' => 'Expires at',
            'link_switch_user_subscription' => 'Switch my subscription',
            'link_unpair_device' => 'remove',
        ];
    }

    /**
     * @return object
     */
    protected function assembleDefaultRawReturnObject(bool $status = false, string $message = null, $data = null)
    {
        return (object)[
            'status' => $status,
            'message' => $message,
            'data' => $data,
        ];
    }
}
