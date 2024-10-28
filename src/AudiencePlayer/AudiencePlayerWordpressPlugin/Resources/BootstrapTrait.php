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
use AudiencePlayer\AudiencePlayerWordpressPlugin\AudiencePlayerWordpressPlugin;
use AudiencePlayer\AudiencePlayerWordpressPlugin\Config\Constants;
use WP_Error;

trait BootstrapTrait
{
    protected $config = [];
    protected $configVars = [
        'project_id' => 'int',
        'api_base_url' => 'string',
        'oauth_client_id' => 'string',
        'oauth_client_secret' => 'string',
        'payment_provider_id' => 'int',
        'is_synchronise_user_accounts' => 'bool',
        'is_synchronise_woocommerce_entitlements' => 'bool',

        'article_detail_series_url_slug' => 'string',
        'article_detail_episode_url_slug' => 'string',
        'article_detail_film_url_slug' => 'string',
        'article_detail_video_url_slug' => 'string',
        'article_carousel_grid_default_click_action' => 'string',

    ];
    protected $projectConfig = [];

    private $deletedWordpressUserObject = null;
    private $newUserRegistrationPassword = null;
    private $isNewUserRegistrationRequest = false;
    private $isNewUserAuthenticationRequest = false;
    private $isNewUserAuthenticationSyncRequest = false;
    private $isChangeAttemptedOnPassword = false;
    private $isChangeAttemptedOnEmail = false;
    private $validatedOrders = [];

    protected static $articleDetailQsaConfigs = [
        'article_detail_episode_url_slug',
        'article_detail_series_url_slug',
        'article_detail_film_url_slug',
        'article_detail_video_url_slug',
    ];

    /**
     * @return void
     */
    public function registerWordpressActions(): void
    {
        $self = $this;

        // Plugin maintenance
        $this->deferPluginMaintenanceActions($self);

        // Main plugin bootstrap for frontend usage
        $this->deferPluginBootstrapActions($self);
        $this->deferAjaxApiActions($self);

        // Main actions for Wordpress Admin interface
        $this->deferAdminFormActions($self);
        $this->deferAdminLogActions($self);

        // Main actions for Wordpress Website interface
        $this->deferWebsiteActions($self);

        // Main actions for Wordpress user mutations
        $this->deferUserAuthenticationActions($self);
        $this->deferUserRegistrationActions($self);
        $this->deferUserProfileUpdateActions($self);
        $this->deferUserDeleteActions($self);

        // Main actions for external integration and/or plugins
        $this->deferIntegrationWooCommerceActions($self);

        // Load frontend shortcodes
        $this->initShortCodes($self);
    }

    // ### INTERNAL DEFERENCE HELPERS

    /**
     * @param $self
     * @return void
     */
    protected function deferPluginMaintenanceActions($self): void
    {
        // runs after plugin is activated/updated
        \register_activation_hook($self::fetchPluginFile(), function () use ($self) {
            $self->checkStatus(true);
        });

        // runs always after plugin is loaded (admin or frontend)
        \add_action('plugins_loaded', function () use ($self) {
            $self->checkStatus(false);
        });

        // runs in the wp-admin > plugins overview and adds additional action links
        \add_filter('plugin_action_links', function ($pluginActionsLinks, $pluginFile) {

            if ($pluginFile === \plugin_basename(self::fetchPluginFile())) {

                array_unshift(
                    $pluginActionsLinks,
                    '<a href="' . \get_bloginfo('wpurl') . '/wp-admin/admin.php?page=' . Constants::SECTION_CONFIGURATION_PAGE_SLUG . '">' . Constants::SECTION_CONFIGURATION_MENU_TITLE . '</a>',
                    '<a href="' . \get_bloginfo('wpurl') . '/wp-admin/admin.php?page=' . Constants::SECTION_HELP_PAGE_SLUG . '">' . Constants::SECTION_HELP_MENU_TITLE . '</a>'
                );
            }

            return $pluginActionsLinks;

        }, 10, 2);

    }

    /**
     * @param $self
     * @return void
     */
    protected function deferPluginBootstrapActions($self): void
    {
        // "wp_loaded": Runs after Wordpress core has loaded.
        // After this event the wordpress user is hydrated which makes methods available like "\wp_get_current_user available".
        // The app can now be bootstrapped (sync AudiencePlayer user for default frontend template usage).
        \add_action('wp_loaded', function () use ($self) {
            if ($self->helper->isWordpressWebsiteContext()) {
                $self->syncAudiencePlayerUserAuthentication(0, [], false);
            }
        });
    }

