<?php 
/**
	Admin Page Framework v3.8.23 by Michael Uno 
	Generated by PHP Class Files Script Generator <https://github.com/michaeluno/PHP-Class-Files-Script-Generator>
	<http://en.michaeluno.jp/audienceplayer>
	Copyright (c) 2013-2020, Michael Uno; Licensed under MIT <http://opensource.org/licenses/MIT> */
class AudiencePlayer_AdminPageFramework_FieldType_system extends AudiencePlayer_AdminPageFramework_FieldType {
    public $aFieldTypeSlugs = array('system',);
    protected $aDefaultKeys = array('data' => array(), 'print_type' => 1, 'attributes' => array('rows' => 60, 'autofocus' => null, 'disabled' => null, 'formNew' => null, 'maxlength' => null, 'placeholder' => null, 'readonly' => 'readonly', 'required' => null, 'wrap' => null, 'style' => null, 'onclick' => 'this.focus();this.select()',),);
    protected function construct() {
    }
    protected function setUp() {
    }
    protected function getEnqueuingScripts() {
        return array();
    }
    protected function getEnqueuingStyles() {
        return array();
    }
    protected function getScripts() {
        return '';
    }
    protected function getIEStyles() {
        return '';
    }
    protected function getStyles() {
        return ".audienceplayer-field-system {width: 100%;}.audienceplayer-field-system .audienceplayer-input-label-container {width: 100%;}.audienceplayer-field-system textarea {background-color: #f9f9f9; width: 97%; outline: 0; font-family: Consolas, Monaco, monospace;white-space: pre;word-wrap: normal;overflow-x: scroll;}";
    }
    protected function getField($aField) {
        $_aInputAttributes = $aField['attributes'];
        $_aInputAttributes['class'].= ' system';
        unset($_aInputAttributes['value']);
        return $aField['before_label'] . "<div class='audienceplayer-input-label-container'>" . "<label for='{$aField['input_id']}'>" . $aField['before_input'] . ($aField['label'] && !$aField['repeatable'] ? "<span " . $this->getLabelContainerAttributes($aField, 'audienceplayer-input-label-string') . ">" . $aField['label'] . "</span>" : "") . "<textarea " . $this->getAttributes($_aInputAttributes) . " >" . esc_textarea($this->_getSystemInfomation($aField['value'], $aField['data'], $aField['print_type'])) . "</textarea>" . $aField['after_input'] . "</label>" . "</div>" . $aField['after_label'];
    }
    private function _getSystemInfomation($asValue = null, $asCustomData = null, $iPrintType = 1) {
        if (isset($asValue)) {
            return $asValue;
        }
        $_aOutput = array();
        foreach ($this->_getFormattedSystemInformation($asCustomData) as $_sSection => $_aInfo) {
            $_aOutput[] = $this->_getSystemInfoBySection($_sSection, $_aInfo, $iPrintType);
        }
        return implode(PHP_EOL, $_aOutput);
    }
    private function _getFormattedSystemInformation($asCustomData) {
        $_aData = $this->getAsArray($asCustomData);
        $_aData = $_aData + array('Admin Page Framework' => isset($_aData['Admin Page Framework']) ? null : AudiencePlayer_AdminPageFramework_Registry::getInfo(), 'WordPress' => $this->_getSiteInfoWithCache(!isset($_aData['WordPress'])), 'PHP' => $this->_getPHPInfo(!isset($_aData['PHP'])), 'PHP Error Log' => $this->_getErrorLogByType('php', !isset($_aData['PHP Error Log'])), 'MySQL' => isset($_aData['MySQL']) ? null : $this->getMySQLInfo(), 'MySQL Error Log' => $this->_getErrorLogByType('mysql', !isset($_aData['MySQL Error Log'])), 'Server' => $this->_getWebServerInfo(!isset($_aData['Server'])), 'Browser' => $this->_getClientInfo(!isset($_aData['Browser'])),);
        return array_filter($_aData);
    }
    private function _getSystemInfoBySection($sSectionName, $aData, $iPrintType) {
        switch ($iPrintType) {
            default:
            case 1:
                return $this->getReadableArrayContents($sSectionName, $aData, 32) . PHP_EOL;
            case 2:
                return "[{$sSectionName}]" . PHP_EOL . print_r($aData, true) . PHP_EOL;
        }
    }
    private function _getClientInfo($bGenerateInfo = true) {
        if (!$bGenerateInfo) {
            return '';
        }
        $_aBrowser = @ini_get('browscap') ? get_browser($_SERVER['HTTP_USER_AGENT'], true) : array();
        unset($_aBrowser['browser_name_regex']);
        return empty($_aBrowser) ? __('No browser information found.', 'audienceplayer') : $_aBrowser;
    }
    private function _getErrorLogByType($sType, $bGenerateInfo = true) {
        if (!$bGenerateInfo) {
            return '';
        }
        switch ($sType) {
            default:
            case 'php':
                $_sLog = $this->getPHPErrorLog(200);
            break;
            case 'mysql':
                $_sLog = $this->getMySQLErrorLog(200);
            break;
        }
        return empty($_sLog) ? $this->oMsg->get('no_log_found') : $_sLog;
    }
    static private $_aSiteInfo;
    private function _getSiteInfoWithCache($bGenerateInfo = true) {
        if (!$bGenerateInfo || isset(self::$_aSiteInfo)) {
            return self::$_aSiteInfo;
        }
        self::$_aSiteInfo = self::_getSiteInfo();
        return self::$_aSiteInfo;
    }
    private function _getSiteInfo() {
        global $wpdb;
        return array(__('Version', 'audienceplayer') => $GLOBALS['wp_version'], __('Language', 'audienceplayer') => $this->getSiteLanguage(), __('Memory Limit', 'audienceplayer') => $this->getReadableBytes($this->getNumberOfReadableSize(WP_MEMORY_LIMIT)), __('Multi-site', 'audienceplayer') => $this->getAOrB(is_multisite(), $this->oMsg->get('yes'), $this->oMsg->get('no')), __('Permalink Structure', 'audienceplayer') => get_option('permalink_structure'), __('Active Theme', 'audienceplayer') => $this->_getActiveThemeName(), __('Registered Post Statuses', 'audienceplayer') => implode(', ', get_post_stati()), 'WP_DEBUG' => $this->getAOrB($this->isDebugMode(), $this->oMsg->get('enabled'), $this->oMsg->get('disabled')), 'WP_DEBUG_LOG' => $this->getAOrB($this->isDebugLogEnabled(), $this->oMsg->get('enabled'), $this->oMsg->get('disabled')), 'WP_DEBUG_DISPLAY' => $this->getAOrB($this->isDebugDisplayEnabled(), $this->oMsg->get('enabled'), $this->oMsg->get('disabled')), __('Table Prefix', 'audienceplayer') => $wpdb->prefix, __('Table Prefix Length', 'audienceplayer') => strlen($wpdb->prefix), __('Table Prefix Status', 'audienceplayer') => $this->getAOrB(strlen($wpdb->prefix) > 16, $this->oMsg->get('too_long'), $this->oMsg->get('acceptable')), 'wp_remote_post()' => $this->_getWPRemotePostStatus(), 'wp_remote_get()' => $this->_getWPRemoteGetStatus(), __('Multibite String Extension', 'audienceplayer') => $this->getAOrB(function_exists('mb_detect_encoding'), $this->oMsg->get('enabled'), $this->oMsg->get('disabled')), __('WP_CONTENT_DIR Writeable', 'audienceplayer') => $this->getAOrB(is_writable(WP_CONTENT_DIR), $this->oMsg->get('yes'), $this->oMsg->get('no')), __('Active Plugins', 'audienceplayer') => PHP_EOL . $this->_getActivePlugins(), __('Network Active Plugins', 'audienceplayer') => PHP_EOL . $this->_getNetworkActivePlugins(), __('Constants', 'audienceplayer') => $this->_getDefinedConstants('user'),);
    }
    private function _getDefinedConstants($asCategories = null, $asRemovingCategories = null) {
        $_asConstants = $this->getDefinedConstants($asCategories, $asRemovingCategories);
        if (!is_array($_asConstants)) {
            return $_asConstants;
        }
        if (isset($_asConstants['user'])) {
            $_asConstants['user'] = array('AUTH_KEY' => '__masked__', 'SECURE_AUTH_KEY' => '__masked__', 'LOGGED_IN_KEY' => '__masked__', 'NONCE_KEY' => '__masked__', 'AUTH_SALT' => '__masked__', 'SECURE_AUTH_SALT' => '__masked__', 'LOGGED_IN_SALT' => '__masked__', 'NONCE_SALT' => '__masked__', 'COOKIEHASH' => '__masked__', 'USER_COOKIE' => '__masked__', 'PASS_COOKIE' => '__masked__', 'AUTH_COOKIE' => '__masked__', 'SECURE_AUTH_COOKIE' => '__masked__', 'LOGGED_IN_COOKIE' => '__masked__', 'TEST_COOKIE' => '__masked__', 'DB_USER' => '__masked__', 'DB_PASSWORD' => '__masked__', 'DB_HOST' => '__masked__',) + $_asConstants['user'];
        }
        return $_asConstants;
    }
    private function _getActiveThemeName() {
        if (version_compare($GLOBALS['wp_version'], '3.4', '<')) {
            $_aThemeData = get_theme_data(get_stylesheet_directory() . '/style.css');
            return $_aThemeData['Name'] . ' ' . $_aThemeData['Version'];
        }
        $_oThemeData = wp_get_theme();
        return $_oThemeData->Name . ' ' . $_oThemeData->Version;
    }
    private function _getActivePlugins() {
        $_aPluginList = array();
        $_aActivePlugins = get_option('active_plugins', array());
        foreach (get_plugins() as $_sPluginPath => $_aPlugin) {
            if (!in_array($_sPluginPath, $_aActivePlugins)) {
                continue;
            }
            $_aPluginList[] = '    ' . $_aPlugin['Name'] . ': ' . $_aPlugin['Version'];
        }
        return implode(PHP_EOL, $_aPluginList);
    }
    private function _getNetworkActivePlugins() {
        if (!is_multisite()) {
            return '';
        }
        $_aPluginList = array();
        $_aActivePlugins = get_site_option('active_sitewide_plugins', array());
        foreach (wp_get_active_network_plugins() as $_sPluginPath) {
            if (!array_key_exists(plugin_basename($_sPluginPath), $_aActivePlugins)) {
                continue;
            }
            $_aPlugin = get_plugin_data($_sPluginPath);
            $_aPluginList[] = '    ' . $_aPlugin['Name'] . ' :' . $_aPlugin['Version'];
        }
        return implode(PHP_EOL, $_aPluginList);
    }
    private function _getWPRemotePostStatus() {
        $_vResponse = $this->getTransient('apf_rp_check');
        $_vResponse = false === $_vResponse ? wp_remote_post(add_query_arg($_GET, admin_url($GLOBALS['pagenow'])), array('sslverify' => false, 'timeout' => 60, 'body' => array('apf_remote_request_test' => '_testing', 'cmd' => '_notify-validate'),)) : $_vResponse;
        $this->setTransient('apf_rp_check', $_vResponse, 60);
        return $this->getAOrB($this->_isHttpRequestError($_vResponse), $this->oMsg->get('not_functional'), $this->oMsg->get('functional'));
    }
    private function _getWPRemoteGetStatus() {
        $_vResponse = $this->getTransient('apf_rg_check');
        $_vResponse = false === $_vResponse ? wp_remote_get(add_query_arg($_GET + array('apf_remote_request_test' => '_testing'), admin_url($GLOBALS['pagenow'])), array('sslverify' => false, 'timeout' => 60,)) : $_vResponse;
        $this->setTransient('apf_rg_check', $_vResponse, 60);
        return $this->getAOrB($this->_isHttpRequestError($_vResponse), $this->oMsg->get('not_functional'), $this->oMsg->get('functional'));
    }
    private function _isHttpRequestError($mResponse) {
        if (is_wp_error($mResponse)) {
            return true;
        }
        if ($mResponse['response']['code'] < 200) {
            return true;
        }
        if ($mResponse['response']['code'] >= 300) {
            return true;
        }
        return false;
    }
    static private $_aPHPInfo;
    private function _getPHPInfo($bGenerateInfo = true) {
        if (!$bGenerateInfo || isset(self::$_aPHPInfo)) {
            return self::$_aPHPInfo;
        }
        $_oErrorReporting = new AudiencePlayer_AdminPageFramework_ErrorReporting;
        self::$_aPHPInfo = array(__('Version', 'audienceplayer') => phpversion(), __('Safe Mode', 'audienceplayer') => $this->getAOrB(@ini_get('safe_mode'), $this->oMsg->get('yes'), $this->oMsg->get('no')), __('Memory Limit', 'audienceplayer') => @ini_get('memory_limit'), __('Upload Max Size', 'audienceplayer') => @ini_get('upload_max_filesize'), __('Post Max Size', 'audienceplayer') => @ini_get('post_max_size'), __('Upload Max File Size', 'audienceplayer') => @ini_get('upload_max_filesize'), __('Max Execution Time', 'audienceplayer') => @ini_get('max_execution_time'), __('Max Input Vars', 'audienceplayer') => @ini_get('max_input_vars'), __('Argument Separator', 'audienceplayer') => @ini_get('arg_separator.output'), __('Allow URL File Open', 'audienceplayer') => $this->getAOrB(@ini_get('allow_url_fopen'), $this->oMsg->get('yes'), $this->oMsg->get('no')), __('Display Errors', 'audienceplayer') => $this->getAOrB(@ini_get('display_errors'), $this->oMsg->get('on'), $this->oMsg->get('off')), __('Log Errors', 'audienceplayer') => $this->getAOrB(@ini_get('log_errors'), $this->oMsg->get('on'), $this->oMsg->get('off')), __('Error log location', 'audienceplayer') => @ini_get('error_log'), __('Error Reporting Level', 'audienceplayer') => $_oErrorReporting->getErrorLevel(), __('FSOCKOPEN', 'audienceplayer') => $this->getAOrB(function_exists('fsockopen'), $this->oMsg->get('supported'), $this->oMsg->get('not_supported')), __('cURL', 'audienceplayer') => $this->getAOrB(function_exists('curl_init'), $this->oMsg->get('supported'), $this->oMsg->get('not_supported')), __('SOAP', 'audienceplayer') => $this->getAOrB(class_exists('SoapClient'), $this->oMsg->get('supported'), $this->oMsg->get('not_supported')), __('SUHOSIN', 'audienceplayer') => $this->getAOrB(extension_loaded('suhosin'), $this->oMsg->get('supported'), $this->oMsg->get('not_supported')), 'ini_set()' => $this->getAOrB(function_exists('ini_set'), $this->oMsg->get('supported'), $this->oMsg->get('not_supported')),) + $this->getPHPInfo() + array(__('Constants', 'audienceplayer') => $this->_getDefinedConstants(null, 'user'));
        return self::$_aPHPInfo;
    }
    private function _getWebServerInfo($bGenerateInfo = true) {
        return $bGenerateInfo ? array(__('Web Server', 'audienceplayer') => $_SERVER['SERVER_SOFTWARE'], 'SSL' => $this->getAOrB(is_ssl(), $this->oMsg->get('yes'), $this->oMsg->get('no')), __('Session', 'audienceplayer') => $this->getAOrB(isset($_SESSION), $this->oMsg->get('enabled'), $this->oMsg->get('disabled')), __('Session Name', 'audienceplayer') => esc_html(@ini_get('session.name')), __('Session Cookie Path', 'audienceplayer') => esc_html(@ini_get('session.cookie_path')), __('Session Save Path', 'audienceplayer') => esc_html(@ini_get('session.save_path')), __('Session Use Cookies', 'audienceplayer') => $this->getAOrB(@ini_get('session.use_cookies'), $this->oMsg->get('on'), $this->oMsg->get('off')), __('Session Use Only Cookies', 'audienceplayer') => $this->getAOrB(@ini_get('session.use_only_cookies'), $this->oMsg->get('on'), $this->oMsg->get('off')),) + $_SERVER : '';
    }
    }
    