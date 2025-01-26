(function($){$(function(){

    const swiper = new Swiper('.swiper', {
        slidesPerView: 4,
        loop: true,
        spaceBetween: 130,

        navigation: {
            nextEl: '.swiper-arrow-wrap .swiper-right',
            prevEl: '.swiper-arrow-wrap .swiper-left',
        },

        breakpoints: {
            320: {
                slidesPerView: 1,
            },
            640: {
              slidesPerView: 2,
            },
            1024: {
              slidesPerView: 3,
            },
            1100: {
                slidesPerView: 4,
            },
        },

    });

})})(jQuery)