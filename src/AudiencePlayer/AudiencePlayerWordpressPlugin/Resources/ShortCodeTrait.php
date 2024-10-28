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

use AudiencePlayer\AudiencePlayerWordpressPlugin\AudiencePlayerWordpressPlugin;

trait ShortCodeTrait
{
    private $placeholderGridImageUrl;

    /**
     * @param $self
     */
    public function initShortCodes($self)
    {
        $this->loadCoreScripts($self);
        $this->loadShortCodes($self);
    }

    /**
     * @param $self
     */
    private function loadCoreScripts($self)
    {
        \add_action('init', function () use ($self) {

            if ($self->helper->isWordpressWebsiteContext()) {

                echo $this->renderPluginTemplateSection('audienceplayer-core-translations', []);

                // JavaScript
                $self->enqueueTemplateFileScript('audienceplayer-core.js');
                $self->enqueueTemplateFileScript('audienceplayer-shortcodes.js');
                $self->enqueueTemplateFileScript('slick.min.js');

                \wp_register_script('video.js', $self->fetchEmbedPlayerLibraryBaseUrl() . '/video.js', [], self::fetchPluginCacheString());
                \wp_enqueue_script('video.js', $self->fetchEmbedPlayerLibraryBaseUrl() . '/video.js', [], self::fetchPluginCacheString());

                // CSS
                $self->enqueueTemplateFileScript('font-awesome.css');
                $self->enqueueTemplateFileScript('audienceplayer-shortcodes.css');
                $self->enqueueTemplateFileScript('slick.css');
                $self->enqueueTemplateFileScript('slick-theme.css');

                \wp_register_script('style.css', $self->fetchEmbedPlayerLibraryBaseUrl() . '/style.css', [], self::fetchPluginCacheString());
                \wp_enqueue_style('style.css', $self->fetchEmbedPlayerLibraryBaseUrl() . '/style.css', [], self::fetchPluginCacheString());
                \wp_register_script('embed-player.css', $self->fetchEmbedPlayerLibraryBaseUrl() . '/embed-player.css', [], self::fetchPluginCacheString());
                \wp_enqueue_style('embed-player.css', $self->fetchEmbedPlayerLibraryBaseUrl() . '/embed-player.css', [], self::fetchPluginCacheString());
            }
        });

        // Runs just before starting buffer output
        //\add_action('template_redirect', function () {});

        // Render basic javascript
        \add_action('wp_head', function () use ($self) {
            if ($self->helper->isWordpressWebsiteContext()) {
                echo $this->renderPluginTemplateSection('audienceplayer-core-javascript', []);
            }
        });

        // Load core html templates immediately after body-tag is opened
        \add_action('wp_body_open', function () use ($self) {
            if ($self->helper->isWordpressWebsiteContext()) {
                echo $this->renderPluginTemplateSection('audienceplayer-core-html', []);
            }
        }, -1);
    }

