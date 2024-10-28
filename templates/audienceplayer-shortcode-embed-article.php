<?php

$content = $content ?? '';

if (($args['article_id'] ?? null) && ($args['asset_id'] ?? null)) {

    $styleElement = 'style="';
    if ($args['width'] ?? 0) {
        $styleElement .= 'width:' . intval($args['width']) . 'px;';
    }
    if ($args['height'] ?? 0) {
        $styleElement .= 'height:' . intval($args['height']) . 'px;';
    }
    $styleElement .= '"';

    $content .=
        '<div class="audienceplayer-embed-article ' . trim($args['class'] ?? '') . '" ' . $styleElement . '>' .
        '<div class="wrapper">' .
        '<iframe src="' . $AudiencePlayerWordpressPlugin->fetchEmbedPlayerLibraryBaseUrl() .
        '?apiBaseUrl=' . $AudiencePlayerWordpressPlugin->fetchApiBaseUrl() .
        '&projectId=' . $AudiencePlayerWordpressPlugin->fetchProjectId() .
        '&token=' . $AudiencePlayerWordpressPlugin->fetchUserBearerToken() .
        '&articleId=' . $args['article_id'] .
        '&assetId=' . $args['asset_id'] .
        (($args['poster_image_url'] ?? null) ? '&posterImageUrl=' . $args['poster_image_url'] : '') .
        (($args['autoplay'] ?? null) ? '&autoplay=true' : '') .
        '" ' .
        'width="' . $args['width'] . '" ' .
        'height="' . $args['height'] . '" ' .
        'frameborder="0" allow="encrypted-media" allowfullscreen="true"></iframe>' .
        '</div>' .
        '</div>';

} else {
    $msg = 'argument "article_id" and/or "asset_id" is not defined';
    $AudiencePlayerWordpressPlugin->pushPublicDebugStack('[audienceplayer_shortcode_embed_article]', $msg);
    \trigger_error('Warning in shortcode [audienceplayer_shortcode_embed_article]: ' . $msg, E_USER_WARNING);
}
