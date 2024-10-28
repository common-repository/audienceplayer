<?php

$content = $content ?? '';

if (($args['product_id'] ?? null)) {

    $result = $AudiencePlayerWordpressPlugin->api()->query
        ->Product($args['product_id'])
        ->properties([
            'id',
            'type',
            'title',
            'description',
            'description_short(output:html)',
            'call_to_action_tag',
            'price',
            'currency_symbol',
            'articles {id}'
        ])
        ->execute();

    if ($result->isSuccessful() && ($product = ($result->getData(true)))) {

        // assemble authentication and authorisation actions
        $actionAuthentication = $AudiencePlayerWordpressPlugin->helper()->populateTemplateString(
            $AudiencePlayerWordpressPlugin->parseJsShortCodeAction($args['authentication_action'] ?:
                $AudiencePlayerWordpressPlugin->fetchLoginUrl(\add_query_arg(['purchase_product_id' => $product->id], $AudiencePlayerWordpressPlugin->fetchCurrentUrl()))),
            ['product_id' => $product->id],
            true
        );
        $actionAuthorisation = $AudiencePlayerWordpressPlugin->parseJsShortCodeAction($args['authorisation_action'] ?: '');
        $actionAfterPurchase = $AudiencePlayerWordpressPlugin->parseJsShortCodeAction($args['after_purchase_action'] ?: '', false);

        // assemble price
        $productPrice = $product->currency_symbol . ' ' . $product->price;

        $classNames = trim($args['class'] ?? '');
        foreach ($product->articles as $article) {
            $classNames .= ' product-id-' . $product->id . ' article-id-' . $article->id;
        }

        $productDescription = $AudiencePlayerWordpressPlugin->helper->sanitiseJsString($product->description_short);

        $onclickAction = "window.AudiencePlayerCore.openModalPurchase(this, 'product', " .
            $args['product_id'] . ',' .
            "'" . $productDescription . "'," .
            "'" . $productPrice . "'," .
            (($args['show_voucher_validation'] ?? false) ? 'true' : 'false') . ',' .
            $actionAuthentication . ',' .
            $actionAuthorisation . ',' .
            $actionAfterPurchase . ');';

        $content .=
            '<div class="audienceplayer-purchase-product-button ' . $classNames . '">' .
            '<div class="wrapper">' .
            '<button class="purchase-product-button" onclick="' . $onclickAction . '">' .
            ($args['label'] ?:
                ($product->call_to_action_tag ?? $AudiencePlayerWordpressPlugin->fetchTranslations('button_purchase_product') . ' ' . $productPrice)
            ) .
            '</button>' .
            '</div>' .
            '</div>';

        if (intval($_REQUEST['purchase_product_id'] ?? 0) === $product->id && $onclickAction) {
            $content .= '<script>';
            // When a mutation with redirect-path (e.g. to Mollie) is fired, the query-string parameter "purchase_product_id" is automatically removed from the redirect-path.
            // Also remove the query-string parameter from the current URL, should the redirect-path not be used (e.g. when fulfilling a zero-sum voucher, which simply triggers a page-reload).
            $content .= 'jQuery(function($){window.AudiencePlayerCore.removeQsaParametersFromCurrentLocation(["purchase_product_id"]);});';
            // Now trigger the purchase-product action
            $content .= 'jQuery(function($){' . $onclickAction . '});';
            // Unset the query-string parameter to avoid further usage in PHP
            unset($_REQUEST['purchase_product_id'], $_POST['purchase_product_id']);
            $content .= '</script>';
        }

    } elseif (($code = $result->getFirstErrorCode()) > 500) {
        $msg = 'query error occurred, code: ' . $code;
        $AudiencePlayerWordpressPlugin->pushPublicDebugStack('[audienceplayer_shortcode_purchase_product_button]', $msg);
        \trigger_error('Warning in shortcode [audienceplayer_shortcode_purchase_product_button]: ' . $msg, E_USER_WARNING);
    }

} else {
    $msg = 'argument "product_id" is not defined';
    $AudiencePlayerWordpressPlugin->pushPublicDebugStack('[audienceplayer_shortcode_purchase_product_button]', $msg);
    \trigger_error('Warning in shortcode [audienceplayer_shortcode_purchase_product_button]: ' . $msg, E_USER_WARNING);
}
