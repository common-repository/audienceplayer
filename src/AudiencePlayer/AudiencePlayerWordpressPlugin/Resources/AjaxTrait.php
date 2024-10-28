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

trait AjaxTrait
{
    public function audienceplayerApiCall()
    {
        try {

            $graphqlParameters = $this->helper->parseGraphQLPostParameters($_REQUEST);

            if ($graphqlParameters->status) {

                $result = $this->api->executeRawGraphQLCall(
                    $graphqlParameters->scope,
                    $graphqlParameters->operation,
                    $graphqlParameters->variables,
                    true,
                    true,
                    '',
                    $graphqlParameters->bearerToken,
                    $graphqlParameters->accessType
                );

                \wp_send_json((array)$result, ($result->errors ?? false) ? Constants::STATUS_INVALID_USER_INPUT_CODE : Constants::STATUS_OK_CODE);

            } else {
                \wp_send_json(['data' => null, 'errors' => $this->api->fetchLastOperationResult(true, false)], Constants::STATUS_INVALID_USER_INPUT_CODE);
            }
        } catch (\Exception $e) {
            \wp_send_json(['data' => null, 'errors' => $this->api->fetchLastOperationResult(true, false)], Constants::STATUS_SERVER_ERROR_CODE);
        }
    }

    public function audienceplayerApiCallback()
    {
        $ret = false;

        try {

            $requestBody = json_decode(file_get_contents('php://input'));
            $requestPayload = $this->helper->sluggifyString($requestBody->payload ?? $_REQUEST['payload'] ?? '', '/[^A-Za-z0-9\-\.=]/i', true, false);

            $result = $this->api->query
                ->ClientPayloadVerify($requestPayload)
                ->execute();

            if (
                $result->isSuccessful() &&
                ($data = ($result->getData(true))) &&
                $data->status &&
                $data->payload &&
                ($payload = json_decode($data->payload)) &&
                $payload->contexts &&
                $payload->id &&
                $payload->email
            ) {
                $ret = $this->syncAudiencePlayerUserToWordpress(
                    $payload->id,
                    $payload->email,
                    (array)$payload,
                    in_array('user_delete', $payload->contexts)
                );
            } else {
                // Log exception
                $this->writeLog(
                    Constants::LOG_LEVEL_WARNING,
                    'resource.ajax-api-callback.payload-parse-error',
                    'Received payload could not be parsed',
                    [
                        'payload' => $requestPayload,
                        'result' => $result->getData(),
                    ]
                );
            }

            // return result
            \wp_send_json(['data' => $ret], $ret ? Constants::STATUS_OK_CODE : Constants::STATUS_INVALID_USER_INPUT_CODE);

        } catch (\Exception $e) {
            \wp_send_json(['data' => $ret], Constants::STATUS_SERVER_ERROR_CODE);
        }
    }

    public function audienceplayerDownloadLogs()
    {
        // Ensure admin page-section target is explicitly "audienceplayer_logs" and user is authorised
        if (
            ($_REQUEST['target'] ?? null) === 'audienceplayer_logs' &&
            ($this->isWordpressAdminUser(\get_current_user_id()) || $this->validateOauthQueryStringCredentials())
        ) {
            $this->exportLogs(intval($_REQUEST['limit'] ?? 0));
            exit;
        } else {
            \wp_send_json_error(['message' => Constants::STATUS_NOT_AUTHORISED_MSG, 'code' => Constants::STATUS_NOT_AUTHORISED_CODE], Constants::STATUS_NOT_AUTHORISED_CODE);
        }
    }

    public function audienceplayerCleanLogs()
    {
        // Ensure admin page-section target is explicitly "audienceplayer_logs" and user is authorised
        if (($this->isWordpressAdminUser(\get_current_user_id()) || $this->validateOauthQueryStringCredentials())) {

            $result = $this->cleanLogs();

            \wp_send_json_success([
                'result' => $result,
                'message' => 'Logs were successfully cleared.',
                'code' => Constants::STATUS_OK_MSG,
            ]);

        } else {
            \wp_send_json_error(['message' => Constants::STATUS_NOT_AUTHORISED_MSG, 'code' => Constants::STATUS_NOT_AUTHORISED_CODE], Constants::STATUS_NOT_AUTHORISED_CODE);
        }
    }

    public function audienceplayerSyncSession()
    {
        $ret = true;

        if (intval($_REQUEST['is_force_sync'] ?? 0)) {
            // Do not trust hydrated token and force AudiencePlayer re-authentication
            $ret = $this->syncAudiencePlayerUserAuthentication(\get_current_user_id(), [], false, true);
        }

        try {
            if ($ret) {
                $data = [
                    'token' => $this->fetchUserBearerToken(),
                    'expires_in' => $this->fetchUserBearerTokenTtl(),
                ];
                \wp_send_json(['data' => $data], Constants::STATUS_OK_CODE);
            } else {
                $resultLastOperation = $this->api->fetchLastOperationResult(true, false);
                \wp_send_json(['data' => null, 'errors' => $resultLastOperation], $resultLastOperation ? $resultLastOperation[0]->code : Constants::STATUS_SERVER_ERROR_CODE);
            }

        } catch (\Exception $e) {
            \wp_send_json(['data' => null, 'errors' => $this->api->fetchLastOperationResult(true, false)], Constants::STATUS_SERVER_ERROR_CODE);
        }
    }

    public function audienceplayerAdminUserProfileForceSync()
    {
        if ($this->isWordpressAdminUser(\get_current_user_id())) {

            if (
                ($wordpressUserId = intval($_REQUEST['user_id'] ?? 0)) &&
                $this->hydrateWordpressUser($wordpressUserId, true)
            ) {

                $userArgs = [
                    'name' => $this->fetchWordpressUserProperty($wordpressUserId, 'name'),
                    'email' => $this->fetchWordpressUserProperty($wordpressUserId, 'email'),
                ];

                if ($this->syncWordpressUserUpdateAction(Constants::ACTION_ADMIN_USER_PROFILE_UPDATE, $wordpressUserId, $userArgs)) {

                    \wp_send_json_success([
                        'audienceplayer_user_id' => $this->fetchWordpressUserProperty($wordpressUserId, 'audienceplayer_user_id'),
                        'message' => 'User was successfully synchronised with AudiencePlayer.',
                        'code' => Constants::STATUS_OK_MSG,
                    ]);

                } else {
                    \wp_send_json_error(['errors' => $this->api->fetchLastOperationResult(true, false), 'message' => Constants::STATUS_SERVER_ERROR_MSG, 'code' => Constants::STATUS_SERVER_ERROR_CODE], Constants::STATUS_SERVER_ERROR_CODE);
                }
            } else {
                \wp_send_json_error(['message' => Constants::STATUS_INVALID_USER_INPUT_MSG, 'code' => Constants::STATUS_INVALID_USER_INPUT_MSG], Constants::STATUS_INVALID_USER_INPUT_MSG);
            }
        } else {
            \wp_send_json_error(['message' => Constants::STATUS_NOT_AUTHORISED_MSG, 'code' => Constants::STATUS_NOT_AUTHORISED_CODE], Constants::STATUS_NOT_AUTHORISED_CODE);
        }
    }

}
