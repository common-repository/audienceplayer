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

use AudiencePlayer\AudiencePlayerWordpressPlugin\Config\Constants;
use WP_Error;

trait UserSyncTrait
{
    protected $wordpressUserObject = null;
    protected $bearerTokenTtl = 3600;

    /**
     * @param int $wordpressUserId
     * @param array $userArgs
     * @param bool $isAutoRegister
     * @param bool $isForceSync
     * @param string $metaSyncDirection
     * @return bool
     */
    public function syncAudiencePlayerUserAuthentication(
        int $wordpressUserId = 0,
        array $userArgs = [],
        bool $isAutoRegister = true,
        bool $isForceSync = false,
        string $metaSyncDirection = ''
    ): bool
    {
        $ret = false;

        // Wordpress user hydration
        $this->hydrateWordpressUser($wordpressUserId);

        if ($this->isWordpressUserHydrated() && ($this->api ?? null)) {

            $wordpressUserId = $this->fetchWordpressUserProperty($wordpressUserId, 'id');
            $wordpressUserEmail = $this->fetchWordpressUserProperty($wordpressUserId, 'email');
            $currentToken = $this->fetchWordpressUserProperty($wordpressUserId, 'audienceplayer_bearer_token');
            $audiencePlayerUserId = $this->fetchWordpressUserProperty($wordpressUserId, 'audienceplayer_user_id');

            try {

                $result = $this->api->hydrateValidatedOrRenewedBearerTokenForUser(
                    $audiencePlayerUserId,
                    $wordpressUserEmail,
                    $userArgs,
                    $isForceSync ? '' : $currentToken,
                    $isAutoRegister,
                    true
                );

                if ($result['access_token'] ?? false) {

                    if ($result['expires_in'] ?? null) {
                        $this->bearerTokenTtl = $result['expires_in'];
                    }

                    // If token us unchanged OR a new user Wordpress user is being registered
                    if ($result['access_token'] === $currentToken) {

                        $ret = true;

                    } elseif ($ret = boolval($this->updateWordpressUserProperty($wordpressUserId, 'audienceplayer_bearer_token', $result['access_token']))) {

                        if ($result['user_id'] && $result['user_id'] !== $audiencePlayerUserId) {
                            $this->updateWordpressUserProperty($wordpressUserId, 'audienceplayer_user_id', $result['user_id']);
                        }

                        $this->hydrateWordpressUser($wordpressUserId, true);
                    }

                }

            } catch (\Exception $e) {

                // Log exception
                $this->writeLog(
                    Constants::LOG_LEVEL_SYSTEM_ERROR,
                    'resource.user-sync.sync-audienceplayer-user-authentication',
                    'Caught exception while synchronising Wordpress user [#' . strval($wordpressUserId) . '] with AudiencePlayer user [#' . strval($audiencePlayerUserId ?? '') . ']',
                    [
                        'wordpress_user_id' => $wordpressUserId,
                        'wordpress_user_email' => $wordpressUserEmail,
                        'audienceplayer_user_id' => $audiencePlayerUserId,
                        'error_msg' => $e->getMessage(),
                        'error_code' => $e->getCode(),
                    ]
                );
            }
        }

        if ($ret && $metaSyncDirection) {
            $ret = $this->syncUserMetaData($wordpressUserId, $metaSyncDirection) && $ret;
        }

        return $ret;
    }

