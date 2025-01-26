<?php

add_theme_support( 'post-thumbnails' );

function sw_enqueue_styles() {

    $styles = [
        'google-fonts-grotesk' => 'https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300..700&display=swap',
        'font-awesome' =>  get_stylesheet_directory_uri() .'/lib/font-awesome/4.7.0/css/font-awesome.min.css',
        'swiper-style' => 'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css',
        'tailwind-style' => get_stylesheet_directory_uri().'/src/output.css',
        'main-style' => get_stylesheet_uri(),
    ];

    foreach ($styles as $handle => $src) {
        wp_enqueue_style( $handle, $src );
    }

    wp_enqueue_script( 'swiper-script', 'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js', [ 'jquery' ]);
    wp_enqueue_script( 'main-script', get_stylesheet_directory_uri() .'/js/main.js', [ 'jquery' ]);  
}

add_action('wp_enqueue_scripts', 'sw_enqueue_styles');

function add_Main_Nav() {
    register_nav_menu('header-menu',__( 'Header Menu' ));
    register_nav_menu('footer-menu', __('Footer Menu'));
}
add_action( 'init', 'add_Main_Nav' );
