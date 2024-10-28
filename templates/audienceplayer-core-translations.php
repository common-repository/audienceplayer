<?php
/*
 * Define the plugin translations which are used in the shortcodes (e.g. error/warning messages, button translations etc.)
 * in this PHP-only template.
 *
 * When the plugin is initialised in Wordpress, the necessary plugin translations are loaded under text domain "audienceplayer".
 * They can be translated/customised according Wordpress best practices. E.g. see
 * - https://developer.wordpress.org/reference/functions/__/
 * - https://developer.wordpress.org/themes/functionality/internationalization/
 *
 * For convenience, in this file the plugin translations are overwritten with the the customised translations from the AudiencePlayer CMS.
 * Depending on the (multi)language strategy of your website and/or theme, this template can be further customised, e.g.:
 * - remove "$AudiencePlayerWordpressPlugin->hydrateProjectTranslations();" to fall back to the default text domain translations
 * - implement your custom setup by using "$AudiencePlayerWordpressPlugin->setTranslations()"
 */

// Overwrite the default plugin translations under text domain "audienceplayer", with the translations from AudiencePlayer CMS.
$AudiencePlayerWordpressPlugin->hydrateProjectTranslations();


/*
// # EXAMPLES

// ## Fetch the default plugin translations under text domain "audienceplayer"
$AudiencePlayerWordpressPlugin->fetchDefaultTranslations();

// ## Fetch the currently hydrated plugin translations
$AudiencePlayerWordpressPlugin->fetchTranslations(null, '', [], false);                                 // fetch all unparsed as array
$AudiencePlayerWordpressPlugin->fetchTranslations('button_active', 'fallback value', $variables, true); // fetch parsed single translation

// ## Overwrite the currently hydrated plugin translations with your own implementation
$AudiencePlayerWordpressPlugin->setTranslations([
    'button_activate' => 'Activate device',
    'button_cancel' => 'Cancel',
    'button_close' => 'Close',
    'button_next' => 'Continue',
     ...
]);
*/
