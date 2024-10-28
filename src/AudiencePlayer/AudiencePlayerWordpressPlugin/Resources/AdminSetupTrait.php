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

use AudiencePlayer\AudiencePlayerApiClient\AudiencePlayerApiClient;
use AudiencePlayer\AudiencePlayerWordpressPlugin\Config\Constants;

trait AdminSetupTrait
{
    public function setUp()
    {
        // ### Main plugin setup
        $this->setRootMenuPage(Constants::ROOT_MENU_LABEL, Constants::ROOT_MENU_SVG_ICON, Constants::ROOT_MENU_POSITION);

        $this->addSubMenuItems(
            [
                'title' => __(Constants::SECTION_CONFIGURATION_MENU_TITLE, 'audienceplayer'),
                'page_slug' => Constants::SECTION_CONFIGURATION_PAGE_SLUG,
            ],
            [
                'title' => __(Constants::SECTION_HELP_MENU_TITLE, 'audienceplayer'),
                'page_slug' => Constants::SECTION_HELP_PAGE_SLUG,
            ],
            [
                'title' => __(Constants::SECTION_LOGS_MENU_TITLE, 'audienceplayer'),
                'page_slug' => Constants::SECTION_LOGS_PAGE_SLUG,
            ]
        );
        $this->setPageHeadingTabsVisibility(false);

        $this->addInPageTabs(
            Constants::SECTION_CONFIGURATION_PAGE_SLUG,    // set the target page slug so that the 'page_slug' key can be omitted from the next continuing in-page tab arrays.
            [
                'tab_slug' => Constants::SECTION_CONFIGURATION_TAB_API_PAGE_SLUG,    // avoid hyphen(dash), dots, and white spaces
                'title' => __(Constants::SECTION_CONFIGURATION_TAB_API_TITLE, 'audienceplayer'),
            ],
            [
                'tab_slug' => Constants::SECTION_CONFIGURATION_TAB_SHORTCODES_PAGE_SLUG,    // avoid hyphen(dash), dots, and white spaces
                'title' => __(Constants::SECTION_CONFIGURATION_TAB_SHORTCODES_TITLE, 'audienceplayer'),
            ],
            [
                'tab_slug' => Constants::SECTION_CONFIGURATION_TAB_INTEGRATIONS_PAGE_SLUG,    // avoid hyphen(dash), dots, and white spaces
                'title' => __(Constants::SECTION_CONFIGURATION_TAB_INTEGRATIONS_TITLE, 'audienceplayer'),
            ],
            [
                'tab_slug' => Constants::SECTION_CONFIGURATION_TAB_DEBUG_PAGE_SLUG,    // avoid hyphen(dash), dots, and white spaces
                'title' => __(Constants::SECTION_CONFIGURATION_TAB_DEBUG_TITLE, 'audienceplayer'),
            ]
        );

        $this->addInPageTabs(
            Constants::SECTION_HELP_PAGE_SLUG,    // set the target page slug so that the 'page_slug' key can be omitted from the next continuing in-page tab arrays.
            [
                'tab_slug' => Constants::SECTION_HELP_TAB_GENERAL_PAGE_SLUG,    // avoid hyphen(dash), dots, and white spaces
                'title' => __(Constants::SECTION_HELP_TAB_GENERAL_TITLE, 'audienceplayer'),
            ],
            [
                'tab_slug' => Constants::SECTION_HELP_TAB_USER_SYNC_PAGE_SLUG,    // avoid hyphen(dash), dots, and white spaces
                'title' => __(Constants::SECTION_HELP_TAB_USER_SYNC_TITLE, 'audienceplayer'),
            ],
            [
                'tab_slug' => Constants::SECTION_HELP_TAB_SHORTCODES_PAGE_SLUG,    // avoid hyphen(dash), dots, and white spaces
                'title' => __(Constants::SECTION_HELP_TAB_SHORTCODES_TITLE, 'audienceplayer'),
            ],
            [
                'tab_slug' => Constants::SECTION_HELP_TAB_CODE_EXAMPLES_PAGE_SLUG,    // avoid hyphen(dash), dots, and white spaces
                'title' => __(Constants::SECTION_HELP_TAB_CODE_EXAMPLES_TITLE, 'audienceplayer'),
            ],
            [
                'tab_slug' => Constants::SECTION_HELP_TAB_RELEASE_NOTES_PAGE_SLUG,    // avoid hyphen(dash), dots, and white spaces
                'title' => __(Constants::SECTION_HELP_TAB_RELEASE_NOTES_TITLE, 'audienceplayer'),
            ],
            [
                'tab_slug' => Constants::SECTION_HELP_TAB_LICENSE_PAGE_SLUG,    // avoid hyphen(dash), dots, and white spaces
                'title' => __(Constants::SECTION_HELP_TAB_LICENSE_TITLE, 'audienceplayer'),
            ]
        );

        $this->addInPageTabs(
            Constants::SECTION_LOGS_PAGE_SLUG,    // set the target page slug so that the 'page_slug' key can be omitted from the next continuing in-page tab arrays.
            [
                'tab_slug' => Constants::SECTION_LOGS_TAB_OVERVIEW_PAGE_SLUG,    // avoid hyphen(dash), dots, and white spaces
                'title' => __(Constants::SECTION_LOGS_TAB_OVERVIEW_TITLE, 'audienceplayer'),
            ]
        );

        $this->setInPageTabTag('h2');
    }