    /**
     * @param $self
     * @return void
     */
    protected function deferAjaxApiActions($self): void
    {
        // "wp_ajax_{audienceplayer_api_call}": Runs when authenticated Wordpress user triggers an AJAX request.
        \add_action('wp_ajax_audienceplayer_api_call', function () use ($self) {
            $self->audienceplayerApiCall();
        });
        // "wp_ajax_nopriv_{audienceplayer_api_call}": Runs when non-authenticated Wordpress user triggers an AJAX request.
        \add_action('wp_ajax_nopriv_audienceplayer_api_call', function () use ($self) {
            $self->audienceplayerApiCall();
        });

        // "wp_ajax_{audienceplayer_sync_session}": Runs when authenticated Wordpress user triggers an AJAX request.
        \add_action('wp_ajax_audienceplayer_sync_session', function () use ($self) {
            $self->audienceplayerSyncSession();
        });

        // "wp_ajax_{audienceplayer_api_callback}": Runs when authenticated Wordpress user triggers an AJAX request.
        \add_action('wp_ajax_audienceplayer_api_callback', function () use ($self) {
            $self->audienceplayerApiCallback();
        });
        // "wp_ajax_nopriv_{audienceplayer_api_callback}": Runs when non-authenticated Wordpress user triggers an AJAX request.
        \add_action('wp_ajax_nopriv_audienceplayer_api_callback', function () use ($self) {
            $self->audienceplayerApiCallback();
        });

    }

    /**
     * @param $self
     * @return void
     */
    protected function deferAdminFormActions($self): void
    {
        // "wp_loaded": Runs when Wordpress admin interface is bootstrapped.
        // Relevant javascripts and styles are enqueued.
        \add_action('admin_enqueue_scripts', function () {
            \wp_register_script('audienceplayer_admin_actions.js', self::fetchPluginUrl('static/js/audienceplayer_admin_actions.js'), ['jquery', 'wp-util'], self::fetchPluginVersion());
            \wp_enqueue_script('audienceplayer_admin_actions.js', self::fetchPluginUrl('static/js/audienceplayer_admin_actions.js'), ['jquery', 'wp-util'], self::fetchPluginVersion());
            \wp_register_style('font-awesome.css', self::fetchPluginUrl('/templates/css/font-awesome.css'), [], self::fetchPluginVersion());
            \wp_enqueue_style('font-awesome.css', self::fetchPluginUrl('/templates/css/font-awesome.css'), [], self::fetchPluginVersion());
            \wp_register_style('audienceplayer_admin.css', self::fetchPluginUrl('static/css/audienceplayer_admin.css'), [], self::fetchPluginVersion());
            \wp_enqueue_style('audienceplayer_admin.css', self::fetchPluginUrl('static/css/audienceplayer_admin.css'), [], self::fetchPluginVersion());
        });

        // Render basic javascript
        \add_action('admin_head', function () use ($self) {
            if ($self->helper->isWordpressAdminContext()) {
                include self::fetchPluginPath('/static/js/audienceplayer_admin.php');
            }
        });

        if ($self->isSyncUserAccounts()) {

            \add_action('edit_user_profile', function ($wordpressUser) use ($self) {
                echo $self->helper->populateTemplateString(
                    file_get_contents(self::fetchPluginPath('/static/html/admin_user_profile_audienceplayer_section.html')),
                    ['audienceplayer_user_id' => \get_user_meta($wordpressUser->ID, 'audienceplayer_user_id', true)]
                );
            });

            // "wp_ajax_{audienceplayer_admin_user_profile_force_sync}": Runs when #audienceplayer_admin_user_profile_force_sync_button is clicked.
            // In this Ajax call, the Wordpress user is (re)synchronised.
            \add_action('wp_ajax_audienceplayer_admin_user_profile_force_sync', function () use ($self) {
                $self->audienceplayerAdminUserProfileForceSync();
            });
        }
    }

    /**
     * @param $self
     * @return void
     */
    protected function deferAdminLogActions($self): void
    {
        // download a log file, accessible for admin users or AudiencePlayer backend (credential check executed in the method)
        \add_action('wp_ajax_audienceplayer_admin_download_logs', function () use ($self) {
            $self->audienceplayerDownloadLogs();
        });
        \add_action('wp_ajax_nopriv_audienceplayer_admin_download_logs', function () use ($self) {
            $self->audienceplayerDownloadLogs();
        });

        // download a file, accessible for admin users or AudiencePlayer backend (credential check executed in the method)
        \add_action('wp_ajax_audienceplayer_admin_clean_logs', function () use ($self) {
            $self->audienceplayerCleanLogs();
        });
        \add_action('wp_ajax_nopriv_audienceplayer_admin_clean_logs', function () use ($self) {
            $self->audienceplayerCleanLogs();
        });
    }

