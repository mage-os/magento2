/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
define([
    'jquery',
    'Magento_PageBuilder/js/widget/show-on-hover',
    'Magento_PageBuilder/js/widget/video-background'
], function ($, showOnHover, videoBackground) {
    'use strict';

    return function (config, element) {
        showOnHover(config);

        var videoElement = element[0].querySelector('[data-background-type=video]');
        if (videoElement) {
            var $slider = $(element).closest('[data-content-type=slider]');
            videoBackground(config, videoElement);
            $slider.on('setPosition', function(event, slick){
                var currentVideoSlide = slick.$slides[slick.currentSlide].querySelector('.jarallax');
                if (currentVideoSlide.jarallax.options.videoPlayOnlyVisible) {
                    currentVideoSlide.jarallax.video.play();
                }
                if (videoElement.jarallax.isVideoInserted) {
                    videoElement.classList.add('video-inserted');
                }
            });
            $slider.on('beforeChange', function(event, slick){
                var currentVideoSlide = slick.$slides[slick.currentSlide].querySelector('.jarallax');

                if (currentVideoSlide.jarallax.options.videoPlayOnlyVisible) {
                    currentVideoSlide.jarallax.video.pause();
                }
            });
        }
    };
});