    // ### SETUP CONFIG SECTION ########################################################################################

    // TAB API

    public function content_audienceplayer_config_tab_api($content)
    {
        return $this->helper->populateTemplateString(
                file_get_contents(self::fetchPluginPath('/static/html/admin_config_api.html')),
                $this->fetchTemplateVariables()
            )
            . $content;
    }

    public function load_audienceplayer_config_tab_api()
    {
        $this->addSettingSections(
            Constants::SECTION_CONFIGURATION_TAB_API_PAGE_SLUG,
            [
                'section_id' => Constants::CONFIG_NAMESPACE_API,
                'page_slug' => Constants::SECTION_CONFIGURATION_PAGE_SLUG,
                'tab_slug' => Constants::SECTION_CONFIGURATION_TAB_API_PAGE_SLUG,    // avoid hyphen(dash), dots, and white spaces
            ]
        );

        $this->addSettingFields(
            Constants::CONFIG_NAMESPACE_API,
            [
                'field_id' => 'project_id',
                'type' => 'text',
                'title' => __('AudiencePlayer project id', 'audienceplayer'),
                'description' => __('The ID of your AudiencePlayer account', 'audienceplayer'),
            ],
            [
                'field_id' => 'api_base_url',
                'type' => 'text',
                'title' => __('AudiencePlayer base url', 'audienceplayer'),
                'description' => __('The AudiencePlayer base url (default: https://api.audienceplayer.com)', 'audienceplayer'),
            ],
            [
                'field_id' => 'oauth_client_id',
                'type' => 'text',
                'title' => __('OAuth client ID', 'audienceplayer'),
                'description' => __('The provided client ID', 'audienceplayer'),
            ],
            [
                'field_id' => 'oauth_client_secret',
                'type' => 'text',
                'title' => __('OAuth client secret', 'audienceplayer'),
                'description' => __('The provided client secret', 'audienceplayer'),
            ],
            [
                'field_id' => 'payment_provider_id',
                'type' => 'text',
                'title' => __('Payment provider ID', 'audienceplayer'),
                'description' => __('The payment provider ID (leave empty if not applicable)', 'audienceplayer'),
            ],
            [
                'field_id' => 'is_synchronise_user_accounts',
                'title' => __('Synchronise user accounts', 'audienceplayer'),
                'type' => 'radio',
                'label' => [
                    1 => '<span class="audienceplayer-admin-warning">' . __('On', 'audienceplayer') . '</span>',
                    0 => __('Off', 'audienceplayer'),
                ],
                'default' => 1,
                'description' => __('Synchronise Wordpress user accounts with AudiencePlayer (required if you intend to use AudiencePlayer Users/Subscriptions/Products!)', 'audienceplayer') . '<br/><br/>' .
                    '<span class="audienceplayer-admin-warning">' .
                    '<em>' . __('WARNING: When set to "ON", all user actions taken such as deleting a user in Wordpress, will immediately be applied to the AudiencePlayer backend!!! Keep this in mind especially while still testing/developing in your Wordpress environment.', 'audienceplayer') . '</em>' .
                    '<br/><em>' . __('For more information please see the help section', 'audienceplayer') . ' <a href="?page=' . Constants::SECTION_HELP_PAGE_SLUG . '&tab=' . Constants::SECTION_HELP_TAB_USER_SYNC_PAGE_SLUG . '">' . Constants::SECTION_HELP_TAB_USER_SYNC_TITLE . '</a>.</em>' .
                    '</span>',
            ],
            [
                'field_id' => 'submit_button',
                'type' => 'submit',
            ]
        );
    }