    /**
     * @param $self
     * @return void
     */
    protected function deferWebsiteActions($self): void
    {
        // Runs just before starting buffer output
        \add_action('template_redirect', function () use ($self) {

            $orderId = intval($_REQUEST['user_payment_account_order_id'] ?? 0);

            if ($orderId) {

                $self->validatedOrders[$orderId] = (object)[
                    'validation_result' => false,
                    'user_payment_account_order_id' => $orderId,
                    'product_id' => intval($_REQUEST['product_id'] ?? 0),
                    'subscription_id' => intval($_REQUEST['subscription_id'] ?? 0),
                    'attributes' => (object)[],
                ];

                $result = $self->api->mutation
                    ->UserPaymentAccountOrderValidate($orderId)
                    ->execute();

                if ($result->isSuccessful()) {

                    $self->validatedOrders[$orderId]->validation_result = true;

                    if ($data = $self->fetchCurrentAudiencePlayerUser(['id,orders{id,amount,type,status}'], true)) {

                        foreach ($data->orders as $order) {
                            if ($order->id === $orderId) {
                                $self->validatedOrders[$orderId]->attributes = $order;
                                break;
                            }
                        }
                    }

                } else {
                    $this->writeLog(
                        Constants::LOG_LEVEL_WARNING,
                        'resource.bootstrap.user-payment-account-order-validation.error',
                        'Warning, AudiencePlayer user payment account order with id [#' . $orderId . '] could not be validated (status: ' . $result->getFirstErrorCode() . ')',
                        [
                            'user_payment_account_order_id' => $orderId,
                            'result' => $result->getData(),
                        ]
                    );
                }
            }
        });

        // Runs just before the theme template is included
        \add_action('template_include', function ($template) use ($self) {

            // inspect QSA for pre-defined generic article detail page parameters and redirect to generic article detail page
            foreach (self::$articleDetailQsaConfigs as $qsaParam) {

                // 1. Handle generic article detail pages
                if ($articleDetailSlug = \get_query_var($qsaParam)) {

                    $arrSlug = explode('/', $articleDetailSlug);
                    $articleDetailSlug = $arrSlug[0] ?? $articleDetailSlug;

                    $self->genericPageArgs = [];

                    if (is_numeric($articleDetailSlug)) {
                        $self->genericPageArgs['article_id'] = $articleDetailSlug;
                    } else {
                        $self->genericPageArgs['article_url_slug'] = $articleDetailSlug;
                    }

                    if ($qsaParam === 'article_detail_episode_url_slug' && ($ancestorUrlSlug = $self->fetchQueryUrlSlugPart($self->loadConfig('article_detail_series_url_slug')))) {
                        if (is_numeric($ancestorUrlSlug)) {
                            $self->genericPageArgs['ancestor_id'] = $ancestorUrlSlug;
                        } else {
                            $self->genericPageArgs['ancestor_url_slug'] = $ancestorUrlSlug;
                        }
                    }

                    return $self->locatePluginTemplateSection('audienceplayer-generic-article-detail.php');
                }
            }

            return $template;
        });

        // Initialise custom rewrite rules
        \add_action('init', function () use ($self) {
            $self->hydrateCustomRewriteRules(false);
        });

        // Populate allowed query vars
        \add_filter('query_vars', function ($query_vars) {
            // 1. Handle generic article detail pages
            foreach (self::$articleDetailQsaConfigs as $qsaParam) {
                $query_vars[] = $qsaParam;
            }
            return $query_vars;
        });

        // One time activation functions when plugin is activated
        \register_activation_hook($self::fetchPluginFile(), function () use ($self) {
            // (Re)hydrate rewrite rules
            $self->hydrateCustomRewriteRules(true);
        });

        // Add chromecast icon to the primary nav menu in the last position (priority "90")
        \add_filter('wp_nav_menu_items', function ($items, $args) use ($self) {

            if ($args->theme_location === 'primary') {

                // Fetch Chromecast receiver app id and button html template
                $chromecastReceiverAppId = $self->fetchChromecastReceiverAppId();
                $chromecastButtonTemplate = trim($self->renderPluginTemplateSection('audienceplayer-chromecast-button.html', []));

                // Only if both are filled, add the chromecast icon to the menu
                if ($chromecastReceiverAppId && $chromecastButtonTemplate) {
                    $items .= '<li class="menu-item menu-item-audienceplayer">' . $chromecastButtonTemplate . '</li>';
                }
            }

            return $items;
        }, 90, 2);

        // Runs at the end of the page, when the wp_footer action is triggered near the tag of the user’s template by \wp_footer()
        \add_action('wp_footer', function () use ($self) {
            echo $self->renderPluginTemplateSection('audienceplayer-debug-console');
        });

        /*
        //Example for a custom post type
        \add_action('init', function () {
            \register_post_type('video-detail-page',
                // CPT Options
                [
                    'labels' => [
                        'name' => __('Video Detail Page'),
                        'singular_name' => __('Video Detail Page')
                    ],
                    'public' => true,
                    'has_archive' => true,
                    'rewrite' => ['slug' => 'video'],
                    'show_in_rest' => true,
                ]
            );
        });
        */
    }

