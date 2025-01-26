<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <title><?php bloginfo('name'); ?> &raquo; <?php is_front_page() ? bloginfo('description') : wp_title(''); ?></title>
    <meta charset="<?php bloginfo( 'charset' ); ?>" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>

<header>
    <div class="flex justify-between items-center">
        <div class="right">
            <div class="header-logo">
                <img src="<?php echo site_url().'/wp-content/uploads/2025/01/header-logo.png'; ?>" alt="">
            </div>
        </div>
        <div class="left">
            <div class="hdr-menu">
                <?php
                    wp_nav_menu(array(
                        'theme_location' => 'header-menu',
                        'menu_class' => 'md:px-auto flex flex-row flex-wrap gap-[20px] px-5 text-white-v2 underline text-sm uppercase md:text-lg lg:gap-[40px]',
                    ));
                ?>
            </div>
        </div>
    </div>
</header>