    public function validation_audienceplayer_config_tab_api($aInputs, $aOldInputs, $oFactory, $aSubmitInfo)
    {
        $isValid = true;
        $errors = [];
        $inputs = $aInputs[Constants::CONFIG_NAMESPACE_API] ?? [];
        $errorMsg = __('There was something wrong with your input.', 'audienceplayer');

        if (isset($inputs['api_base_url'])) {

            if (filter_var($inputs['api_base_url'], FILTER_VALIDATE_URL)) {

                if (substr($inputs['api_base_url'], 0, 8) === "https://") {
                    $inputs['api_base_url'] = rtrim($inputs['api_base_url'], '/');
                } else {
                    $isValid = false;
                    $errors[Constants::CONFIG_NAMESPACE_API]['api_base_url'] = __('The base url must start with https://', 'audienceplayer') . ' ' . $inputs['api_base_url'];
                }

            } else {
                $isValid = false;
                $errors[Constants::CONFIG_NAMESPACE_API]['api_base_url'] = __('The value must be a valid url, starting with "https://":', 'audienceplayer') . ' ' . $inputs['api_base_url'];
            }
        }

        if (isset($inputs['project_id']) && !is_numeric($inputs['project_id'])) {
            $isValid = false;
            $errors[Constants::CONFIG_NAMESPACE_API]['project_id'] = __('The value must be numeric:', 'audienceplayer') . ' ' . $inputs['project_id'];
        }

        if (isset($inputs['oauth_client_id']) && !is_numeric($inputs['oauth_client_id'])) {
            $isValid = false;
            $errors[Constants::CONFIG_NAMESPACE_API]['oauth_client_id'] = __('The value must be numeric:', 'audienceplayer') . ' ' . $inputs['oauth_client_id'];
        }

        if (isset($inputs['oauth_client_secret']) && !ctype_alnum($inputs['oauth_client_secret'])) {
            $isValid = false;
            $errors[Constants::CONFIG_NAMESPACE_API]['oauth_client_secret'] = __('The value must be alpha-numeric:', 'audienceplayer') . ' ' . $inputs['oauth_client_secret'];
        }

        if (isset($inputs['payment_provider_id']) && !(is_numeric($inputs['payment_provider_id']) || trim($inputs['payment_provider_id']) === '')) {
            $isValid = false;
            $errors[Constants::CONFIG_NAMESPACE_API]['payment_provider_id'] = __('The value must be numeric:', 'audienceplayer') . ' ' . $inputs['payment_provider_id'];
        }

        if ($isValid) {

            try {

                $result = AudiencePlayerApiClient::init(
                    $inputs['oauth_client_id'],
                    $inputs['oauth_client_secret'],
                    $inputs['project_id'],
                    $inputs['api_base_url']
                )->query->ClientUser(1)->execute();

                if ($result->getFirstErrorCode() === 401) {
                    $isValid = false;
                    $errorMsg = __('Error, could not connect to the AudiencePlayer API with the provided credentials!', 'audienceplayer');
                }

            } catch (\Exception $e) {
                $isValid = false;
                $errorMsg = __('Error, could not connect to the AudiencePlayer API with the provided credentials!', 'audienceplayer');
            }

        }

        // An invalid value is found.
        if (false === $isValid) {

            // Set the error array for the input fields.
            $this->setFieldErrors($errors);

            $this->setSettingNotice($errorMsg);
            return $aOldInputs;

        }

        // The return values will be saved in the database.
        return $aInputs;
    }

    // TAB SHORTCODES

    public function content_audienceplayer_config_tab_shortcodes($content)
    {
        return $this->helper->populateTemplateString(
                file_get_contents(self::fetchPluginPath('/static/html/admin_config_shortcodes.html')),
                $this->fetchTemplateVariables()
            )
            . $content;
    }