    /**
     * @param $self
     * @return void
     */
    protected function deferUserAuthenticationActions($self): void
    {
        if ($self->isSyncUserAccounts()) {

            // "authenticate": Runs immediately after a user has submitted login form.
            // MEDIUM PRIO, must fire at somewhere in this chain
            \add_filter('authenticate', function ($userResult = null, $userLogin = '', $userPassword = '') use ($self) {

                $ret = $userResult;
                $self->isNewUserAuthenticationRequest = true;
                $self->isNewUserAuthenticationSyncRequest = false;

                if (trim($userLogin)) {

                    if (filter_var($userLogin, FILTER_VALIDATE_EMAIL)) {

                        // Retrieve id from WP_User (if there was an error, $userResult is instance of WP_Error
                        $wordpressUserId = $self->assembleCallbackWordpressUserId($userResult);
                        // Fetch user password
                        $userPassword = $userPassword ?: $self->assembleSubmittedRequestProperty(['pwd', 'user_pass', 'password']);
                        // login-property is an e-mail address and its related user does not yet exist locally
                        $userLogin = $userLogin ?: $self->assembleSubmittedRequestProperty(['log', 'login', 'email']);

                        if (
                            // WP_User was not retrieved
                            !$wordpressUserId &&
                            // password was entered
                            $userPassword &&
                            // login-property is an e-mail address and its related user does not yet exist locally
                            $userLogin && (false === (username_exists($userLogin) || email_exists($userLogin)))
                        ) {
                            // Check if user is authenticatable
                            if ($result = $self->authenticateRemoteAudiencePlayerUser($userLogin, $userPassword)) {

                                // sync remote user to Wordpress
                                $userArgs = [
                                    'user_login' => $userLogin,
                                    'user_pass' => $userPassword,
                                ];

                                if ($self->syncAudiencePlayerUserToWordpress($result->user_id, $userLogin, $userArgs, false) && $self->isWordpressUserHydrated()) {
                                    $ret = $self->wordpressUserObject->user;
                                } else {
                                    $self->isNewUserAuthenticationSyncRequest = true;
                                }

                            } elseif ($self->fetchRemoteAudiencePlayerUser(0, $userLogin)) {

                                // user was not authenticatable but does exist remotely, so show appropriate error and hint towards
                                // password reset using the default "incorrect password" error from "wp-includes/user.php"
                                $ret = new WP_Error(
                                    'incorrect_password',
                                    sprintf(
                                        __('<strong>Error</strong>: The password you entered for the email address %s is incorrect.', 'default'),
                                        '<strong>' . $userLogin . '</strong>'
                                    ) .
                                    ' <a href="' . \wp_lostpassword_url() . '">' .
                                    __('Lost your password?', 'default') .
                                    '</a>'
                                );

                            }

                        } else {
                            $self->isNewUserAuthenticationSyncRequest = true;
                        }

                    } else {
                        // user must log in with an e-mail address, username login is not supported (AudiencePlayer only supports email + password
                        $ret = new WP_Error('invalid_email', __('Invalid email address.', 'default'));
                    }

                }

                return $ret;

            }, 200, 3);

            // "login_redirect": Runs immediately after user has been logged in to determine where to send the user
            // Use this filter to ensure the remote AudiencePlayer user exists and is synchronised.
            \add_filter('login_redirect', function ($url, $request, $user) use ($self) {

                if (
                    $self->isNewUserAuthenticationRequest &&
                    $self->isNewUserAuthenticationSyncRequest &&
                    ($wordpressUserId = $self->assembleCallbackWordpressUserId($user))
                ) {
                    $userArgs = [];
                    if ($password = $self->assembleSubmittedRequestProperty(['pwd', 'user_pass', 'password'])) {
                        $userArgs['password'] = $password;
                        $self->isChangeAttemptedOnPassword = true;
                    }
                    // user was just successfully authenticated and logged in Wordpress and must now be forcefully synched with AudiencePlayer
                    $self->syncAudiencePlayerUserAuthentication($wordpressUserId, $userArgs, true, true, Constants::USER_METADATA_SYNC_DIRECTION_WP2A);
                }

                return $url;

            }, 10, 3);
        }
    }

