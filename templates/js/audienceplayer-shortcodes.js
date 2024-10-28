/* ### audienceplayer-article-carousel ############################################################################### */
/* ################################################################################################################### */
jQuery(function ($) {

    if (window.AudiencePlayerCore && window.AudiencePlayerCore.CONFIG) {

        window.AudiencePlayerCore.CONFIG.slickCarouselConfig = {

            speed: 500,
            dots: true,
            infinite: false,
            adaptiveHeight: true,
            arrows: false,

            responsive: [{
                breakpoint: 1024,
                settings: {
                    slidesToShow: 3,
                    slidesToScroll: 3,
                    dots: true,
                    infinite: false,
                    adaptiveHeight: true,
                    arrows: false
                }
            }, {
                breakpoint: 600,
                settings: {
                    slidesToShow: 2,
                    slidesToScroll: 2,
                    dots: true,
                    infinite: false,
                    adaptiveHeight: true,
                    arrows: false
                }
            }, {
                breakpoint: 300,
                settings: "unslick" // destroys slick
            }]
        };
    }

});

/* ### audienceplayer-article-carousel ############################################################################### */
/* ################################################################################################################### */
