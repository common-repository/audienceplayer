<?php
/**
 * @package AudiencePlayer
 * @version 0.0.1
 */
/*
Plugin Name: AudiencePlayer
Plugin URI: https://support.audienceplayer.com
Description: AudiencePlayer integration
Author: AudiencePlayer
Version: 5.0.2
Author URI: https://www.audienceplayer.com
Text Domain: audienceplayer
*/

// Load AdminPageFramework dependencies for AudiencePlayerWordpressPlugin
include(dirname(__FILE__) . '/admin-page-framework/library/admin-page-framework.php');
if (!class_exists('AudiencePlayer_AdminPageFramework')) {
    return;
}

// Load composer dependencies for AudiencePlayerWordpressPlugin
if (is_readable(__DIR__ . '/vendor/autoload.php')) {
    require __DIR__ . '/vendor/autoload.php';
}

// Load AudiencePlayerWordpressPlugin
if (is_readable(__DIR__ . '/src/AudiencePlayer/AudiencePlayerWordpressPlugin/AutoLoader.php')) {
    require __DIR__ . '/src/AudiencePlayer/AudiencePlayerWordpressPlugin/AutoLoader.php';
}

// #####################################################################################################################
// Instantiate AudiencePlayerWordpressPlugin and defer Wordpress actions
$AudiencePlayerWordpressPlugin = \AudiencePlayer\AudiencePlayerWordpressPlugin\AudiencePlayerWordpressPlugin::init();
$AudiencePlayerWordpressPlugin->registerWordpressActions();
// #####################################################################################################################