    /**
     * @param $self
     * @return void
     */
    protected function deferUserRegistrationActions($self): void
    {
        if ($self->isSyncUserAccounts()) {

            // "random_password": Runs immediately after a random password has been created for the user that is to be registered.
            // After this event, the new password is known which will be temporarily stored.
            // HIGH PRIO, fires before system events
            \add_action('random_password', function ($passwordNew, $length) use ($self) {
                $self->isNewUserRegistrationRequest = true;
                return $self->newUserRegistrationPassword = $passwordNew;
            }, 10, 2);

            // "registration_errors": Runs immediately after user registration form was submitted and is being validated.
            // After this event, given Wordpress user is validated and registered on AudiencePlayer.
            // HIGH PRIO, fires before system events
            \add_filter('registration_errors', function ($wpError, $sanitizedUserLogin = null, $newUserEmail = null) use ($self) {

                $self->isNewUserRegistrationRequest = true;
                $userArgs = [];

                if ($name = $sanitizedUserLogin ?: $self->helper->parseUserFullNameFromUserData($_REQUEST)) {
                    $userArgs['name'] = $name;
                }
                if ($password = $self->fetchDetectedNewUserRegistrationPassword($self->assembleSubmittedRequestProperty(['pass2', 'user_pass', 'user_password']))) {
                    $userArgs['password'] = $password;
                }
                if ($newUserEmail = $newUserEmail ?: $self->assembleSubmittedRequestProperty(['user_email'])) {
                    $self->syncWordpressUserRegisterAction($newUserEmail, $userArgs, $wpError);
                }

                return $wpError;
            }, 10, 3);

            // "check_admin_referer": Runs immediately after an admin request has been validated/falsified.
            // Currently the only way to reliably check if an admin creates a user account.
            // HIGH PRIO, fires before system events
            \add_action('check_admin_referer', function ($action) use ($self) {

                if ($action === 'create-user') {

                    // current request chain is a new user registration
                    $self->isNewUserRegistrationRequest = true;

                    if ($password = $self->assembleSubmittedRequestProperty(['pass2'])) {
                        $self->newUserRegistrationPassword = $password;
                    }
                }
            });

            // "user_register": Runs immediately after a new user registration has been successfully completed.
            // After this event, given Wordpress user is synchronised with AudiencePlayer.
            // HIGH PRIO, fires before system events
            //
            // Warning: the 'user_register' is also called when a Wordpress user is created via \wp_create_user and \wp_insert_user
            // which in turn is used by UserSyncTrait->createWordpressUser which has protection against undesired auto-login.
            // N.B, 3rd party might have set an auto-login on this event.
            \add_action('user_register', function ($wordpressUserId) use ($self) {

                $userArgs = [];

                // if a previously unsynched user registration is detected (e.g. admin creates a user), parse the userArgs
                if ($self->isNewUserRegistrationRequest) {

                    if ($email = $self->assembleSubmittedRequestProperty(['email'])) {
                        $userArgs['email'] = trim(\sanitize_email($email));
                    }
                    if ($password = $self->fetchDetectedNewUserRegistrationPassword($self->assembleSubmittedRequestProperty(['pass2', 'user_pass', 'user_password']))) {
                        $userArgs['password'] = $password;
                    }
                    if ($name = $self->helper->parseUserFullNameFromUserData($_REQUEST)) {
                        $userArgs['name'] = $name;
                    }
                }

                return $self->syncAudiencePlayerUserAuthentication($wordpressUserId, $userArgs, $self->isNewUserRegistrationRequest, false, Constants::USER_METADATA_SYNC_DIRECTION_WP2A);

            }, 10, 1);
        }
    }

