<?php

$content = $content ?? '';

if (($args['article_id'] ?? null) && ($args['asset_id'] ?? null)) {

    $actionAuthentication = $AudiencePlayerWordpressPlugin->parseJsShortCodeAction($args['authentication_action'] ?: $AudiencePlayerWordpressPlugin->fetchLoginUrl(true));
    $actionAuthorisation = $AudiencePlayerWordpressPlugin->parseJsShortCodeAction($args['authorisation_action'] ?: '');

    if ($args['show_play_progress'] ?? false) {

        // Check if given asset has a relative play-progress value
        $assetProgressPc = intval(($args['appr'] ?? $article->appr ?? $asset->appr ?? 0) * 100);
        $playProgressElement = '<div class="article-id-' . $args['article_id'] . ' progress-bar"><div class="article-id-' . $args['article_id'] . ' progress" style="width:' . $assetProgressPc . '%"></div></div>';

    } else {
        $playProgressElement = '';
    }

    $content .=
        '<div class="audienceplayer-play-article-button ' . trim($args['class'] ?? '') . '">' .
        '<div class="wrapper">' .
        '<button class="play-article-button" onclick="window.AudiencePlayerCore.openModalPlayer(' .
        'this, ' .
        $args['article_id'] . ',' .
        $args['asset_id'] . ',' .
        $actionAuthentication . ',' .
        $actionAuthorisation .
        ');">' .
        '<i class="audienceplayer-fa play"></i>' .
        ($args['label'] ?? $AudiencePlayerWordpressPlugin->fetchTranslations('button_play')) .
        '</button>' .
        $playProgressElement .
        '</div>' .
        '</div>';

} else {
    $msg = 'argument "article_id" and/or "asset_id" is not defined';
    $AudiencePlayerWordpressPlugin->pushPublicDebugStack('[audienceplayer_shortcode_play_article_button]', $msg);
    \trigger_error('Warning in shortcode [audienceplayer_shortcode_play_article_button]: ' . $msg, E_USER_WARNING);
}