    /**
     * @param $self
     */
    private function loadShortCodes($self)
    {
        // SHORTCODE: [audienceplayer_article_carousel]
        \add_shortcode('audienceplayer_article_carousel', function ($attributes, $content = null, $tag = null) use ($self) {
            $args = $self->parseShortCodeAttributes($attributes, ['is_display_as_carousel' => true]);
            return $self->renderPluginTemplateSection('audienceplayer-shortcode-article-carousel-grid', $args);
        });

        // SHORTCODE: [audienceplayer_article_grid]
        \add_shortcode('audienceplayer_article_grid', function ($attributes, $content = null, $tag = null) use ($self) {
            $args = $self->parseShortCodeAttributes($attributes, ['is_display_as_carousel' => false, 'show_play_progress' => true]);
            return $self->renderPluginTemplateSection('audienceplayer-shortcode-article-carousel-grid', $args);
        });

        // SHORTCODE: [audienceplayer_play_article_button]
        \add_shortcode('audienceplayer_play_article_button', function ($attributes, $content = null, $tag = null) use ($self) {
            $args = $self->parseShortCodeAttributes($attributes, ['show_play_progress' => false]);
            return $self->renderPluginTemplateSection('audienceplayer-shortcode-play-article-button', $args);
        });

        // SHORTCODE: [audienceplayer_embed_article]
        \add_shortcode('audienceplayer_embed_article', function ($attributes, $content = null, $tag = null) use ($self) {
            $args = $self->parseShortCodeAttributes($attributes);
            return $self->renderPluginTemplateSection('audienceplayer-shortcode-embed-article', $args);
        });

        // SHORTCODE: [audienceplayer_article_detail]
        \add_shortcode('audienceplayer_article_detail', function ($attributes, $content = null, $tag = null) use ($self) {
            $args = $self->parseShortCodeAttributes($attributes, ['show_play_progress' => true]);
            return $self->renderPluginTemplateSection('audienceplayer-shortcode-article-detail', $args);
        });

        // SHORTCODE: [audienceplayer_purchase_product_button]
        \add_shortcode('audienceplayer_purchase_product_button', function ($attributes, $content = null, $tag = null) use ($self) {
            $args = $self->parseShortCodeAttributes($attributes);
            return $self->renderPluginTemplateSection('audienceplayer-shortcode-purchase-product-button', $args);
        });

        // SHORTCODE: [audienceplayer_purchase_subscriptions]
        \add_shortcode('audienceplayer_purchase_subscriptions', function ($attributes, $content = null, $tag = null) use ($self) {
            $args = $self->parseShortCodeAttributes($attributes);
            return $self->renderPluginTemplateSection('audienceplayer-shortcode-purchase-subscriptions', $args);
        });

        // SHORTCODE: [audienceplayer_user_account]
        \add_shortcode('audienceplayer_user_account', function ($attributes, $content = null, $tag = null) use ($self) {
            $args = $self->parseShortCodeAttributes($attributes);
            return $self->renderPluginTemplateSection('audienceplayer-shortcode-user-account', $args);
        });

        // SHORTCODE: [audienceplayer_device_pairing]
        \add_shortcode('audienceplayer_device_pairing', function ($attributes, $content = null, $tag = null) use ($self) {
            $args = $self->parseShortCodeAttributes($attributes);
            return $self->renderPluginTemplateSection('audienceplayer-shortcode-device-pairing', $args);
        });
    }

    /**
     * @param $attributes
     * @param array $forceAttributes
     * @return array
     */
    private function parseShortCodeAttributes($attributes, array $forceAttributes = []): array
    {
        $shortCodeDefaults = [
            'id' => 0,
            'ancestor_id' => null,
            'product_id' => null,
            'article_id' => null,
            'article_url_slug' => null,
            'article_type_base_url_slug' => null,
            'article_series_base_url_slug' => null,
            'ancestor_url_slug' => null,
            'category_id' => null,
            'category_url_slug' => null,
            'recommended_subscription_id' => 0,
            'recommended_subscription_label' => '',
            'subscription_id' => 0,
            'asset_id' => null,
            'appr' => null,
            'autoplay' => false,
            'aspect_ratio' => '16x9', // @TODO: const
            'poster_image_url' => '',
            'locale' => '',
            'label' => '',
            'click_action' => $this->loadConfig('article_carousel_grid_default_click_action'), // e.g.: articles/{{ article_id }}
            'click_action_path_prefix' => '',
            'after_purchase_action' => '', // e.g. a url
            'authentication_action' => '', // e.g.: articles/{{ article_id }}
            'authorisation_action' => '', // e.g.: articles/{{ article_id }}
            'class' => 'audienceplayer-' . $this->helper->sluggifyString(\wp_get_theme()),
            'width' => 640, // 384
            'height' => 360, // 216
            'meta_keys' => [],
            'limit' => 0,
            'offset' => 0,
            'show_claim_device' => true,
            'show_descendant_articles' => true,
            'show_description' => true,
            'show_notifiable' => false,
            'show_pagination' => true,
            'show_payment_method' => true,
            'show_payment_orders' => true,
            'show_play_progress' => true,
            'show_products' => true,
            'show_seasons' => false,
            'show_subscription_info' => true,
            'show_subscription_suspend' => true,
            'show_titles' => true,
            'show_trailer' => true,
            'show_user_devices' => true,
            'show_voucher_redeem' => true,
            'show_voucher_validation' => true,
            'subscription_ids' => [],
            'types' => [],
        ];

        $arr = \shortcode_atts(array_merge($shortCodeDefaults, $forceAttributes), $attributes);

        foreach ($arr as $key => $value) {
            // check actual type versus expected type as defined in defaults
            if (isset($shortCodeDefaults[$key]) && ($type = gettype($shortCodeDefaults[$key])) && gettype($value) === 'string') {
                if ($type === 'array' || $type === 'boolean' || $type === 'bool') {
                    $arr[$key] = $this->helper->typeCastValue($arr[$key], $type);
                } else {
                    $arr[$key] = $this->helper->typeCastValue($arr[$key], 'auto');
                }
            }
        }

        return $arr;
    }