    /**
     * @param $self
     * @return void
     */
    protected function deferUserProfileUpdateActions($self): void
    {
        if ($self->isSyncUserAccounts()) {

            // "user_profile_update_errors": Runs immediately after user profile-update-form was submitted and is being validated.
            // After this event, given Wordpress user is validated and updated on AudiencePlayer.
            // HIGH PRIO, fires before system events
            \add_filter('user_profile_update_errors', function ($wpError) use ($self) {

                if ($_REQUEST['user_id'] ?? 0) {

                    // Parse properties eligible for AudiencePlayer sync
                    $userArgs = [];
                    if ($email = $self->assembleSubmittedRequestProperty(['email'])) {
                        $userArgs['email'] = \sanitize_email($email);
                        $self->isChangeAttemptedOnEmail = true;
                    }
                    if ($password = $self->assembleSubmittedRequestProperty(['pass2', 'user_pass', 'user_password'])) {
                        $userArgs['password'] = $password;
                        $self->isChangeAttemptedOnPassword = true;
                    }
                    if ($name = $self->helper->parseUserFullNameFromUserData($_REQUEST)) {
                        $userArgs['name'] = $name;
                    }

                    $result = $self->syncWordpressUserUpdateAction(
                        Constants::ACTION_USER_PROFILE_UPDATE,
                        intval($_REQUEST['user_id']),
                        $userArgs,
                        $wpError
                    );

                    if ($result) {
                        // change-validation e-mail is sent early on in "admin-filters.php": \add_action('personal_options_update', 'send_confirmation_on_profile_email');
                        // @TODO: investigate delaying this until after 'user_profile_update_errors'
                        //\remove_action('personal_options_update', 'send_confirmation_on_profile_email');
                        //\do_action('personal_options_update', 'send_confirmation_on_profile_email');
                    }
                }

                return $wpError;
            }, 10, 1);


            // if user has entered confirm-change-email-flow by clicking on link e-mail "email_change_email"
            // "get_user_metadata": Runs very shortly after user has clicked on the " profile.php?newuseremail" link an
            // in e-mail to confirm change of e-mail address, and has entered the "newuseremail"-flow in user-edit.php
            // Before the applicable metakey (containing the new e-mail address) is parsed, we inject an AudiencePlayer
            // check.
            // We use this filter in favour of a hook on "email_change_email", since that event runs after the WP user
            // has already been updated with a new e-mail address.
            \add_filter('get_user_metadata', function ($check, $object_id, $meta_key, $single, $meta_type) use ($self) {

                // recreated evaluation logic contained in user-edit.php to obtain the new e-mail address
                if (
                    !$self->isChangeAttemptedOnEmail &&
                    defined('IS_PROFILE_PAGE') &&
                    IS_PROFILE_PAGE &&
                    $meta_type === 'user' &&
                    $meta_key === '_new_email' &&
                    isset($_REQUEST['newuseremail']) &&
                    ($wordpressUserId = intval($object_id ?? 0))
                ) {
                    $meta_cache = \update_meta_cache($meta_type, [$object_id]);
                    $new_email = maybe_unserialize($meta_cache[$object_id][$meta_key][0] ?? '');

                    if ($new_email && ($new_email['newemail'] ?? null) && hash_equals($new_email['hash'], $_REQUEST['newuseremail'])) {

                        $self->isChangeAttemptedOnEmail = true;

                        $userArgs = ['email' => trim($new_email['newemail'] ?? '')];

                        $result = $self->syncWordpressUserUpdateAction(
                            Constants::ACTION_ADMIN_USER_PROFILE_UPDATE,
                            $wordpressUserId,
                            $userArgs
                        );

                        // if sync was not successful, restore the original user data and revert the pending change + hard exit
                        if (false === $result) {
                            // @TODO: consider creating the missing Wordpress user
                            //$self->createWordpressUser($currentUserData);
                            \delete_user_meta($wordpressUserId, '_new_email');
                            \wp_die($self->fetchTranslations('dialogue_email_address_exists_conflict'));
                            return false;
                        }
                    }
                }

                return null;

            }, 10, 5);

            // "login_form_lostpassword"/"login_form_retrievepassword": Runs immediately before given action "lostpassword" or "retrievepassword" is processed.
            // Ensures local user is first created, if it exists remotely.
            $lostPasswordCallback = function ($args = null) use ($self) {

                if (
                    isset($_REQUEST['wp-submit']) &&
                    ($userLogin = $self->assembleSubmittedRequestProperty(['user_login', 'log', 'login', 'email'])) &&
                    filter_var($userLogin, FILTER_VALIDATE_EMAIL) &&
                    (false === (username_exists($userLogin) || email_exists($userLogin))) &&
                    ($remoteUser = $self->fetchRemoteAudiencePlayerUser(0, $userLogin))
                ) {

                    $userArgs = [
                        'user_login' => $userLogin,
                        'user_pass' => \wp_generate_password(),
                        'user_nicename' => $remoteUser->name,
                        'display_name' => $remoteUser->name,
                    ];

                    $self->isChangeAttemptedOnPassword = true;

                    return $self->syncAudiencePlayerUserToWordpress($remoteUser->id, $userLogin, $userArgs, false);
                }

                return $args;
            };
            \add_action('login_form_lostpassword', $lostPasswordCallback);
            \add_action('login_form_retrievepassword', $lostPasswordCallback);

            // "after_password_reset": Runs immediately upon completion of function \reset_password (e.g. when wp_set_password() is used).
            // Update the password for given user
            \add_action('after_password_reset', function ($user = null, $newPassword = '') use ($self) {
                if (
                    !$self->isChangeAttemptedOnPassword &&
                    ($wordpressUserId = $self->assembleCallbackWordpressUserId($user)) &&
                    ($newPassword = trim($newPassword) ?: $self->assembleSubmittedRequestProperty(['pass1', 'pass2']))
                ) {
                    $self->isChangeAttemptedOnPassword = true;
                    $self->syncWordpressUserToAudiencePlayer($wordpressUserId, ['password' => $newPassword]);
                }

                return true;
            }, 10, 2);

            \add_action('password_reset', function ($user = null, $newPassword = null) use ($self) {
                if (
                    !$self->isChangeAttemptedOnPassword &&
                    ($wordpressUserId = $self->assembleCallbackWordpressUserId($user)) &&
                    ($newPassword = trim($newPassword) ?: $self->assembleSubmittedRequestProperty(['pass1', 'pass2', 'password_1', 'password_2']))
                ) {
                    $self->isChangeAttemptedOnPassword = true;
                    $self->syncWordpressUserToAudiencePlayer($wordpressUserId, ['password' => $newPassword]);
                }

                return true;
            }, 10, 2);

            // "send_password_change_email": Fires near the end of \wp_update_user(), which may be used by other plugins to update user email/password
            \add_action('send_password_change_email', function ($send, $user = null, $userdata = null) use ($self) {

                $wordpressUserId = $self->assembleCallbackWordpressUserId($user);

                if (
                    !$self->isChangeAttemptedOnPassword &&
                    $wordpressUserId &&
                    // password_1, password_2 are used by plugins such as WooCommerce
                    ($newPassword = $self->assembleSubmittedRequestProperty(['pass1', 'pass2', 'password_1', 'password_2']))
                ) {
                    $self->isChangeAttemptedOnPassword = true;

                    // If sync fails, exit current flow to avoid the change being executed in Wordpress (a falsy return on this action won't halt execution)
                    if (!$self->syncWordpressUserToAudiencePlayer($wordpressUserId, ['password' => $newPassword])) {
                        \wp_die($self->fetchTranslations('dialogue_error_occurred'));
                    }
                }

            }, 10, 3);

            // "send_email_change_email": Fires near the end of \wp_update_user(), which may be used by other plugins to update user email/password
            \add_action('send_email_change_email', function ($send, $user = null, $userdata = null) use ($self) {

                $wordpressUserId = $self->assembleCallbackWordpressUserId($user);

                if (
                    !$self->isChangeAttemptedOnEmail &&
                    $wordpressUserId &&
                    // account_email is used by plugins such as WooCommerce
                    ($newEmail = $self->assembleSubmittedRequestProperty(['email', 'account_email']))
                ) {
                    $self->isChangeAttemptedOnEmail = true;

                    // If sync fails, exit current flow to avoid the change being executed in Wordpress (a falsy return on this action won't halt execution)
                    if (!$self->syncWordpressUserToAudiencePlayer($wordpressUserId, ['email' => $newEmail])) {
                        \wp_die($self->fetchTranslations('dialogue_email_address_exists_conflict'));
                    }
                }

            }, 10, 3);
        }
    }