    public function load_audienceplayer_config_tab_shortcodes()
    {
        $this->addSettingSections(
            Constants::SECTION_CONFIGURATION_TAB_SHORTCODES_PAGE_SLUG,
            [
                'title' => __('Generic Article Detail Pages | [audienceplayer_article_detail]', 'audienceplayer'),
                'description' => 'For each <strong>Article type</strong>, a generic Article detail page can be dynamically created based on shortcode [audienceplayer_article_detail]. ' .
                    'Here you can configure via which base url-slug these pages can be reached (e.g. in Article carousels and grids). ' .
                    'See help section <a href="?page=' . Constants::SECTION_HELP_PAGE_SLUG . '&tab=' . Constants::SECTION_HELP_TAB_SHORTCODES_PAGE_SLUG .
                    '">Shortcodes</a> for more information on the template location should you wish to customise it.<br/>' .
                    'N.B. If you choose to use the generic pages then please ensure that an option other than "plain" is selected in your ' .
                    '<a href="' . get_admin_url() . 'options-permalink.php">permalink settings</a> (typically "option Post name" is expected).',
                'section_id' => Constants::CONFIG_NAMESPACE_SHORTCODES . '_1',
                'page_slug' => Constants::SECTION_CONFIGURATION_PAGE_SLUG,
                'tab_slug' => Constants::SECTION_CONFIGURATION_TAB_SHORTCODES_PAGE_SLUG,    // avoid hyphen(dash), dots, and white spaces
            ],
            [
                'title' => __('Article carousels and grids | [audienceplayer_article_carousel_grid]', 'audienceplayer'),
                'description' => '',
                'section_id' => Constants::CONFIG_NAMESPACE_SHORTCODES . '_2',
                'page_slug' => Constants::SECTION_CONFIGURATION_PAGE_SLUG,
                'tab_slug' => Constants::SECTION_CONFIGURATION_TAB_SHORTCODES_PAGE_SLUG,    // avoid hyphen(dash), dots, and white spaces
            ],
            [
                'title' => __('Save settings', 'audienceplayer'),
                'description' => '',
                'section_id' => Constants::CONFIG_NAMESPACE_SHORTCODES . '_3',
                'page_slug' => Constants::SECTION_CONFIGURATION_PAGE_SLUG,
                'tab_slug' => Constants::SECTION_CONFIGURATION_TAB_SHORTCODES_PAGE_SLUG,    // avoid hyphen(dash), dots, and white spaces
            ]
        );

        $this->addSettingFields(
            Constants::CONFIG_NAMESPACE_SHORTCODES . '_1',
            [
                'field_id' => 'article_detail_series_url_slug',
                'type' => 'text',
                'title' => __('Series url slug', 'audienceplayer'),
                'default' => 'series',
                'description' => __('The base url slug for the article detail page of articles with type "series". A given slug "series" will be mapped as:', 'audienceplayer') .
                    '<ul class="audienceplayer-admin-ul">' .
                    '<li>' . \home_url() . '/<strong>series</strong>/{{ article_url_slug }}}/</li>' .
                    '</ul>',
            ],
            [
                'field_id' => 'article_detail_episode_url_slug',
                'type' => 'text',
                'title' => __('Episode url slug', 'audienceplayer'),
                'default' => 'episode',
                'description' => __('The base url slug for the article detail page of articles with type "episode". A given slug "episodes" will be mapped as follows:', 'audienceplayer') .
                    '<ul class="audienceplayer-admin-ul">' .
                    '<li>' . \home_url() . '/..../<strong>episode</strong>/{{ article_url_slug }}</li>' .
                    '</ul>',
            ],
            [
                'field_id' => 'article_detail_film_url_slug',
                'type' => 'text',
                'title' => __('Film url slug', 'audienceplayer'),
                'default' => 'film',
                'description' => __('The base url slug for the article detail page of articles with type "film". A given slug "film" will be mapped as:', 'audienceplayer') .
                    '<ul class="audienceplayer-admin-ul">' .
                    '<li>' . \home_url() . '/<strong>film</strong>/{{ article_url_slug }}/</li>' .
                    '</ul>',
            ],
            [
                'field_id' => 'article_detail_video_url_slug',
                'type' => 'text',
                'title' => __('Video url slug', 'audienceplayer'),
                'default' => 'video',
                'description' => __('The base url slug for the article detail page of articles with type "video". A given slug "video" will be mapped as:', 'audienceplayer') .
                    '<ul class="audienceplayer-admin-ul">' .
                    '<li>' . \home_url() . '/<strong>video</strong>/{{ article_url_slug }}/</li>' .
                    '</ul>',
            ]
        );

        $this->addSettingFields(
            Constants::CONFIG_NAMESPACE_SHORTCODES . '_2',
            [
                'field_id' => 'article_carousel_grid_default_click_action',
                'title' => __('Default click action', 'audienceplayer'),
                'type' => 'radio',
                'label' => [
                    'modal' => __('Modal video player ("modal")', 'audienceplayer'),
                    'auto' => __('Dynamic Article detail page ("auto")', 'audienceplayer'),
                ],
                'default' => 'modal',
                'description' => __('After a click-action on an Article thumbnail in a carousel or grid, either the modal video player is displayed or ' .
                        'the user will be redirected to a dynamic Article detail page.', 'audienceplayer') . '<br/>' .
                    __('This setting can always be manually overwritten by defining a "click-action" argument in the shortcode itself: "modal" for the modal video player, "auto" for dynamic detail pages, or of course any other custom href-expression ', 'audienceplayer') .
                    '(see the help section <a href="?page=' . Constants::SECTION_HELP_PAGE_SLUG . '&tab=' . Constants::SECTION_HELP_TAB_SHORTCODES_PAGE_SLUG . '#audienceplayer_article_carousel">shortcodes > [audienceplayer_article_carousel]</a> for more info)',
            ]
        );

        $this->addSettingFields(
            Constants::CONFIG_NAMESPACE_SHORTCODES . '_3',
            [
                'field_id' => 'submit_button',
                'type' => 'submit',
            ]
        );

    }

