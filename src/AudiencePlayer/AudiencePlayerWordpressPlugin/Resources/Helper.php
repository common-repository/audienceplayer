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
use Exception;

class Helper
{
    /**
     * @param $data
     * @link https://florianbrinkmann.com/en/wordpress-backend-request-3815/
     */
    public function dump($data)
    {
        if ($this->isWordpressAdminContext()) {
            echo '<div style="margin-left:180px;font-weight:bold;background-color:#000000;color:#00ff00;">' .
                \var_export($data, true) .
                '</div>' . PHP_EOL;
        } else {
            echo '<div style="font-weight:bold;background-color:#000000;color:#00ff00;">' .
                \var_export($data, true) .
                '</div>' . PHP_EOL;
        }
    }

    /**
     * @return bool
     */
    public function isWordpressWebsiteContext(): bool
    {
        return !$this->isWordpressAdminContext();
    }

    /**
     * @return bool
     */
    public function isWordpressAdminContext(): bool
    {
        $ret = false;

        /**
         * Get current URL.
         *
         * @link https://wordpress.stackexchange.com/a/126534
         */
        $currentUrl = home_url(add_query_arg(null, null));

        /**
         * Get admin URL and referrer.
         *
         * @link https://core.trac.wordpress.org/browser/tags/4.8/src/wp-includes/pluggable.php#L1076
         */
        $adminUrl = strtolower(admin_url());
        $referrer = strtolower(wp_get_referer());

        /**
         * Check if this is a admin request. If true, it
         * could also be a AJAX request from the frontend.
         */
        if (0 === strpos($currentUrl, $adminUrl)) {
            /**
             * Check if the user comes from a admin page.
             */
            if (0 === strpos($referrer, $adminUrl)) {

                $ret = true;

            } else {

                /**
                 * Check for AJAX requests.
                 *
                 * @link https://gist.github.com/zitrusblau/58124d4b2c56d06b070573a99f33b9ed#file-lazy-load-responsive-images-php-L193
                 */
                if (function_exists('wp_doing_ajax')) {
                    $ret = !wp_doing_ajax();
                } else {
                    $ret = !(defined('DOING_AJAX') && DOING_AJAX);
                }
            }
        }

        return $ret;
    }

    /**
     * @param array $userData
     * @return string
     */
    public function parseUserFullNameFromUserData(array $userData): string
    {
        return
            trim(($userData['first_name'] ?? '') . ' ' . ($userData['last_name'] ?? '')) ?:
                trim($userData['display_name'] ?? '') ?:
                    trim($userData['user_login'] ?? '');
    }

    /**
     * @param string $name
     * @param string $type
     * @param string $delimiter
     * @return string
     */
    public function parsePersonName(string $name, string $type = 'first', string $delimiter = ' ')
    {
        $arr = explode($delimiter, $name);
        $firstName = array_shift($arr);

        if ($type === 'first') {

            return ucfirst($firstName);

        } elseif ($type === 'last') {

            return ucfirst(implode(' ', $arr));

        } else {

            return $name;

        }
    }

    /**
     * @param $string
     * @param array $data
     * @param bool $isRemoveEmptyTerms
     * @return string
     */
    public function populateTemplateString($string, array $data = [], $isRemoveEmptyTerms = true)
    {
        if ($string) {

            if ($data) {
                foreach ($data as $key => $val) {
                    if (!is_object($val) && !is_iterable($val)) {
                        $string = preg_replace('/&lt;&lt;\s*' . preg_quote($key) . '\s*&gt;&gt;/i', $val, $string);
                        $string = preg_replace('/<<\s*' . preg_quote($key) . '\s*>>/i', $val, $string);
                        $string = preg_replace('/{{\s*' . preg_quote($key) . '\s*}}/i', $val, $string);
                    }
                }
            }

            if ($isRemoveEmptyTerms) {
                $string = preg_replace('/&lt;&lt;\s*[A-Za-z0-9\.-_]+\s*&gt;&gt;/i', '', $string);
                $string = preg_replace('/<<\s*[A-Za-z0-9\.-_]+\s*>>/i', '', $string);
                $string = preg_replace('/{{\s*[A-Za-z0-9\.-_]+\s*}}/i', '', $string);
            }
        }

        return strval($string);
    }