    /**
     * Locate a given template file in the theme folder with fallback to plugin folders
     * @param $templateFileName
     * @param string $templatePath
     * @param string $defaultPath
     * @return string
     */
    private function locatePluginTemplateSection($templateFileName, $templatePath = '', $defaultPath = '')
    {
        // Set variable to search in the templates folder of theme.
        $templatePath = $templatePath ?: self::fetchPluginFolderName() . '/templates/';

        // Search template file in theme folder.
        $template = locate_template([$templatePath . $templateFileName, $templateFileName]);

        // Get plugins template file.
        if (!$template) {
            $defaultPath = $defaultPath ?: AudiencePlayerWordpressPlugin::fetchPluginPath(
                $this->helper->removeStringPrefix(self::fetchPluginFolderName() . '/', $templatePath)
            );
            $template = $defaultPath . $templateFileName;
        }

        // return apply_filters, so that 3rd parties may access this
        return $template;
    }

    /**
     * Render a given template
     * @param $templateName
     * @param array $args
     * @param string $templatePath
     * @param string $defaultPath
     * @return bool|string|void
     */
    private function renderPluginTemplateSection($templateName, $args = [], $templatePath = '', $defaultPath = '')
    {
        $content = '';

        if (is_array($args)) {
            extract($args);
        }

        $fileExtension = pathinfo($templateName, PATHINFO_EXTENSION);

        if ($fileExtension !== 'html') {
            $fileExtension = 'php';
        }

        $templateFileName = $this->helper->removeStringSuffix('.' . $fileExtension, $templateName) . '.' . $fileExtension;
        $templateFile = $this->locatePluginTemplateSection($templateFileName, $templatePath, $defaultPath);

        if (!file_exists($templateFile)) {
            _doing_it_wrong(__FUNCTION__, sprintf('<code>%s</code> does not exist.', $templateFile), self::fetchPluginVersion());
            return;
        }

        if ($fileExtension === 'html') {

            $content = file_get_contents($templateFile);

        } else {

            // Make placeholder image available
            if (!($args['placeholder_image_url'] ?? null)) {
                if (!$this->placeholderGridImageUrl) {
                    $this->placeholderGridImageUrl = $this->locatePluginTemplateUrl('article-carousel-grid-item-placeholder-' . ($args['aspect_ration'] ?? '16x9') . '.png', self::fetchPluginFolderName() . '/templates/images/');
                }
                $args['placeholder_image_url'] = $this->placeholderGridImageUrl;
            }

            // Make main plugin instantiation available
            $AudiencePlayerWordpressPlugin = self::init();

            include $templateFile;
        }

        return $content;
    }

    /**
     * @param $templateName
     * @param string $templatePath
     * @param string $defaultPath
     * @return string
     */
    private function locatePluginTemplateUrl($templateName, $templatePath = '', $defaultPath = '')
    {
        $templatePath = $templatePath ?: self::fetchPluginFolderName() . '/templates/';
        $file = $this->locatePluginTemplateSection($templateName, $templatePath, $defaultPath);
        return (strstr($file, 'themes') ? \get_template_directory_uri() : \plugins_url()) . '/' . $templatePath . $templateName;
    }

    /**
     * Enqueue a given template file script (JS or CSS) located in the theme folder with fallback to the plugin folder
     * @param $templateDependencyFileName
     * @param string $templatePath
     * @param string $defaultPath
     */
    private function enqueueTemplateFileScript($templateDependencyFileName, $templatePath = '', $defaultPath = '')
    {
        $pathInfo = pathinfo($templateDependencyFileName);
        $templatePath = $templatePath ?: self::fetchPluginFolderName() . '/templates/' . ($pathInfo['extension'] ?? '') . '/';

        if (
            ($templateDependencyFile = $this->locatePluginTemplateSection($templateDependencyFileName, $templatePath)) &&
            file_exists($templateDependencyFile)
        ) {

            $templateUrl = (strstr($templateDependencyFile, 'themes') ? \get_template_directory_uri() : \plugins_url()) . '/' . $templatePath . $templateDependencyFileName;

            if ($pathInfo['extension'] === 'js') {

                \wp_register_script($templateDependencyFileName, $templateUrl, ['jquery'], self::fetchPluginVersion());
                \wp_enqueue_script($templateDependencyFileName, $templateUrl, ['jquery'], self::fetchPluginVersion());

            } elseif ($pathInfo['extension'] === 'css') {

                \wp_register_style($templateDependencyFileName, $templateUrl, [], self::fetchPluginVersion());
                \wp_enqueue_style($templateDependencyFileName, $templateUrl, [], self::fetchPluginVersion());
            }
        }
    }

}