    /**
     * @param $self
     * @return void
     */
    protected function deferUserDeleteActions($self): void
    {
        if ($self->isSyncUserAccounts()) {

            // "delete_user": Runs just before a user is deleted from the database.
            // During this event, the synchronised audienceplayer user data is known which will be temporarily stored.
            \add_action('delete_user', function ($wordpressUserId, $reassignPostsToWordpressUserId = null, $wordpressUser = null) use ($self) {
                $self->deletedWordpressUserObject = $self->fetchWordpressUserObject($wordpressUserId);
                if (!$self->deletedWordpressUserObject) {
                    \wp_die('An error occurred when preparing for AudiencePlayer user synchronisation! [code: ' . Constants::STATUS_ERROR_AUDIENCEPLAYER_USER_DELETION_PREPARATION_CODE . ']');
                }
            }, 10, 3);

            // "deleted_user": Runs immediately after a user is deleted from the database.
            // After this event, the deleted user will be deleted from AudiencePlayer.
            \add_action('deleted_user', function ($wordpressUserId) use ($self) {
                if (
                    $wordpressUserId &&
                    $self->deletedWordpressUserObject &&
                    $wordpressUserId === $self->fetchWordpressUserProperty($self->deletedWordpressUserObject, 'id') &&
                    ($audiencePlayerUserId = $self->fetchWordpressUserProperty($self->deletedWordpressUserObject, 'audienceplayer_user_id')) &&
                    ($wordpressUserEmail = $self->fetchWordpressUserProperty($self->deletedWordpressUserObject, 'email')) &&
                    false === $self->deleteAudiencePlayerUser($audiencePlayerUserId, $wordpressUserEmail)
                ) {
                    \wp_die(
                        'User was deleted in Wordpress, but an error occurred during AudiencePlayer user synchronisation! [code: ' . Constants::STATUS_ERROR_AUDIENCEPLAYER_USER_DELETION_CODE . ']' .
                        '<div style="padding-top:8px;font-family:monospace;">' . $self->api->fetchLastOperationResult(true, true) . '</div>'
                    );
                }
            });
        }
    }

    // ### INTERNAL GENERAL HELPERS

    /**
     * @return bool
     */
    protected function isSyncUserAccounts(): bool
    {
        return boolval($this->loadConfig('is_synchronise_user_accounts') ?? true);
    }

    /**
     * @param string $primaryValue
     * @return string|null
     */
    protected function fetchDetectedNewUserRegistrationPassword(string $primaryValue = ''): ?string
    {
        $password = $primaryValue ?: $this->newUserRegistrationPassword;
        $this->newUserRegistrationPassword = null;
        return $password;
    }

    /**
     * @param bool $isForceFlush
     * @return void
     */
    protected function hydrateCustomRewriteRules(bool $isForceFlush = false): void
    {
        // 1. Handle generic article detail pages
        foreach (self::$articleDetailQsaConfigs as $qsaParam) {
            \add_rewrite_tag('%' . $qsaParam . '%', '([^&]+)', $qsaParam . '=');
            if ($qsaParam === 'article_detail_episode_url_slug') {
                // allow for schema https://url/...../episode/{{ slug }}
                \add_rewrite_rule('(^|.*/)' . $this->loadConfig($qsaParam) . '/(.+?)/?$', 'index.php?' . $qsaParam . '=$matches[2]', 'top');
            } else {
                // allow for schema https://url/film/{{ slug }}
                \add_rewrite_rule('^' . $this->loadConfig($qsaParam) . '/(.+?)/?$', 'index.php?' . $qsaParam . '=$matches[1]', 'top');
            }
        }

        if ($isForceFlush) {
            // Flush rewrite rules to accommodate any url_slug changes that may have been made
            \flush_rewrite_rules();
        }
    }

