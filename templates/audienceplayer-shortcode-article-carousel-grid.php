<?php

$content = $content ?? '';
$scriptContent = '';

// By default query all types that typically hold video content
$articleTypes = $args['types'] ?: [
    \AudiencePlayer\AudiencePlayerApiClient\Resources\Globals::ARTICLE_TYPE_VIDEO,
    \AudiencePlayer\AudiencePlayerApiClient\Resources\Globals::ARTICLE_TYPE_EPISODE,
    \AudiencePlayer\AudiencePlayerApiClient\Resources\Globals::ARTICLE_TYPE_FILM,
    \AudiencePlayer\AudiencePlayerApiClient\Resources\Globals::ARTICLE_TYPE_SERIES,
];

if ($args['category_id']) {
    $q = $AudiencePlayerWordpressPlugin->api()->query->ArticleList(intval($args['category_id']), $articleTypes);
    $componentId = 'c' . $args['category_id'];
} elseif ($args['ancestor_id']) {
    $q = $AudiencePlayerWordpressPlugin->api()->query->ArticleList(null, $articleTypes)->arguments(['ancestor_id' => intval($args['ancestor_id'])]);
    $componentId = 'a' . $args['ancestor_id'];
} else {
    $q = null;
    $componentId = 0;
}

if ($q && $componentId) {

    $paginationOffset = intval($_REQUEST['ap_pagination_offset'] ?? $args['offset']);
    $paginationLimit = intval($_REQUEST['ap_pagination_limit'] ?? $args['limit']);

    // Set pagination
    if ($paginationOffset || $paginationLimit) {
        $q->paginate($paginationLimit, $paginationOffset);
    }

    $result = $q
        ->properties([
            'id',
            'name',
            'type',
            'url_slug',
            'metas{key,value}',
            'images{base_url file_name aspect_ratio_profile}',
            'assets{id linked_type screenshots{base_url file_name aspect_ratio_profile}}',
        ])
        ->execute();

    if ($result->isSuccessful() && ($data = $result->getData(true))) {

        $isDisplayAsCarousel = $args['is_display_as_carousel'] ?? false;

        $actionAuthentication = $AudiencePlayerWordpressPlugin->parseJsShortCodeAction($args['authentication_action'] ?: $AudiencePlayerWordpressPlugin->fetchLoginUrl(true));
        $actionAuthorisation = $AudiencePlayerWordpressPlugin->parseJsShortCodeAction($args['authorisation_action'] ?: '');

        $content .= '<div class="audienceplayer-article-carousel ' . ($isDisplayAsCarousel ? '' : 'matrix') . ' ' . trim($args['class'] ?? '') . '">';

        $content .= '<div id="audience-player-article-carousel-' . $componentId . '" class="carousel-wrapper" data-slick=\'{"slidesToShow": 6, "slidesToScroll": 4}\'>';

        foreach ($data->items as $article) {

            foreach ($article->assets as $asset) {

                if ($asset->linked_type !== 'trailer') {

                    // Check if given asset has a relative play-progress value
                    $assetProgressPc = intval(($article->appr ?? $asset->appr ?? 0) * 100);

                    // Prepare the properly scaled image (leveraging AudiencePlayer image-scaling profiles "/{width}x{height}/")
                    if (($args['aspect_ratio'] ?? '16x9') === '16x9') {
                        $aspectRatio = '16x9';
                        $imageWidth = 320;
                        $imageHeight = 180;
                        $imageResolution = '/320x180/';
                    } else {
                        $aspectRatio = '2x3';
                        $imageWidth = 180;
                        $imageHeight = 270;
                        $imageResolution = '/180x270/';
                    }

                    if (
                        ($image = $AudiencePlayerWordpressPlugin->fetchImageWithAspectRatio($article->images ?? [], $aspectRatio)) ||
                        ($image = $AudiencePlayerWordpressPlugin->fetchImageWithAspectRatio($asset->screenshots ?? [], $aspectRatio))
                    ) {
                        $imageElement = '<img src="' . $image->base_url . $imageResolution . $image->file_name . '" border="0" width="' . $imageWidth . '" height="' . $imageHeight . '" />';

                    } elseif ($args['placeholder_image_url'] ?? null) {

                        $imageElement = '<img src="' . $args['placeholder_image_url'] . '" border="0" width="' . $imageWidth . '" height="' . $imageHeight . '" />';
                    }

                    $clickActionVars = [
                        'category_id' => $args['category_id'],
                        'category_url_slug' => $args['category_url_slug'],
                        'ancestor_id' => $args['ancestor_id'],
                        'ancestor_url_slug' => $args['ancestor_url_slug'],
                        'article_id' => $article->id,
                        'article_url_slug' => $article->url_slug,
                        'article_type_base_url_slug' => $AudiencePlayerWordpressPlugin->fetchArticleTypeBaseSlug($article->type),
                        'article_series_base_url_slug' => $AudiencePlayerWordpressPlugin->fetchArticleTypeBaseSlug('series'),
                        'asset_id' => $asset->id,
                        'locale' => $args['locale'] ?: $AudiencePlayerWordpressPlugin->helper()->getLocale(),
                    ];

                    // Prepare click action [default: open AudiencePlayerCore EmbedPlayer, fallback: execute given url/javascript action]
                    $clickActionPrefix = ($args['click_action_path_prefix'] ?? null) ?
                        trim(trim($AudiencePlayerWordpressPlugin->helper()->populateTemplateString($args['click_action_path_prefix'], $clickActionVars, true)), '/') :
                        '';
                    $clickActionPrefix = $clickActionPrefix ? $clickActionPrefix . '/' : $clickActionPrefix;

                    switch (($args['click_action'] ?? '') ?: 'modal') {

                        case 'auto':
                            $clickAction = $clickActionPrefix . $AudiencePlayerWordpressPlugin->fetchArticleTypeBaseSlug($article->type) . '/' . $article->url_slug;

                            if ($article->type === 'episode' && $args['ancestor_url_slug']) {
                                $clickAction = $clickActionPrefix . $AudiencePlayerWordpressPlugin->fetchArticleTypeBaseSlug('series') . '/' . $args['ancestor_url_slug'] . '/' . $clickAction;
                            }

                            $clickActionLink = '<a href="/' . $clickAction . '"><i class="audienceplayer-fa open"></i></a>';
                            break;

                        case 'modal':
                            $clickActionLink = '<a href="javascript:void(0);" onclick="window.AudiencePlayerCore.openModalPlayer(' .
                                'this,' .
                                $article->id . ',' .
                                $asset->id . ',' .
                                $actionAuthentication . ',' .
                                $actionAuthorisation .
                                ');"><i class="audienceplayer-fa play-circle"></i></a>';
                            break;

                        default:
                            $clickActionLink = '<a href="' . $AudiencePlayerWordpressPlugin->helper()->populateTemplateString($clickActionPrefix . $args['click_action'], $clickActionVars, true) . '"><i class="audienceplayer-fa open"></i></a>';
                            break;
                    }

                    // Prepare title to be displayed in the grid item
                    $titleElement = (isset($args['show_titles']) && $args['show_titles']) ?
                        '<div class="title">' . $AudiencePlayerWordpressPlugin->fetchMetaValueForKey($article->metas, 'title') . '</div>' : '';

                    $playProgressElement = (($args['show_play_progress'] ?? false) ?
                        '<div class="article-id-' . $article->id . ' progress-bar"><div class="article-id-' . $article->id . ' progress" style="width:' . $assetProgressPc . '%"></div></div>' : '');

                    // Render the grid item
                    $content .=
                        '<div class="grid-item article-id-' . $article->id . ' article-type-' . $article->type . '">' .
                        '<div class="image">' .
                        '<div class="action-link">' . $clickActionLink . '</div>' .
                        $imageElement .
                        $playProgressElement .
                        '</div>' .
                        $titleElement .
                        '</div>';

                    break 1;
                }
            }
        }

        if ($isDisplayAsCarousel) {

            // Initialise Slick carousel
            $scriptContent .= '<script>jQuery(function ($) {$("#audience-player-article-carousel-' . $componentId . '").slick(window.AudiencePlayerCore.CONFIG.slickCarouselConfig);});</script>';

        } elseif (
            $args['show_pagination'] &&
            ($pagination = $data->pagination ?? null) &&
            $pagination->page_count > 1
        ) {
            $content .= '<div class="paginator">';

            for ($i = 1; $i <= $pagination->page_count; $i++) {
                if ($i === $pagination->page_current) {
                    $content .= '<div class="bullet"><i class="audienceplayer-fa pagination-bullet-active"></i></div>';
                } else {
                    $paginationOffset = $pagination->count * ($i - 1);
                    $linkElement = 'javascript:window.location.replace(window.AudiencePlayerCore.fetchCurrentUrl({' .
                        'ap_pagination_offset:' . $paginationOffset . ',' .
                        'ap_pagination_limit:' . $paginationLimit .
                        '}));';
                    $content .= '<div class="bullet"><a href="' . $linkElement . '"><i class="audienceplayer-fa pagination-bullet"></i></a></div>';
                }
            }

            $content .= '</div>';
        }

        // CLOSE: class="audienceplayer-article-carousel
        $content .= '</div>';

        // CLOSE: id="audience-player-article-carousel-$componentId" class="carousel-wrapper"
        $content .= '</div>';

    } elseif (($code = $result->getFirstErrorCode()) > 500) {
        $msg = 'query error occurred, code: ' . $code;
        $AudiencePlayerWordpressPlugin->pushPublicDebugStack('[audienceplayer_shortcode_article_carousel_grid]', $msg);
        \trigger_error('Warning in shortcode [audienceplayer_shortcode_article_carousel_grid]: ' . $msg, E_USER_WARNING);
    }

} else {
    $msg = 'missing or invalid arguments, query could not be constructed.';
    $AudiencePlayerWordpressPlugin->pushPublicDebugStack('[audienceplayer_shortcode_article_carousel_grid]', $msg);
    \trigger_error('Warning in shortcode [audienceplayer_shortcode_article_carousel_grid]: ' . $msg, E_USER_WARNING);
}

$content .= $scriptContent;

$AudiencePlayerWordpressPlugin->pushPublicDebugStack('[audienceplayer_shortcode_article_carousel_grid]', $AudiencePlayerWordpressPlugin->api()->fetchLastOperationQuery(true), $AudiencePlayerWordpressPlugin->api()->fetchLastOperationResult());
