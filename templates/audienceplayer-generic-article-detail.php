<?php
/**
 * This generic page should be based on the main template file of your theme
 * This current template is based on "twentytwenty/index.php"
 *
 */

get_header();
?>

<main id="site-content" role="main">

    <?php

    // execute audienceplayer-article-detail shortcode with pre-assembled arguments
    $args = $AudiencePlayerWordpressPlugin->fetchGenericPageArgs();

    $shortcode = '[audienceplayer_article_detail';
    foreach ($args as $key => $val) {
        $shortcode .= ' ' . $key . '=' . $val;
    }
    $shortcode .= ']';

    echo \do_shortcode($shortcode);

    ?>

</main><!-- #site-content -->

<?php get_footer(); ?>