    // ### INTERNAL CONFIG HELPERS

    /**
     * @return AudiencePlayerApiClient|null
     */
    protected function initAudiencePlayerApiClient(): ?AudiencePlayerApiClient
    {
        try {

            if ($this->loadConfig()) {

                return $this->api = AudiencePlayerApiClient::init(
                    strval($this->loadConfig('oauth_client_id') ?: ''),
                    strval($this->loadConfig('oauth_client_secret') ?: ''),
                    intval($this->loadConfig('project_id') ?: 0),
                    strval($this->loadConfig('api_base_url') ?: ''),
                    $this->helper->getLocale(),
                    $this->helper->getIpAddress()
                );
            }

        } catch (\Exception $e) {
            // catch exception
            //trigger_error('Could not instantiate AudiencePlayer API, OAuth credentials are likely invalid!', E_USER_WARNING);
        }

        return null;
    }

    /**
     * @return void
     */
    protected function initAudiencePlayerApiProjectConfig(): void
    {
        $this->loadProjectConfig(null, false);
    }

    /**
     * @param null $property
     * @return array|mixed|null
     */
    protected function loadConfig($property = null)
    {
        if (!($this->config ?? null)) {
            $this->config = [];
            $this->hydrateConfigVars(\get_option(AudiencePlayerWordpressPlugin::class) ?? []);
        }

        if ($property) {

            $value = $this->config[$property] ?? null;

            return is_null($value) ?
                $value :
                $this->helper->typeCastValue($value, $this->configVars[$property] ?? 'string');
        }

        return $this->config;
    }

    /**
     * @param $configs
     * @return void
     */
    protected function hydrateConfigVars($configs): void
    {
        if ($configs) {

            foreach ($configs as $key => $config) {
                $this->config = array_merge($this->config, $config);
                /* separate config per namespace, should the use case arise in the future
                foreach (Constants::CONFIG_NAMESPACES as $configNamespace) {
                    if (strstr($key, $configNamespace)) {
                        $this->config[$configNamespace] = array_merge($this->config[$configNamespace] ?? [], $config);
                    }
                }
                */
            }

            unset ($key, $config, $configs);
        }
    }

    /**
     * @param string|null $property
     * @param bool $forceReload
     * @return array|mixed|null
     */
    protected function loadProjectConfig(string $property = null, bool $forceReload = false)
    {
        if ((!($this->projectConfig ?? null) || false === $forceReload) && $this->api) {

            $cacheKey = $this->assembleCacheKey(['locale' => $this->helper->getLocale()]);

            $q = $this->api()->query->Config()->properties([
                'project_id',
                'has_article_nomadics',
                'has_article_series_nomadics',
                'platform{chromecast_receiver_app_id,is_support_player_playback_speed_change}',
                'language_tags{key,value}',
            ]);

            $callback = function () use ($q) {
                if (($result = $q->execute()) && $result->isSuccessful()) {
                    return $result->getData(true);
                } else {
                    return null;
                }
            };

            $this->projectConfig = (array)$this->syncCacheValue($cacheKey, $callback, $forceReload) ?? [];
        }

        if ($property) {
            return $this->projectConfig[$property] ?? null;
        } else {
            return $this->projectConfig;
        }
    }

    /**
     * @param $user
     * @return mixed|null
     */
    protected function assembleCallbackWordpressUserId($user)
    {
        if ($user && is_object($user) && ($user->ID ?? null)) {

            return $user->ID;

        } elseif (is_array($user) && ($user['ID'] ?? null)) {

            return $user['ID'];
        }

        return null;
    }

    /**
     * @param array $restrictKeys
     * @return string
     */
    protected function assembleSubmittedRequestProperty(array $restrictKeys = []): string
    {
        $ret = null;

        foreach ($restrictKeys as $key) {
            if (is_null($ret)) {
                $ret = $_REQUEST[$key] ?? null;
            } else {
                break;
            }
        }

        return trim(strval($ret));
    }

    /**
     * @return bool
     */
    protected function validateOauthQueryStringCredentials(): bool
    {
        return
            trim($_REQUEST['oauth_client_id'] ?? '') && $_REQUEST['oauth_client_id'] === $this->loadConfig('oauth_client_id') &&
            trim($_REQUEST['oauth_client_secret'] ?? '') && $_REQUEST['oauth_client_secret'] === $this->loadConfig('oauth_client_secret');
    }

}