    /**
     * @param int $wordpressUserId
     * @param string $direction
     * @return bool
     */
    protected function syncUserMetaData(int $wordpressUserId, string $direction = Constants::USER_METADATA_SYNC_DIRECTION_WP2A): bool
    {
        $ret = false;
        $apiResult = null;
        $e = null;

        try {

            if (
                ($wordpressUserObject = $this->fetchWordpressUserObject($wordpressUserId)) &&
                ($audiencePlayerUserId = $this->fetchWordpressUserProperty($wordpressUserObject, 'audienceplayer_user_id'))
            ) {

                if ($direction === Constants::USER_METADATA_SYNC_DIRECTION_WP2A) {

                    $metadata = [];

                    // Fetch Wordpress user metas and update remote AudiencePlayer user
                    foreach (Constants::USER_METADATA_SYNC_KEYS as $metaKey => $typeCast) {
                        $value = $this->fetchWordpressUserProperty($wordpressUserObject, $metaKey);
                        if (!is_null($value)) {
                            $metadata[$metaKey] = trim($value);
                        }
                    }
                    $metadata['first_name'] = trim($this->fetchWordpressUserProperty($wordpressUserObject, 'first_name'));
                    $metadata['last_name'] = trim($this->fetchWordpressUserProperty($wordpressUserObject, 'last_name'));
                    $metadata['email'] = trim($this->fetchWordpressUserProperty($wordpressUserObject, 'email'));

                    $metadata = $this->helper->parseAudiencePlayerUserArgs($metadata);
                    unset($metadata['email']);

                    if ($metadata) {
                        $ret = $this->updateAudiencePlayerUser($audiencePlayerUserId, $metadata, true);
                    } else {
                        $ret = true;
                    }

                } elseif ($direction === Constants::USER_METADATA_SYNC_DIRECTION_A2WP) {

                    // Fetch remote AudiencePlayer user metas and update Wordpress user
                    $apiResult = $this->api->fetchUser($audiencePlayerUserId, '', array_keys(Constants::USER_METADATA_SYNC_KEYS));

                    if (($ret = $apiResult->isSuccessful()) && ($metadata = (array)$apiResult->getData(true))) {

                        foreach ($metadata as $key => $value) {

                            if ($value = trim($value)) {

                                if ($key === 'name') {
                                    $this->updateWordpressUserProperty($wordpressUserId, 'first_name', $this->helper->parsePersonName($value, 'first')) && $ret;
                                    $this->updateWordpressUserProperty($wordpressUserId, 'last_name', $this->helper->parsePersonName($value, 'last')) && $ret;
                                } else {
                                    $this->updateWordpressUserProperty($wordpressUserId, $key, $value) && $ret;
                                }
                            }
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            $ret = false;
        }

        if (false === $ret) {
            // Log exception
            $this->writeLog(
                Constants::LOG_LEVEL_WARNING,
                'resource.user-sync.sync-user-metadata',
                'Error occurred while synchronising metadata for Wordpress user [#' . strval($wordpressUserId) . '] with AudiencePlayer user [#' . strval($audiencePlayerUserId ?? '') . ']',
                [
                    'wordpress_user_id' => $wordpressUserId,
                    'audienceplayer_user_id' => $audiencePlayerUserId ?? null,
                    'metadata_keys' => Constants::USER_METADATA_SYNC_KEYS,
                    'metadata' => $metadata ?? [],
                    'direction' => $direction,
                    'api_result' => $apiResult ? $apiResult->getRawData() : $apiResult,
                    'error_msg' => $e ? $e->getMessage() : '',
                    'error_code' => $e ? $e->getCode() : '',
                ]
            );
        }

        return boolval($ret);
    }

    /**
     * @param $arr
     */
    protected function parseMetas($arr)
    {
        $ret = [];

        foreach ($arr as $key => $value) {
            if ($value = trim($value)) {
                $ret[$key] = $value;
            }
        }
    }


    /**
     * @param string $wordpressUserEmail
     * @param array $userArgs
     * @return null
     */
    protected function createAudiencePlayerUser(string $wordpressUserEmail, array $userArgs = [])
    {
        $ret = null;

        try {

            $result = $this->api->hydrateValidatedOrRenewedBearerTokenForUser(
                0,
                $wordpressUserEmail,
                $userArgs,
                '',
                true,
                true
            );

        } catch (\Exception $e) {

            $result = null;

            // Log exception
            $this->writeLog(
                Constants::LOG_LEVEL_SYSTEM_ERROR,
                'resource.user-sync.create-audienceplayer-user',
                'Caught exception while creating an AudiencePlayer user with email ' . $wordpressUserEmail,
                [
                    'wordpress_user_email' => $wordpressUserEmail,
                    'error_msg' => $e->getMessage(),
                    'error_code' => $e->getCode(),
                ]
            );
        }

        if ($result['user_id'] ?? false) {
            $ret = $result['user_id'];
        }

        return $ret;
    }

    /**
     * @param int $audiencePlayerUserId
     * @param array $userArgs
     * @param bool $isSanitiseWordpressQuotes
     * @return bool
     */
    protected function updateAudiencePlayerUser(int $audiencePlayerUserId, array $userArgs = [], bool $isSanitiseWordpressQuotes = true): bool
    {
        if ($isSanitiseWordpressQuotes) {
            foreach ($userArgs as $key => $value) {
                if (is_string($value)) {
                    $userArgs[$key] = stripslashes($value);
                }
            }
        }

        // Try to update AudiencePlayer user
        return $audiencePlayerUserId && $userArgs && $this->api->updateUser($audiencePlayerUserId, $userArgs);
    }

    /**
     * @param int $audiencePlayerUserId
     * @param string $wordpressUserEmail
     * @return bool
     */
    protected function deleteAudiencePlayerUser(int $audiencePlayerUserId, string $wordpressUserEmail)
    {
        $ret = false;

        if ($audiencePlayerUserId && $wordpressUserEmail) {

            try {

                $ret = $this->api->deleteUser($audiencePlayerUserId, $wordpressUserEmail);

            } catch (\Exception $e) {

                $ret = false;

                // Log exception
                $this->writeLog(
                    Constants::LOG_LEVEL_SYSTEM_ERROR,
                    'resource.user-sync.delete-audienceplayer-user',
                    'Caught exception while deleting AudiencePlayer user [#' . strval($audiencePlayerUserId) . '] for user for Wordpress user [' . strval($wordpressUserEmail) . ']',
                    [
                        'audienceplayer_user_id' => $audiencePlayerUserId,
                        'wordpress_user_email' => $wordpressUserEmail,
                        'error_msg' => $e->getMessage(),
                        'error_code' => $e->getCode(),
                    ]
                );
            }
        }

        return $ret;
    }

    /**
     * @param $userData
     * @param bool $isForceSilentCreation
     * @return int|WP_Error
     */
    protected function createWordpressUser($userData, $isForceSilentCreation = true)
    {
        if ($isForceSilentCreation) {
            // prevent that this is treated as newly created user registration (by wp_insert_user => 'user_register')
            $this->isNewUserRegistrationRequest = false;
        }

        // Collect currently logged in user
        $currentWordpressUserId = \get_current_user_id();

        // create the new Wordpress user
        $wordpressUserId = \wp_insert_user($userData);

        // If successful, prevent auto-login of this user which is often set in the Wordpress theme on the 'user_register' event
        // to allow newly registered users to become auto-logged in
        if ($isForceSilentCreation && \get_current_user_id() !== $currentWordpressUserId) {
            \wp_logout();
        }

        return $wordpressUserId;
    }

    /**
     * @param string $newUserEmail
     * @param array $userArgs
     * @param WP_Error|null $wpError
     * @return bool|WP_Error
     */
    public function syncWordpressUserRegisterAction(string $newUserEmail, array $userArgs = [], WP_Error &$wpError = null)
    {
        $ret = false;

        if (is_null($wpError) || !$wpError->has_errors()) {

            // First try to register given user with AudiencePlayer
            if ($newUserEmail && filter_var($newUserEmail, FILTER_VALIDATE_EMAIL)) {

                if ($this->createAudiencePlayerUser($newUserEmail, $userArgs)) {
                    $ret = true;
                } else {
                    $wpError->add('email_error', __('<strong>ERROR</strong>: E-mail address cannot be registered (likely an account with this address already exists).', Constants::TRANSLATION_TEXT_DOMAIN));
                }

            } else {
                $wpError->add('email_error', __('Invalid email address.', 'default'));
            }
        }

        return $wpError ? $wpError : $ret;
    }

    /**
     * @param string $actionContext
     * @param int $wordpressUserId
     * @param array $userArgs
     * @param WP_Error|null $wpError
     * @return bool|WP_Error
     */
    public function syncWordpressUserUpdateAction(string $actionContext, int $wordpressUserId, array $userArgs = [], WP_Error &$wpError = null)
    {
        $ret = false;

        if (is_null($wpError) || !$wpError->has_errors()) {

            // Validate Wordpress user first, and force sync with AudiencePlayer
            if (
                $this->syncAudiencePlayerUserAuthentication($wordpressUserId, $userArgs, true, true, Constants::USER_METADATA_SYNC_DIRECTION_WP2A) &&
                ($audiencePlayerUserId = $this->fetchWordpressUserProperty($wordpressUserId, 'audienceplayer_user_id'))
            ) {
                $ret = true;
                $updateArgs = [];
                $isAdminUser = ($actionContext === Constants::ACTION_ADMIN_USER_PROFILE_UPDATE) || $this->isWordpressAdminUser(\get_current_user_id());

                // Parse e-mail argument
                if ($ret && ($userArgs['email'] ?? '')) {

                    if (filter_var($userArgs['email'], FILTER_VALIDATE_EMAIL)) {

                        // Moderator/Admin will directly modify the e-mail address.
                        // A regular user e-mail change occurs later, after explicit confirmation e-mail
                        if ($isAdminUser) {
                            // Prepare for update, which will be handled below
                            $updateArgs['email'] = $userArgs['email'];
                        } elseif (
                            // Query the API and check if new e-mail address is available
                            $userArgs['email'] !== $this->fetchWordpressUserProperty($wordpressUserId, 'email') &&
                            false === $this->isAudiencePlayerEmailAddressAvailable($userArgs['email'])
                        ) {
                            $ret = false;
                            if ($wpError) {
                                $wpError->add('email_error', __('<strong>ERROR</strong>: E-mail address "' . $userArgs['email'] . '" is not available.', 'audienceplayer'));
                            }
                            \add_filter('send_email_change_email', '__return_false');
                            \delete_user_meta($wordpressUserId, '_new_email');
                        }

                    } else {
                        $ret = false;
                        if ($wpError) {
                            $wpError->add('email_error', __('Invalid email address.', 'default'));
                        }
                    }
                }

                // Parse password argument
                if ($ret && ($userArgs['password'] ?? '')) {
                    $updateArgs['password'] = $userArgs['password'];
                }

                if ($ret && ($userArgs['name'] ?? '')) {
                    $updateArgs['name'] = $userArgs['name'];
                }

                // Try to update AudiencePlayer e-mail address
                if ($ret && $updateArgs && false === $this->updateAudiencePlayerUser($audiencePlayerUserId, $updateArgs, true)) {

                    // ERROR: updating remote user
                    $ret = false;
                    if ($wpError) {
                        $wpError->add('email_error', __('<strong>ERROR</strong>: User details cannot be updated (possibly an account with given e-mail address already exists?).', Constants::TRANSLATION_TEXT_DOMAIN));
                    }
                }

            } else {

                // user not found
                $ret = false;
                if ($wpError) {
                    $wpError->add('email_error', __('<strong>ERROR</strong>: User data could not be validated.', Constants::TRANSLATION_TEXT_DOMAIN));
                }
            }
        }

        return $ret;
    }

    /**
     * @param int $audiencePlayerUserId
     * @param string $email
     * @param array $userArgs
     * @param bool $isDeleteUser
     * @return bool
     */
    public function syncAudiencePlayerUserToWordpress(int $audiencePlayerUserId, string $email, array $userArgs = [], bool $isDeleteUser = false)
    {
        $ret = false;

        // Find user by Id
        if (
            ($users = \get_users(['meta_key' => 'audienceplayer_user_id', 'meta_value' => $audiencePlayerUserId])) &&
            $numUsers = count($users)
        ) {

            if ($numUsers === 1) {
                $userById = reset($users);
            } else {
                // Log exception
                $this->writeLog(
                    Constants::LOG_LEVEL_SYSTEM_ERROR,
                    'resource.user-sync.sync-audienceplayer-user-to-wordpress.multiple-users-found',
                    'Possible data corruption detected, multiple Wordpress users found for AudiencePlayer user [#' . $audiencePlayerUserId . ']',
                    [
                        'audienceplayer_user_id' => $audiencePlayerUserId,
                        'email' => $email,
                        'user_args' => $userArgs,
                        'is_delete' => $isDeleteUser,
                        'user_count' => $numUsers,
                    ]
                );
                return false;
            }

        } else {
            $userById = null;
        }

        // Find user by e-mail
        $userByEmail = \get_user_by('email', $email);

        // Inspect user results
        if ($userById && $userByEmail && $userById->ID !== $userByEmail->ID) {

            // Log exception
            $this->writeLog(
                Constants::LOG_LEVEL_SYSTEM_ERROR,
                'resource.user-sync.sync-audienceplayer-user-to-wordpress.wordpress-user-conflict',
                'Possible data corruption detected, conflicting Wordpress user properties [#' . $userById->ID . ', #' . $userByEmail->ID . '] detected for AudiencePlayer user [#' . $audiencePlayerUserId . ']',
                [
                    'audienceplayer_user_id' => $audiencePlayerUserId,
                    'email' => $email,
                    'user_args' => $userArgs,
                    'is_delete' => $isDeleteUser,
                    'wordpress_user_by_id_id' => $userById->ID ?? null,
                    'wordpress_user_by_id_email' => $userById->user_email ?? null,
                    'wordpress_user_by_email_id' => $userByEmail->ID ?? null,
                    'wordpress_user_by_email_email' => $userByEmail->user_email ?? null,
                ]
            );

            return false;

        } elseif ($user = $userById ?: $userByEmail) {

            $logArgs = [
                'audienceplayer_user_id' => $audiencePlayerUserId,
                'email' => $email,
                'user_args' => $userArgs,
                'is_delete' => $isDeleteUser,
                'user_id' => $user->ID,
                'wordpress_user_by_id_id' => $userById->ID ?? null,
                'wordpress_user_by_id_email' => $userById->user_email ?? null,
                'wordpress_user_by_email_id' => $userByEmail->ID ?? null,
                'wordpress_user_by_email_email' => $userByEmail->user_email ?? null,
            ];

            // Update user e-mail address if necessary
            if (
                $user->user_email !== $email &&
                ($this->isChangeAttemptedOnEmail = true) &&
                !($retUpdateUser = \wp_update_user(['ID' => $user->ID, 'user_email' => $email]))
            ) {

                // Log exception
                $this->writeLog(
                    Constants::LOG_LEVEL_SYSTEM_ERROR,
                    'resource.user-sync.sync-audienceplayer-user-to-wordpress.db-insert-new-user-error',
                    'Error updating Wordpress user e-mail "' . $user->user_email . '" => "' . $email . '"',
                    array_merge($logArgs, ['wp_update_user_result' => $retUpdateUser])
                );

                return false;
            }

            // Delete existing user
            if ($isDeleteUser) {

                $logId = $user->ID;
                $logEmail = $user->user_email;

                if ($ret = \wp_delete_user($user->ID)) {
                    $this->writeLog(
                        Constants::LOG_LEVEL_INFO,
                        'resource.user-sync.sync-audienceplayer-user-to-wordpress.wordpress-user-deleted',
                        'Wordpress user "' . $logEmail . '" with id [#' . $logId . '] deleted',
                        $logArgs
                    );
                } else {
                    $this->writeLog(
                        Constants::LOG_LEVEL_SYSTEM_ERROR,
                        'resource.user-sync.sync-audienceplayer-user-to-wordpress.wordpress-user-deletion-error',
                        'Error, Wordpress user "' . $logEmail . '" with id [#' . $logId . '] was not deleted',
                        $logArgs
                    );
                    return false;
                }

            } else {

                if ($args = $this->helper->arrayOnly($this->helper->parseWordpressUserArgs($userArgs), ['user_pass'])) {
                    $this->isChangeAttemptedOnPassword = true;
                }

                $ret = $this->syncAudiencePlayerUserAuthentication($user->ID, $userArgs, false, true, Constants::USER_METADATA_SYNC_DIRECTION_A2WP);

                // metadata already handled in syncAudiencePlayerUserAuthentication, but password may also have been changed
                if ($ret) {

                    if ($args) {

                        $this->isChangeAttemptedOnPassword = true;
                        $ret = $this->updateWordpressUser($user->ID, $args);

                        if (!$ret) {
                            $this->writeLog(
                                Constants::LOG_LEVEL_SYSTEM_ERROR,
                                'resource.user-sync.sync-audienceplayer-user-to-wordpress.update-wordpress-user',
                                'Error updating Wordpress user [#' . strval($user->ID) . '] after successful audienceplayer user [#' . $audiencePlayerUserId . '] authentication synchronisation',
                                $logArgs
                            );
                        }
                    }

                } else {

                    $this->writeLog(
                        Constants::LOG_LEVEL_SYSTEM_ERROR,
                        'resource.user-sync.sync-audienceplayer-user-to-wordpress.sync-user-authentication',
                        'Error synchronising AudiencePlayer user [#' . $audiencePlayerUserId . '] authentication with Wordpress user [#' . strval($user->ID) . ']',
                        $logArgs
                    );
                }
            }

        } elseif (
            false === $isDeleteUser &&
            ($userArgs = $this->helper->parseWordpressUserArgs($userArgs, ['user_email' => $email])) &&
            ($userId = $this->createWordpressUser($userArgs))
        ) {

            // sync newly created user
            $ret = $this->syncAudiencePlayerUserAuthentication($userId, [], false, true, Constants::USER_METADATA_SYNC_DIRECTION_A2WP);

        } else {
            $ret = true;
        }

        if (!$ret) {
            $this->writeLog(
                Constants::LOG_LEVEL_WARNING,
                'resource.user-sync.sync-audienceplayer-user-to-wordpress.general-error',
                'Warning, AudiencePlayer user with id [#' . $audiencePlayerUserId . '] was not synched to Wordpress user "' . $email . '"',
                [
                    'audienceplayer_user_id' => $audiencePlayerUserId,
                    'email' => $email,
                    'user_args' => $userArgs,
                    'is_delete' => $isDeleteUser,
                ]

            );
        }

        return $ret;
    }

    /**
     * Synchronises Wordpress User data to the corresponding AudiencePlayer User via GraphQL API
     *
     * @param int $wordpressUserId
     * @param array $userArgs
     * @param bool $isSanitiseWordpressQuotes
     * @param bool $isReturnRaw
     * @return bool|object
     */
    public function syncWordpressUserToAudiencePlayer(
        int $wordpressUserId,
        array $userArgs = [],
        bool $isSanitiseWordpressQuotes = true,
        bool $isReturnRaw = false
    )
    {
        $ret = false;
        $retSync1 = true;
        $retSync2 = true;
        $message = '';

        if (
            $wordpressUserId &&
            ($retSync1 = $this->syncAudiencePlayerUserAuthentication($wordpressUserId, $userArgs, true, true, Constants::USER_METADATA_SYNC_DIRECTION_WP2A)) &&
            ($audiencePlayerUserId = $this->fetchWordpressUserProperty($wordpressUserId, 'audienceplayer_user_id'))
        ) {

            $userArgs = $this->helper->parseAudiencePlayerUserArgs($userArgs);

            // check for e-mail change first
            if (
                isset($userArgs['email']) &&
                ($emailAddress = $this->fetchWordpressUserProperty($wordpressUserId, 'email')) &&
                $userArgs['email'] !== $emailAddress &&
                false === $this->isAudiencePlayerEmailAddressAvailable($userArgs['email'])
            ) {
                $ret = false;
            } else {
                $retSync2 = $userArgs ? $this->updateAudiencePlayerUser($audiencePlayerUserId, $userArgs, $isSanitiseWordpressQuotes) : true;
                $ret = $retSync2;
            }
        }

        if (false === $retSync1 || false === $retSync2) {

            $message = 'Error synchronising data';

            $rawData = $this->writeLog(
                Constants::LOG_LEVEL_SYSTEM_ERROR,
                'resource.user-sync.sync-wordpress-user-to-audienceplayer',
                'Error synchronising (meta) data from Wordpress user [#' . strval($wordpressUserId) . '] to AudiencePlayer user [#' . strval($audiencePlayerUserId ?? '') . ']',
                [
                    'result_sync_audienceplayer_user_authentication' => $retSync1,
                    'result_update_audienceplayer_user' => $retSync2,
                ]
            );
        }

        if ($isReturnRaw) {
            return $this->assembleDefaultRawReturnObject($ret, $message, $rawData ?? null);
        } else {
            return $ret;
        }
    }


    /**
     * Synchronise an entitlement for an AudiencePlayer Subscription resource to AudiencePlayer
     *
     * @param int $wordpressUserId The id of the local Wordpress user
     * @param int $audiencePlayerSubscriptionId The id of the remote AudiencePlayer subscription
     * @param string $action Either "fulfill" or "revoke" the entitlement
     * @param \DateTime|null $expiresAt Required with action "fulfil", specify future date
     * @param bool $isReturnRaw
     */
    public function syncWordpressUserSubscriptionEntitlementToAudiencePlayer(
        int $wordpressUserId,
        int $audiencePlayerSubscriptionId,
        string $action,
        \DateTime $expiresAt = null,
        bool $isReturnRaw = false
    )
    {
        $ret = false;
        $message = '';

        if (
            $wordpressUserId &&
            $audiencePlayerSubscriptionId &&
            ($audiencePlayerUserId = $this->fetchWordpressUserProperty($wordpressUserId, 'audienceplayer_user_id'))
        ) {
            try {
                $ret = $this->api->manageUserSubscriptionEntitlement($audiencePlayerUserId, $audiencePlayerSubscriptionId, $action, $expiresAt);
            } catch (\Exception $e) {

                // Log exception
                $message = 'Caught exception while synchronising a subscription entitlement to AudiencePlayer subscription';
                $rawData = $this->writeLog(
                    Constants::LOG_LEVEL_SYSTEM_ERROR,
                    'resource.user-sync.sync-wordpress-user-subscription-entitlement-to-audienceplayer',
                    'Caught exception while synchronising a subscription entitlement to AudiencePlayer subscription [#' . $audiencePlayerSubscriptionId . '] of user [#' . strval($audiencePlayerUserId) . '] for user for Wordpress user [#' . strval($wordpressUserId) . ']',
                    [
                        'audienceplayer_user_id' => $audiencePlayerUserId,
                        'wordpress_user_id' => $wordpressUserId,
                        'audienceplayer_subscription_id' => $audiencePlayerSubscriptionId,
                        'error_msg' => $e->getMessage(),
                        'error_code' => $e->getCode(),
                    ]
                );
            }
        }

        if ($isReturnRaw) {
            return $this->assembleDefaultRawReturnObject($ret, $message, $rawData ?? null);
        } else {
            return $ret;
        }
    }

    /**
     * @param int $wordpressUserId The id of the local Wordpress user
     * @param int $audiencePlayerProductId The id of the remote AudiencePlayer product
     * @param string $action Either "fulfill" or "revoke" the entitlement
     * @return bool
     */
    public function syncWordpressUserProductEntitlementToAudiencePlayer(
        int $wordpressUserId,
        int $audiencePlayerProductId,
        string $action
    ): bool
    {
        $ret = false;

        if (
            $wordpressUserId &&
            $audiencePlayerProductId &&
            ($audiencePlayerUserId = $this->fetchWordpressUserProperty($wordpressUserId, 'audienceplayer_user_id'))
        ) {
            try {
                $ret = $this->api->manageUserProductEntitlement($audiencePlayerUserId, $audiencePlayerProductId, $action);
            } catch (\Exception $e) {
                // Log exception
                $this->writeLog(
                    Constants::LOG_LEVEL_SYSTEM_ERROR,
                    'resource.user-sync.sync-wordpress-user-product-entitlement-to-audienceplayer',
                    'Caught exception while synchronising a product entitlement to AudiencePlayer product [' . $audiencePlayerProductId . '] of user [#' . strval($audiencePlayerUserId) . '] for user for Wordpress user [#' . strval($wordpressUserId) . ']',
                    [
                        'audienceplayer_user_id' => $audiencePlayerUserId,
                        'wordpress_user_id' => $wordpressUserId,
                        'audienceplayer_product_id' => $audiencePlayerProductId,
                        'error_msg' => $e->getMessage(),
                        'error_code' => $e->getCode(),
                    ]
                );
            }
        }

        return $ret;
    }

    /**
     * @param $email
     * @return bool
     */
    public function isAudiencePlayerEmailAddressAvailable($email)
    {
        $ret = true;

        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {

            if (
                // Query the API and check if new e-mail address is available
                ($result = $this->api->fetchUser(0, $email, ['id', 'email'])) &&
                ($result->getData(true)->id ?? null)
            ) {
                $ret = false;
            }

        } else {
            $ret = false;
        }

        return $ret;
    }

    // ### INTERNAL HELPERS

    /**
     * @param int $wordpressUserId
     * @return null|object
     */
    protected function fetchWordpressUserObject(int $wordpressUserId = 0)
    {
        $ret = null;

        if (
            ($user = $wordpressUserId ? \get_user_by('ID', $wordpressUserId) : \wp_get_current_user()) && ($user->ID ?? 0) &&
            ($userMeta = \get_user_meta($user->ID))
        ) {
            $ret = (object)[
                'user' => $user,
                'userMeta' => $userMeta,
            ];
        }

        return $ret;
    }

    /**
     * @param int $wordpressUserId
     * @param bool $isForceHydration
     * @return bool
     */
    protected function hydrateWordpressUser(int $wordpressUserId = 0, $isForceHydration = false): bool
    {
        $ret = false;

        // Hydrate given or current frontend user
        if (
            (
                false === $isForceHydration &&
                $this->isWordpressUserHydrated() &&
                $this->fetchWordpressUserProperty(0, 'id') === $wordpressUserId
            ) ||
            (
            $this->wordpressUserObject = $this->fetchWordpressUserObject($wordpressUserId)
            )
        ) {
            $ret = true;
        }

        return $ret;
    }

    /**
     * @param int $wordpressUserId
     * @param array $userArgs
     * @return bool
     */
    protected function updateWordpressUser(int $wordpressUserId, array $userArgs = [])
    {
        $ret = true;

        foreach ($userArgs as $property => $value) {
            $ret = $this->updateWordpressUserProperty($wordpressUserId, $property, $value) && $ret;
        }

        return $ret;
    }

    /**
     * @return bool
     */
    protected function isWordpressUserHydrated(): bool
    {
        return $this->wordpressUserObject && $this->wordpressUserObject->user && ($this->wordpressUserObject->user->ID ?? false);
    }

    /**
     * @param $wordpressUserId
     * @param string $property
     * @return array|int|null|string
     */
    protected function fetchWordpressUserProperty($wordpressUserId, string $property)
    {
        if (is_object($wordpressUserId)) {
            $wordpressUserObject = $wordpressUserId;
        } elseif ($this->isWordpressUserHydrated() && ($wordpressUserId === 0 || $wordpressUserId === ($this->wordpressUserObject->user->user->ID ?? 0))) {
            $wordpressUserObject = $this->wordpressUserObject;
        } else {
            $wordpressUserObject = $this->fetchWordpressUserObject($wordpressUserId);
        }

        switch ($property) {
            case 'id':
                $ret = intval($wordpressUserObject->user->ID ?? 0);
                break;
            case 'audienceplayer_bearer_token':
                $ret = $wordpressUserObject->userMeta['audienceplayer_bearer_token'][0] ?? '';
                break;
            case 'audienceplayer_user_id':
                $ret = intval($wordpressUserObject->userMeta['audienceplayer_user_id'][0] ?? 0);
                break;
            case 'is_newsletter_opt_in':
                // If newsletter_opt_in is not defined, return as such (null)
                $ret = isset($wordpressUserObject->userMeta['is_newsletter_opt_in'], $wordpressUserObject->userMeta['is_newsletter_opt_in'][0]) ?
                    intval($wordpressUserObject->userMeta['is_newsletter_opt_in'][0]) : null;
                break;
            case 'email':
                $ret = strval($wordpressUserObject->user->data->user_email ?? '');
                break;
            case 'name':
                $ret = trim(($wordpressUserObject->userMeta['first_name'][0] ?? '') . ' ' . ($wordpressUserObject->userMeta['last_name'][0] ?? '')) ?:
                    trim($wordpressUserObject->user->data->display_name ?? '');
                break;
            case 'roles':
                $ret = $wordpressUserObject->user->roles ?? [];
                break;
            default:
                $ret = $wordpressUserObject->userMeta[$property][0] ?? null;
                break;
        }

        return $ret;
    }

    /**
     * @param int $wordpressUserId
     * @param string $property
     * @param $value
     * @param bool $isRehydrateWordpressUser
     * @return bool
     */
    protected function updateWordpressUserProperty(int $wordpressUserId, string $property, $value, bool $isRehydrateWordpressUser = false): bool
    {
        $ret = false;

        if ($wordpressUserId = $this->fetchWordpressUserProperty($wordpressUserId, 'id')) {

            switch ($property) {
                case 'email':
                    $ret = \wp_update_user(['ID' => $wordpressUserId, 'email' => $value]);
                    break;
                case 'user_pass':
                case 'user_password':
                case 'password':
                    \wp_set_password($value, $wordpressUserId);
                    $ret = true;
                    break;
                default:
                    $ret = \update_user_meta($wordpressUserId, $property, $value);
                    break;
            }

            if (($ret = boolval($ret)) && $isRehydrateWordpressUser) {
                $this->hydrateWordpressUser($wordpressUserId, true);
            }
        }

        return $ret;
    }

    /**
     * @param int $wordpressUserId
     * @param array $roles
     * @param bool $hasAll
     * @return bool
     */
    protected function hasWordpressUserRoles(int $wordpressUserId = 0, array $roles = [], $hasAll = true)
    {
        $ret = false;

        if ($wordpressUserId && $roles) {

            $intersection = [];

            $currentRoles = $this->fetchWordpressUserProperty($wordpressUserId, 'roles');

            // check if roles have already been hydrated (which is the case for OauthClient->pseudoUsers, else execute query
            if ($currentRoles) {

                // roles are already loaded on the model
                $intersection = array_intersect($roles, $currentRoles);
            }

            if ($hasAll) {
                $ret = count($intersection) == count($roles) || false;
            } else {
                $ret = count($intersection) > 0 || false;
            }
        }

        return $ret;
    }

    /**
     * @param int $wordpressUserId
     * @return bool
     */
    protected function isWordpressSubscriberUser(int $wordpressUserId = 0)
    {
        return
            $wordpressUserId &&
            $this->hasWordpressUserRoles($wordpressUserId, ['subscriber']) &&
            count($this->fetchWordpressUserProperty($wordpressUserId, 'roles')) === 1;
    }

    /**
     * @param int $wordpressUserId
     * @return bool
     */
    protected function isWordpressAdminUser(int $wordpressUserId = 0)
    {
        return
            $wordpressUserId && (
                !$this->hasWordpressUserRoles($wordpressUserId, ['subscriber']) ||
                count($this->fetchWordpressUserProperty($wordpressUserId, 'roles')) > 1
            );
    }

    /**
     * @param int $audiencePlayerUserId
     * @param string $email
     * @return null
     */
    protected function fetchRemoteAudiencePlayerUser(int $audiencePlayerUserId, string $email)
    {
        $ret = null;

        // user does not exist locally, check if it exists remotely and if so, add the user locally
        $result = $this->api->fetchUser(intval($audiencePlayerUserId), $email, ['id', 'email', 'name']);

        // Check if user exists
        if ($result->isSuccessful() && ($user = ($result->getData(true))) && ($user->id ?? null)) {
            $ret = $user;
        }

        return $ret;
    }

    /**
     * @param string $email
     * @param string $password
     * @return null
     */
    protected function authenticateRemoteAudiencePlayerUser(string $email, string $password)
    {
        $ret = null;

        $result = $this->api->mutation
            ->UserAuthenticate($email, $password)
            ->properties(['user_id', 'user_email', 'access_token'])
            ->execute();

        // Check if user is authenticatable
        if ($result->isSuccessful() && ($auth = ($result->getData(true))) && ($auth->access_token ?? null)) {
            $ret = $auth;
        }

        return $ret;
    }

}