    /**
     * @param $requestData
     * @return object
     */
    public function parseGraphQLPostParameters($requestData)
    {
        $ret = (object)[
            'scope' => Globals::OAUTH_SCOPE_USER,
            'operation' => '',
            'variables' => [],
            'bearerToken' => '',
            'accessType' => Globals::OAUTH_ACCESS_AS_AGENT_USER,
            'status' => false,
        ];

        if ($ret->operation = strval(\wp_unslash($requestData['operation'] ?? ''))) {

            $ret->status = true;

            if (isset($requestData['scope']) && false !== ($key = array_search(strval($requestData['scope']), Globals::OAUTH_SCOPES))) {
                $ret->scope = Globals::OAUTH_SCOPES[$key];
            }

            if (isset($requestData['access_type']) && false !== ($key = array_search(strval($requestData['access_type']), Globals::OAUTH_ACCESS_AGENTS))) {
                $ret->accessType = Globals::OAUTH_ACCESS_AGENTS[$key];
            }

            if (isset($requestData['bearer_token']) && $requestData['bearer_token'] === preg_filter('/[a-zA-Z0-9-_.]+/', '', $requestData['bearer_token'])) {
                $ret->bearerToken = $requestData['bearer_token'];
            }

            if (isset($requestData['variables'])) {

                if (is_array($requestData['variables'])) {

                    $ret->variables = $requestData['variables'];

                } else {

                    try {
                        $ret->variables = json_decode(\wp_unslash($requestData['variables']));
                        if (!is_array($ret['variables'])) {
                            $ret->variables = [];
                        }
                    } catch (Exception $e) {
                        $ret->variables = [];
                    }
                }
            }
        }

        return $ret;
    }

    /**
     * Parse remote AudiencePlayer user arguments into Wordpress user properties
     *
     * @param $userArgs
     * @param array $forceArgs
     * @return array
     */
    public function parseWordpressUserArgs(array $userArgs, array $forceArgs = [])
    {
        $ret = [];

        $translationMatrix = [
            'user_login' => ['user_login', 'email'],
            'user_email' => ['user_email', 'email'],
            'user_pass' => ['user_pass', 'user_password', 'password'],
            'user_nicename' => ['user_nicename', 'name', 'first_name'],
            'display_name' => ['display_name', 'name', 'first_name'],
        ];

        foreach ($translationMatrix as $key => $fields) {

            foreach ($fields as $fieldKey) {
                if ($userArgs[$fieldKey] ?? null) {
                    $ret[$key] = $userArgs[$fieldKey];
                    break;
                }
            }
        }

        foreach ($forceArgs as $key => $value) {
            $ret[$key] = $value;
        }

        return $ret;
    }

    /**
     * Parse Wordpress user arguments into remote AudiencePlayer user properties
     *
     * @param $userArgs
     * @param array $forceArgs
     * @return array
     */
    public function parseAudiencePlayerUserArgs(array $userArgs, array $forceArgs = [])
    {
        $ret = [];

        $translationsMatrix = [
            'email' => ['email', 'user_email'],
            'password' => ['user_pass', 'user_password', 'password'],
        ];

        foreach ($translationsMatrix as $key => $fields) {
            foreach ($fields as $fieldKey) {
                if ($userArgs[$fieldKey] ?? null) {
                    $ret[$key] = $userArgs[$fieldKey];
                    break;
                }
            }
        }

        foreach ($userArgs as $key => $value) {
            if (
                isset(Constants::USER_METADATA_SYNC_KEYS[$key]) &&
                ($typeCast = Constants::USER_METADATA_SYNC_KEYS[$key])
            ) {
                if ($key === 'gender') {
                    // only  parse gender value if it is a property enum value
                    if (in_array($value, ['male', 'female', 'non_binary', 'unknown'])) {
                        $ret[$key] = ['value' => strval($value), 'type' => 'enum'];
                    }
                } elseif ($key === 'is_newsletter_opt_in') {
                    $ret[$key] = ['value' => (boolval(is_string($value) ? trim(preg_replace('/^(false)$/i', '0', $value)) : $value) ? 'true' : 'false'), 'type' => 'bool'];
                } elseif ($key === 'phone' && $this->validatePhoneNumberFormat(strval($value))) {
                    $ret[$key] = strval($value);
                } else {
                    $ret[$key] = $this->typeCastValue($value, $typeCast);
                }
            }
        }

        // do not sync name if it is the same as the e-mail address
        if (isset($ret['name'], $ret['email']) && $ret['name'] === $ret['email']) {
            unset($ret['name']);
        }

        // parse name from first/last name args
        if (!isset($ret['name']) && ($value = trim(($userArgs['first_name'] ?? '') . ' ' . ($userArgs['last_name'] ?? '')))) {
            $ret['name'] = $value;
        }

        // add required args
        foreach ($forceArgs as $key => $value) {
            $ret[$key] = $value;
        }

        return $ret;
    }

