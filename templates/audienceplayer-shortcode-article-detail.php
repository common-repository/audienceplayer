<?php

$content = $content ?? '';

$articleId = $args['article_id'] ?? 0;
$articleUrlSlug = $args['article_url_slug'] ?? '';

if ($articleId || $articleUrlSlug) {

    $queryProperties = [
        'id',
        'type',
        'url_slug',
        'name',
        'metas{key,value}',
        'posters{base_url file_name aspect_ratio_profile}',
        'assets{id linked_type duration screenshots{base_url file_name aspect_ratio_profile}}',
        'products{id title description description_short currency currency_symbol price call_to_action_tag}',
    ];

    if ($args['show_seasons']) {
        $queryProperties[] = 'children{id type url_slug name metas{key,value}}';
        $selectedSeasonArticleId = intval($_REQUEST['season_article_id'] ?? 0);
    } else {
        $selectedSeasonArticleId = 0;
    }

    $result = $AudiencePlayerWordpressPlugin->api()->query
        ->Article($articleId, $articleUrlSlug)
        ->properties($queryProperties)
        ->execute();

    if ($result->isSuccessful() && ($article = ($result->getData(true)))) {

        $articleId = $article->id;

        $htmlTrailerAsset = null;
        $htmlVideoAsset = null;

        foreach ($article->assets as $asset) {

            if (is_null($htmlTrailerAsset) && $asset->linked_type === 'trailer') {
                $htmlTrailerAsset = $asset;
            }

            if (is_null($htmlVideoAsset) && $asset->linked_type !== 'trailer') {
                $htmlVideoAsset = $asset;
            }
        }

        // Check if given asset has a relative play-progress value
        $assetProgressPc = intval(($article->appr ?? $htmlVideoAsset->appr ?? 0) * 100);

        // Prepare the properly scaled image (leveraging AudiencePlayer image-scaling profiles "/{width}x{height}/")
        if ($poster = $AudiencePlayerWordpressPlugin->fetchImageWithAspectRatio($article->posters ?? [], '16x9')) {
            $posterUrl = $poster->base_url . '/1280x720/' . $poster->file_name;
        } elseif ($htmlVideoAsset && ($poster = $AudiencePlayerWordpressPlugin->fetchImageWithAspectRatio($htmlVideoAsset->screenshots ?? [], '16x9'))) {
            $posterUrl = $poster->base_url . '/1280x720/' . $poster->file_name;
        } else {
            $posterUrl = $args['placeholder_image_url'];
        }

        $content .= '<div class="audienceplayer-article-detail ' . trim($args['class'] ?? '') . ' article-id-' . $articleId . ' article-type-' . $article->type . '">';
        $content .= '<div class="wrapper">';

        $content .= '<div class="header">';

        $content .= '<div class="poster" style="background-image:url(' . $posterUrl . ')"></div>';

        $content .= '<div class="title"><h1>' . $AudiencePlayerWordpressPlugin->fetchMetaValueForKey($article->metas, 'title') . '</h1></div>';

        $content .= '<div class="buttons-play">';
        if ($htmlVideoAsset) {
            $content .= \do_shortcode(
                '[audienceplayer_play_article_button' .
                ' article_id=' . $articleId .
                ' asset_id=' . $htmlVideoAsset->id .
                ' label="' . $AudiencePlayerWordpressPlugin->fetchTranslations('button_play') . '"' .
                ' appr=' . intval($article->appr ?? $htmlVideoAsset->appr ?? 0) .
                ' show_play_progress=1' .
                ']'
            );
        }
        if ($args['show_trailer'] && $htmlTrailerAsset) {
            $content .= \do_shortcode(
                '[audienceplayer_play_article_button' .
                ' article_id=' . $articleId .
                ' class=trailer' .
                ' asset_id=' . $htmlTrailerAsset->id .
                ' label="' . $AudiencePlayerWordpressPlugin->helper->sanitiseJsString($AudiencePlayerWordpressPlugin->fetchTranslations('button_play_trailer'), false, true) . '"' .
                ' show_play_progress=0' .
                ']'
            );
        }
        $content .= '</div>';

        $content .= '<div class="buttons-product">';
        if ($article->products) {
            foreach ($article->products as $product) {
                $content .= \do_shortcode(
                    '[audienceplayer_purchase_product_button' .
                    ' product_id=' . $product->id .
                    ' label="' . $AudiencePlayerWordpressPlugin->helper->sanitiseJsString($product->call_to_action_tag ?: $AudiencePlayerWordpressPlugin->fetchTranslations('button_purchase_product'), false, true) . '"' .
                    ' show_play_progress=1' .
                    ']'
                );
            }
        }
        $content .= '</div>';

        $content .= '</div>';

        if ($args['show_description']) {
            $content .= '<div class="description">' . $AudiencePlayerWordpressPlugin->fetchMetaValueForKey($article->metas, 'description') . '</div>';
        }

        $ignoreKeys = [
            'title',
            'description',
            'description_short',
            'seo_description',
            'seo_title',
            'seo_description',
            'seo_keywords',
            'seo_og_title',
            'seo_og_description',
        ];

        $keys = ($args['meta_keys'] ?? []);

        $content .= '<div class="metadata">';
        foreach ($keys as $key) {

            $value = $AudiencePlayerWordpressPlugin->fetchMetaValueForKey($article->metas, $key);

            switch ($key) {

                default:
                    $value = strval($value);
                    break;

                case 'duration':
                    if ($value = intval((is_numeric($value) && intval($value)) ? $value : $htmlVideoAsset->duration ?? 0)) {
                        $value = $value < 3600 ?
                            intval(date('i', $value)) . 'm' :
                            date('k', $value) . 'h' . ' ' . intval(date('i', $value)) . 'm';
                    } else {
                        $value = '';
                    }
                    break;

                case 'parental':
                    $indices = array_map(function ($index) {
                        return strtoupper(trim($index));
                    }, explode(',', $value));

                    $value = '';
                    foreach ($indices as $index) {
                        $value .= $index ? '<div class="audienceplayer-parental audienceplayer-parental-' . $index . '" alt="' .
                            $AudiencePlayerWordpressPlugin->fetchTranslations('dialogue_parental_indication_' . $index) . '"></div>' : '';
                    }
                    break;
            }

            if (trim($value)) {
                $content .= '<div class="meta-label ' . $key . '">' . $AudiencePlayerWordpressPlugin->fetchTranslations('label_meta_' . $key) . '</div>';
                $content .= '<div class="meta-value ' . $key . '">' . $value . '</div>';
            }

        }
        $content .= '</div>';

        $content .= '</div>';

        if ($args['show_seasons']) {

            $ancestorUrlSlug = $article->url_slug;
            $seasonArticleDescription = '';

            foreach ($article->children as $seasonArticle) {
                $selectedSeasonArticleId = $selectedSeasonArticleId ?: $seasonArticle->id;
                $seasonArticleTitle = $AudiencePlayerWordpressPlugin->fetchMetaValueForKey($seasonArticle->metas, 'title');
                if ($seasonArticle->id === $selectedSeasonArticleId) {
                    $ancestorUrlSlug .= '/' . $seasonArticle->url_slug;
                    $seasonArticleDescription = $AudiencePlayerWordpressPlugin->fetchMetaValueForKey($seasonArticle->metas, 'description');
                    $content .= '<div class="season-tab active"><a href="?season_article_id=' . $seasonArticle->id . '">' . $seasonArticleTitle . '</a></div>';
                } else {
                    $content .= '<div class="season-tab"><a href="?season_article_id=' . $seasonArticle->id . '">' . $seasonArticleTitle . '</a></div>';
                }
            }

            $content .= '<div class="season-description">' . $seasonArticleDescription . '</div>';

            if ($selectedSeasonArticleId) {
                $content .= \do_shortcode(
                    '[audienceplayer_article_carousel' .
                    ' ancestor_id=' . $selectedSeasonArticleId .
                    ' ancestor_url_slug=' . $article->url_slug .
                    ' show_titles=' . ($args['show_titles'] ?? 1) .
                    ' authentication_action=' .
                    ']'
                );
            }

        } else {

            $content .= \do_shortcode(
                '[audienceplayer_article_carousel' .
                ' ancestor_id=' . $article->id .
                ' ancestor_url_slug=' . $article->url_slug .
                ' show_titles=' . ($args['show_titles'] ?? 1) .
                ' authentication_action=' .
                ']'
            );
        }

        $content .= '</div>';

    } else {
        $msg = 'query error occurred for article_id ' . $articleId . ', code: ' . $result->getFirstErrorCode();
        $AudiencePlayerWordpressPlugin->pushPublicDebugStack('[audienceplayer_shortcode_article_detail]', $msg);
        \trigger_error('Warning in shortcode [audienceplayer_shortcode_article_detail]: ' . $msg, E_USER_NOTICE);
    }

} else {
    $msg = 'argument "article_id" is not defined';
    $AudiencePlayerWordpressPlugin->pushPublicDebugStack('[audienceplayer_shortcode_article_detail]', $msg);
    \trigger_error('Warning in shortcode [audienceplayer_shortcode_article_detail]: ' . $msg, E_USER_WARNING);
}

$AudiencePlayerWordpressPlugin->pushPublicDebugStack('[audienceplayer_shortcode_article_detail]', $AudiencePlayerWordpressPlugin->api()->fetchLastOperationQuery(true), $AudiencePlayerWordpressPlugin->api()->fetchLastOperationResult());