    public function validation_audienceplayer_config_tab_shortcodes($aInputs, $aOldInputs, $oFactory, $aSubmitInfo)
    {
        $isValid = true;
        $errors = [];
        $errorMsg = __('There was something wrong with your input.', 'audienceplayer');

        $inputs = $aInputs[Constants::CONFIG_NAMESPACE_SHORTCODES . '_1'] ?? [];
        foreach ($inputs as $key => $input) {
            if (!trim($input)) {
                $errors[Constants::CONFIG_NAMESPACE_SHORTCODES . '_1'][$key] = __('The value may not be empty!', 'audienceplayer');
                $isValid = false;
            }
        }

        // An invalid value is found.
        if ($isValid) {

            $this->hydrateConfigVars($aInputs);
            $this->hydrateCustomRewriteRules(true);

        } else {

            // Set the error array for the input fields.
            $this->setFieldErrors($errors);

            $this->setSettingNotice($errorMsg);
            return $aOldInputs;

        }

        // The return values will be saved in the database.
        return $aInputs;
    }

    // TAB INTEGRATIONS

    public function content_audienceplayer_config_tab_integrations($content)
    {
        return $this->helper->populateTemplateString(
                file_get_contents(self::fetchPluginPath('/static/html/admin_config_integrations.html')),
                $this->fetchTemplateVariables()
            )
            . $content;
    }