    /**
     * Generates a slug (url-compatible string) from input string. E.g. replaces accented characters with
     * equivalent ascii characters, whitespaces and dashes with underscores.
     *
     * @param string $string
     * @param string|null $regexNonAllowedChars
     * @param bool $sanitizeString
     * @param bool $forceLowerCase
     * @return string
     */
    public function sluggifyString(string $string, string $regexNonAllowedChars = null, bool $sanitizeString = true, bool $forceLowerCase = true): string
    {
        $string = trim($string);
        $regexNonAllowedChars = $regexNonAllowedChars ?: '/[^A-Za-z0-9\-\.]/i';

        if ($sanitizeString) {
            $search = explode(',', ' ,_,ç,æ,œ,á,é,í,ó,ú,à,è,ì,ò,ù,ä,ë,ï,ö,ü,ÿ,â,ê,î,ô,û,å,ø,Ø,Å,Á,À,Â,Ä,È,É,Ê,Ë,Í,Î,Ï,Ì,Ò,Ó,Ô,Ö,Ú,Ù,Û,Ü,Ÿ,Ç,Æ,Œ');
            $replace = explode(',', '-,-,c,ae,oe,a,e,i,o,u,a,e,i,o,u,a,e,i,o,u,y,a,e,i,o,u,a,o,O,A,A,A,A,A,E,E,E,E,I,I,I,I,O,O,O,O,U,U,U,U,Y,C,AE,OE');
            $string = str_replace($search, $replace, $string);
        }

        return $forceLowerCase ?
            strtolower(preg_replace($regexNonAllowedChars, '', $string)) :
            preg_replace($regexNonAllowedChars, '', $string);
    }

    /**
     * Get a subset of the items from the given array.
     *
     * @param $array
     * @param $keys
     * @param bool $isMatchKeys
     * @return array
     */
    public function arrayOnly($array, $keys, $isMatchKeys = false)
    {
        return $isMatchKeys ?
            array_intersect_key($array, (array)$keys) :
            array_intersect_key($array, array_flip((array)$keys));
    }

    /**
     * Get inverse of a subset of the items from the given array.
     *
     * @param $array
     * @param $keys
     * @param bool $isMatchKeys
     * @return array
     */
    public function arrayExcept($array, $keys, $isMatchKeys = false)
    {
        return $isMatchKeys ?
            array_diff_key($array, (array)$keys) :
            array_diff_key($array, array_flip((array)$keys));
    }

    /**
     * @param $url
     * @return bool|mixed|string
     */
    public function validateAbsoluteOrRelativeUrl(string $url)
    {
        if (
            ($ret = filter_var(trim($url), FILTER_VALIDATE_URL)) ||
            (substr(trim($url), 0, 1) === '/')
        ) {
            return trim($url);
        } else {
            return false;
        }
    }

    /**
     * Validate phone number format
     * - may contain plus-sign-prefix
     * - must contain only numbers (minimum 3, spaces are allowed)
     *
     * @param $phoneNumber
     * @return bool
     */
    public function validatePhoneNumberFormat($phoneNumber): bool
    {
        return preg_match('/^\+?([0-9]+\s*){3,}$/', $phoneNumber) || false;
    }