    public function load_audienceplayer_config_tab_integrations()
    {
        $this->addSettingSections(
            Constants::SECTION_CONFIGURATION_TAB_INTEGRATIONS_PAGE_SLUG,
            [
                'section_id' => Constants::CONFIG_NAMESPACE_INTEGRATIONS,
                'page_slug' => Constants::SECTION_CONFIGURATION_PAGE_SLUG,
                'tab_slug' => Constants::SECTION_CONFIGURATION_TAB_INTEGRATIONS_PAGE_SLUG,    // avoid hyphen(dash), dots, and white spaces
            ]
        );

        $this->addSettingFields(
            Constants::CONFIG_NAMESPACE_INTEGRATIONS,
            [
                'field_id' => 'is_enable_chromecast',
                'title' => __('Google Chromecast', 'audienceplayer'),
                'type' => 'radio',
                'label' => [
                    'false' => __('Disable', 'audienceplayer'),
                    'true' => __('Enable', 'audienceplayer'),
                ],
                'default' => 'false',
                'description' => __('Enable the AudiencePlayer interface for Google Chromecast. ', 'audienceplayer') .
                    '<br/><br/>' .
                    '<ul class="audienceplayer-admin-ol">' .
                    '<li><em>' . __('In order use this feature, Chromecast must be included in your AudiencePlayer plan.', 'audienceplayer') . '</em></li>' .
                    '<li><em>' . __('When enabled, the Google Chromecast connection button is automatically added to the main navigation menu.', 'audienceplayer') . '</em></li>' .
                    '<li><em>' .
                    __('The Google Chromecast connection button is rendered based on the html template file "audienceplayer_chromecast_button.html", which can be modified/replaced similar to shortcode templates ', 'audienceplayer') .
                    '(' . __('see the help section for more information', 'audienceplayer') . ' <a href="?page=' . Constants::SECTION_HELP_PAGE_SLUG . '&tab=' . Constants::SECTION_HELP_TAB_SHORTCODES_PAGE_SLUG . '">shortcodes</a>).' .
                    __('If this html template file is left empty, the Chromecast button will not be added. You can then choose your own place within your own theme to position it.', 'audienceplayer') .
                    '</em></li>' .
                    '</ul>',
            ],
            [
                'field_id' => 'is_synchronise_woocommerce_entitlements',
                'title' => __('WooCommerce&nbsp;<sup class="audienceplayer-admin-warning">BETA</sup>', 'audienceplayer'),
                'type' => 'radio',
                'label' => [
                    'false' => __('Disable', 'audienceplayer'),
                    'true' => '<span class="audienceplayer-admin-warning">' . __('Enable', 'audienceplayer') . '</span>',
                ],
                'default' => 'false',
                'description' => __('Synchronise WooCommerce entitlements.') . '<br/>' .
                    __('Fulfilled and/or revoked orders for WooCommerce Products and Subscriptions are synchronised to AudiencePlayer.', 'audienceplayer') . '<br/><br/>' .
                    '<span class="audienceplayer-admin-warning">' .
                    __('Warning: This feature is currently in "Bèta", meaning the feature is currently being extensively tested and still under development. Use with care and at your own risk!', 'audienceplayer') .
                    '</span>' .
                    '<br/><br/>' .
                    '<ul class="audienceplayer-admin-ol">' .
                    '<li><em>' .
                    __('<strong>Products</strong>: Please add to your WooCommerce Product, a custom attribute "audienceplayer_product_id" with the value of your AudiencePlayer Product ID.', 'audienceplayer') . ' ' .
                    __('Supported are Products created by the default plugin <a href="https://wordpress.org/plugins/woocommerce/" target=""_blank">WooCommerce</a>.', 'audienceplayer') .
                    '</em></li>' .
                    $this->helper->populateTemplateString(
                        '<a href="{{STATIC_BASE_URL}}images/example-integration-woocommerce-product-custom-attribute.jpg" target="_blank"><img src="{{STATIC_BASE_URL}}images/example-integration-woocommerce-product-custom-attribute.jpg" border="0" class="audienceplayer-admin-screenshot"/></a><br/>',
                        $this->fetchTemplateVariables()
                    ) .
                    '<br/>' .
                    '<li><em>' .
                    __('<strong>Subscriptions</strong>: Please add to your WooCommerce Subscription, a custom attribute "audienceplayer_subscription_id" with the value of your AudiencePlayer Subscription ID.', 'audienceplayer') . ' ' .
                    __('Supported are Subscriptions created by plugins <a href="https://woocommerce.com/products/woocommerce-subscriptions/" target="_blank">WooCommerce Subscriptions</a> and <a href="https://wordpress.org/plugins/subscriptions-for-woocommerce/" target="_blank">WPSwings Subscriptions for WooCommerce</a>.', 'audienceplayer') .
                    '</em></li>' .
                    $this->helper->populateTemplateString(
                        '<a href="{{STATIC_BASE_URL}}images/example-integration-woocommerce-subscription-custom-attribute.jpg" target="_blank"><img src="{{STATIC_BASE_URL}}images/example-integration-woocommerce-subscription-custom-attribute.jpg" border="0" class="audienceplayer-admin-screenshot"/></a><br/>',
                        $this->fetchTemplateVariables()
                    ) .
                    '</ul>',
            ],

            Constants::CONFIG_NAMESPACE_INTEGRATIONS,
            [
                'field_id' => 'submit_button',
                'type' => 'submit',
            ]
        );
    }

    public function validation_audienceplayer_config_tab_integrations($aInputs, $aOldInputs, $oFactory, $aSubmitInfo)
    {
        $isValid = true;
        $errors = [];
        $errorMsg = __('There was something wrong with your input.', 'audienceplayer');

        $inputs = $aInputs[Constants::CONFIG_NAMESPACE_INTEGRATIONS] ?? [];
        foreach ($inputs as $key => $input) {
            if (!trim($input)) {
                $errors[Constants::CONFIG_NAMESPACE_INTEGRATIONS][$key] = __('The value may not be empty!', 'audienceplayer');
                $isValid = false;
            }
        }

        // An invalid value is found.
        if ($isValid) {

            $this->hydrateConfigVars($aInputs);
            $this->hydrateCustomRewriteRules(true);

        } else {

            // Set the error array for the input fields.
            $this->setFieldErrors($errors);

            $this->setSettingNotice($errorMsg);
            return $aOldInputs;

        }

        // The return values will be saved in the database.
        return $aInputs;
    }

    // TAB DEBUG

    public function content_audienceplayer_config_tab_debug($content)
    {
        return $this->helper->populateTemplateString(
                file_get_contents(self::fetchPluginPath('/static/html/admin_config_debug.html')),
                $this->fetchTemplateVariables()
            )
            . $content;
    }

    public function load_audienceplayer_config_tab_debug()
    {
        $this->addSettingSections(
            Constants::SECTION_CONFIGURATION_TAB_DEBUG_PAGE_SLUG,
            [
                'section_id' => Constants::CONFIG_NAMESPACE_DEBUG,
                'page_slug' => Constants::SECTION_CONFIGURATION_PAGE_SLUG,
                'tab_slug' => Constants::SECTION_CONFIGURATION_TAB_DEBUG_PAGE_SLUG,    // avoid hyphen(dash), dots, and white spaces
            ]
        );

        $this->addSettingFields(
            Constants::CONFIG_NAMESPACE_DEBUG,
            [
                'field_id' => 'is_enable_js_console_debug',
                'title' => __('JavaScript Console Debug', 'audienceplayer'),
                'type' => 'radio',
                'label' => [
                    'false' => __('Disable', 'audienceplayer'),
                    'true' => __('Enable', 'audienceplayer'),
                ],
                'default' => 'false',
                'description' => __('Enable JavaScript console debugging. ' .
                        'Debug information, particularly on shortcodes, will be pushed to the console log of the browser inspector, which is part of the browser\'s Development tools', 'audienceplayer') . ' (<a href="https://en.wikipedia.org/wiki/Web_development_tools" target="_blank">Development Tools</a>).<br/><br/>' .
                    '<span class="audienceplayer-admin-warning">' .
                    __('Warning: When activated, the debug information will be visible in the browser console for all users. Though in general sensitive information (like credentials) are never logged, use with care and at your own risk!', 'audienceplayer') .
                    '</span>',
            ],
            Constants::CONFIG_NAMESPACE_DEBUG,
            [
                'field_id' => 'submit_button',
                'type' => 'submit',
            ]
        );
    }

    public function validation_audienceplayer_config_tab_debug($aInputs, $aOldInputs, $oFactory, $aSubmitInfo)
    {
        $isValid = true;
        $errors = [];
        $errorMsg = __('There was something wrong with your input.', 'audienceplayer');

        $inputs = $aInputs[Constants::CONFIG_NAMESPACE_DEBUG] ?? [];
        foreach ($inputs as $key => $input) {
            if (!trim($input)) {
                $errors[Constants::CONFIG_NAMESPACE_DEBUG][$key] = __('The value may not be empty!', 'audienceplayer');
                $isValid = false;
            }
        }

        // An invalid value is found.
        if ($isValid) {

            $this->hydrateConfigVars($aInputs);
            $this->hydrateCustomRewriteRules(true);

        } else {

            // Set the error array for the input fields.
            $this->setFieldErrors($errors);

            $this->setSettingNotice($errorMsg);
            return $aOldInputs;

        }

        // The return values will be saved in the database.
        return $aInputs;
    }

    // ### SETUP HELP SECTION ##########################################################################################

    public function renderAdminHtmlText(string $string, array $variables = [])
    {
        return str_replace(array_keys($variables), array_values($variables), $string);
    }

    public function content_audienceplayer_help_tab_general($content)
    {
        return $this->helper->populateTemplateString(
                file_get_contents(self::fetchPluginPath('/static/html/admin_help_general.html')),
                $this->fetchTemplateVariables()
            )
            . $content;
    }

    public function content_audienceplayer_help_tab_user_sync($content)
    {
        return $this->helper->populateTemplateString(
                file_get_contents(self::fetchPluginPath('/static/html/admin_help_user_sync.html')),
                $this->fetchTemplateVariables()
            )
            . $content;
    }

    public function content_audienceplayer_help_tab_shortcodes($content)
    {
        return $this->helper->populateTemplateString(
                file_get_contents(self::fetchPluginPath('/static/html/admin_help_shortcodes.html')),
                $this->fetchTemplateVariables()
            )
            . $content;
    }

    public function content_audienceplayer_help_tab_code_examples($content)
    {
        return $this->helper->populateTemplateString(
                file_get_contents(self::fetchPluginPath('/static/html/admin_help_code_examples.html')),
                $this->fetchTemplateVariables()
            )
            . $content;
    }