    /**
     * @param string $prefix
     * @param string $string
     * @return bool|string
     */
    public function removeStringPrefix(string $prefix, string $string)
    {
        return (strpos($string, $prefix) === 0) ? substr($string, strlen($prefix)) : $string;
    }

    /**
     * @param string $suffix
     * @param string $string
     * @return bool|string
     */
    public function removeStringSuffix(string $suffix, string $string)
    {
        return (strpos($string, $suffix) === strlen($string) - strlen($suffix)) ? substr($string, 0, -strlen($suffix)) : $string;
    }

    /**
     * @param string $string
     * @param bool $isEscapeSingleQuotes
     * @param bool $isEscapeDoubleQuotes
     * @param bool $isConvertToUnicode
     * @param bool $isEscapeLineBreaks
     * @return mixed
     */
    public function sanitiseJsString(string $string, bool $isEscapeSingleQuotes = true, bool $isEscapeDoubleQuotes = false, bool $isConvertToUnicode = false, bool $isEscapeLineBreaks = true)
    {
        $searchReplace = [];

        if ($isEscapeSingleQuotes) {
            $searchReplace['\''] = $isConvertToUnicode ? '&#039;' : '\\\'';
        }

        if ($isEscapeDoubleQuotes) {
            $searchReplace['"'] = $isConvertToUnicode ? '&#034;' : '\"';
        }

        if ($isEscapeLineBreaks) {
            $string = str_replace(PHP_EOL, '\n', $string);
        }

        return str_replace(array_keys($searchReplace), array_values($searchReplace), $string);
    }

    public function sanitiseJsAttributeText($string, $isEscapeSingleQuotes = false, $isEscapeDoubleQuotes = false)
    {
        $matrix = [
            '\\' => '',
            '&#92;' => '',
            '"' => $isEscapeDoubleQuotes ? '\&#34;' : '&#34;',
            '&quot;' => $isEscapeDoubleQuotes ? '\&#34;' : '&#34;',
            '\'' => $isEscapeSingleQuotes ? '\&#039;' : '&#039;',
            '&apos;' => $isEscapeSingleQuotes ? '\&#039;' : '&#039;',
            PHP_EOL => '\n',
        ];

        foreach ($matrix as $search => $replace) {
            $string = str_replace($search, $replace, $string);
        }

        return $string;
    }

    /**
     * @param $value
     * @param string $type
     * @return array|bool|float|int|string
     */
    public function typeCastValue($value, $type = 'auto')
    {
        // Determine type automatically
        if ($type === 'auto') {
            if (is_numeric($value)) {
                $value = false === strstr($value, '.') ? intval($value) : floatval($value);
            }
            $type = gettype($value);
        }

        // Parse given type
        if ($type === 'array') {

            if (gettype($value) === 'string') {
                $value = explode(',', $value) ?: [];
            }

            foreach ($value as $k => $v) {
                $value[$k] = $this->typeCastValue($v);
            }

        } elseif ($type === 'string') {

            $value = strval($value);

        } elseif ($type === 'bool' || $type === 'boolean') {

            $value = !in_array($value, ['', '0', 'false', false, null, 0], true);

        } elseif ($type === 'int' || $type === 'integer') {

            $value = intval($value);

        } elseif ($type === 'float' || $type === 'double') {

            $value = floatval($value);
        }

        return $value;
    }

    /**
     * @param string $fallbackLocale
     * @param int $length
     * @return string
     */
    public function getLocale($fallbackLocale = 'en', $length = 2): string
    {
        $locale = strval(\get_locale()) ?: $fallbackLocale;
        return $length ? substr($locale, 0, $length) : $locale;
    }

    /**
     * @param string $fallbackIpAddress
     * @return string
     */
    public function getIpAddress(string $fallbackIpAddress = ''): string
    {
        //$allowedKeys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        $allowedKeys = ['REMOTE_ADDR'];

        try {

            foreach ($allowedKeys as $key) {

                $ipChain = explode(',', ($_SERVER[$key] ?? ''));

                if ($ipChain[0] ?? null) {
                    return $ipChain[0];
                }
            }

        } catch (Exception $e) {
            // silent fail
        }

        return $fallbackIpAddress;
    }

}