    public function content_audienceplayer_help_tab_release_notes($content)
    {
        return $this->helper->populateTemplateString(
                file_get_contents(self::fetchPluginPath('/static/html/admin_help_release_notes.html')),
                $this->fetchTemplateVariables()
            )
            . $content;
    }

    public function content_audienceplayer_help_tab_license($content)
    {
        return $this->helper->populateTemplateString(
                file_get_contents(self::fetchPluginPath('/static/html/admin_help_license.html')),
                $this->fetchTemplateVariables()
            )
            . $content;
    }

    // ### SETUP LOGS SECTION ##########################################################################################

    public function content_audienceplayer_logs_tab_overview($content)
    {
        $logData = $this->fetchLogs();
        include self::fetchPluginPath('/static/html/admin_logs_overview.php');

        return $content;
    }

    // ### HELPERS

    private function fetchTemplateVariables()
    {
        return [
            'SECTION_CONFIGURATION_MENU_TITLE' => Constants::SECTION_CONFIGURATION_MENU_TITLE,
            'SECTION_CONFIGURATION_PAGE_SLUG' => Constants::SECTION_CONFIGURATION_PAGE_SLUG,
            'SECTION_CONFIGURATION_TAB_API_PAGE_SLUG' => Constants::SECTION_CONFIGURATION_TAB_API_PAGE_SLUG,
            'SECTION_CONFIGURATION_TAB_API_TITLE' => Constants::SECTION_CONFIGURATION_TAB_API_TITLE,
            'SECTION_CONFIGURATION_TAB_SHORTCODES_PAGE_SLUG' => Constants::SECTION_CONFIGURATION_TAB_SHORTCODES_PAGE_SLUG,
            'SECTION_CONFIGURATION_TAB_SHORTCODES_TITLE' => Constants::SECTION_CONFIGURATION_TAB_SHORTCODES_TITLE,
            'SECTION_CONFIGURATION_TAB_INTEGRATIONS_PAGE_SLUG' => Constants::SECTION_CONFIGURATION_TAB_INTEGRATIONS_PAGE_SLUG,
            'SECTION_CONFIGURATION_TAB_INTEGRATIONS_TITLE' => Constants::SECTION_CONFIGURATION_TAB_INTEGRATIONS_TITLE,
            'SECTION_HELP_MENU_TITLE' => Constants::SECTION_HELP_MENU_TITLE,
            'SECTION_HELP_PAGE_SLUG' => Constants::SECTION_HELP_PAGE_SLUG,
            'SECTION_HELP_TAB_CODE_EXAMPLES_PAGE_SLUG' => Constants::SECTION_HELP_TAB_CODE_EXAMPLES_PAGE_SLUG,
            'SECTION_HELP_TAB_CODE_EXAMPLES_TITLE' => Constants::SECTION_HELP_TAB_CODE_EXAMPLES_TITLE,
            'SECTION_HELP_TAB_SHORTCODES_PAGE_SLUG' => Constants::SECTION_HELP_TAB_SHORTCODES_PAGE_SLUG,
            'SECTION_HELP_TAB_SHORTCODES_TITLE' => Constants::SECTION_HELP_TAB_SHORTCODES_TITLE,
            'SECTION_HELP_TAB_USER_SYNC_PAGE_SLUG' => Constants::SECTION_HELP_TAB_USER_SYNC_PAGE_SLUG,
            'SECTION_HELP_TAB_USER_SYNC_TITLE' => Constants::SECTION_HELP_TAB_USER_SYNC_TITLE,
            'SECTION_LOGS_MENU_TITLE' => Constants::SECTION_LOGS_MENU_TITLE,
            'SECTION_LOGS_PAGE_SLUG' => Constants::SECTION_LOGS_PAGE_SLUG,
            'SECTION_LOGS_TAB_OVERVIEW_PAGE_SLUG' => Constants::SECTION_LOGS_TAB_OVERVIEW_PAGE_SLUG,
            'SECTION_LOGS_TAB_OVERVIEW_TITLE' => Constants::SECTION_LOGS_TAB_OVERVIEW_TITLE,
            'SECTION_WP_SETTINGS_PERMALINKS' => get_admin_url() . 'options-permalink.php',
            'STATIC_BASE_URL' => self::fetchPluginUrl('static/'),
            'YEAR' => date('Y'),
        ];
    }

}
