<?php

get_header();

echo '<div class="site-main">';

?>

    <div class="hero-section ptb-80 plr-172">
        <div class="hero-container">
            <div class="big-text-wrap">
                <img class="circle" src="<?php echo get_stylesheet_directory_uri().'/images/circle.png'; ?>" alt="">
                <div class="text1 text-112 text-right uppercase font-medium">We're on a mission</div>
                <div class="text-combo flex items-center justify-between">
                    <div class="sm desktop text-xl font-normal">Brands that lead by change; that aims to disrupt; that exists to shape the future of our world.</div>
                    <div class="text2 text-112 uppercase font-medium">to build</div>
                </div>
                <div class="text3 text-112 uppercase font-medium">world class digital products</div>
                <div class="sm mobile text-xl font-normal">Brands that lead by change; that aims to disrupt; that exists to shape the future of our world.</div>
            </div>
            <div class="h-btm flex justify-between items-center">
                <a href="#" class="text-base uppercase text-xl underline">HOW CAN WE HELP</a>

                <div class="lets-talk-btn">
                    <div class="arr-btn">
                        <img src="<?php echo get_stylesheet_directory_uri().'/images/arrow-down.png'; ?>" alt="">
                    </div>
                </div>
            </div>
        </div>
    </div> <!-- end hero-section -->

    <div class="video-section ptb-80 plr-60">
        <?php $bg_img = get_field('video_image', 'option');?>
        <div class="bg-img bg-cover bg-center" style="background-image: url('<?php echo $bg_img; ?>'); "></div>
        <div class="text-3v2xl text-center"><?php echo get_field('bottom_text', 'option'); ?></div>
    </div> <!-- end video-section -->

    <div class="services-section bg-gray-dark ptb-120 plr-60">
        <div class="heading uppercase font-medium text-8v2xl"><?php echo get_field('service_heading', 'option'); ?></div>
        <div class="sub-heading uppercase text-4v2xl"><?php echo get_field('service_sub_heading', 'option'); ?></div>

        <div class="service-list-wrap">
            <?php
                if( have_rows('services', 'option') ) {
                    while( have_rows('services', 'option') ) {
                        the_row();
                        
                        echo '<div class="service-item grid">';
                            echo '<div class="lbl"><div class="text-3v2xl">'.get_sub_field('label').'</div></div>';
                            echo '<div class="val"><div class="text-xl text-gray-light">'.get_sub_field('description').'</div></div>';
                        echo '</div>';

                    }
                }
            ?>
        </div>

    </div> <!-- end services-section -->

    <div class="recent-projects-section ptb-80 plr-60">
        <div class="heading uppercase font-medium text-8v2xl"><?php echo get_field('recent_projects_heading', 'option'); ?></div>
        <div class="text-end">
            <a href="#" class="text-white uppercase text-xl underline">See More</a>
        </div>
        <div class="projects-list-wrap">
            <?php
                $args = array(
                    'post_type' => 'project',
                    'posts_per_page' => -1,
                );
                $query = new WP_Query ($args);

                if( $query->have_posts() ) {
                    while( $query->have_posts() ) {
                        $query->the_post();
                        $title = get_the_title();
                        $sub_title = get_field('sub_heading', get_the_ID());
                        $img_url = get_the_post_thumbnail_url( get_the_ID() );
                        ?>
                            <a class="project-item" href="#">
                                <div class="image" style="background-image: url('<?php echo $img_url; ?>'); "></div>
                                <div class="info">
                                    <div class="title text-3v2xl"><?php echo $title; ?></div>
                                    <div class="sub-title text-xl"><?php echo $sub_title; ?></div>
                                    <div class="arrow-icon">
                                        <img src="<?php echo get_stylesheet_directory_uri().'/images/arrow-icon.svg'; ?>" alt="">
                                    </div>
                                </div>
                            </a>
                        <?php
                    }
                } wp_reset_postdata();
            ?>
        </div>

    </div> <!-- end recent-projects-section -->

    <div class="our-clients-section ptb-80 plr-60">
        <div class="flex justify-between items-center">
            <div class="heading uppercase font-medium text-8v2xl"><?php echo get_field('client_heading', 'option'); ?></div>
            <div class="swiper-arrow-wrap">
                <div class="arr swiper-left"></div>
                <div class="arr swiper-right"></div>
            </div>
        </div>

        <div class="client-carousel-wrap swiper">
            <div class="swiper-wrapper">
                <?php
                    if( have_rows('clients', 'option') ) {
                        while( have_rows('clients', 'option') ) {
                            the_row();
                            echo '<div class="swiper-slide">';
                                echo '<div class="client-item flex flex-col gap-[24px]">';
                                    echo '<div class="logo"><img src="'.get_sub_field('logo').'" title=""></div>';
                                    echo '<div class="name text-xl font-medium">'.get_sub_field('name').'</div>';
                                    echo '<div class="description text-sm">'.get_sub_field('description').'</div>';
                                echo '</div>';
                            echo '</div>';
                        }
                    }
                ?>
            </div>
        </div>
    </div> <!-- end our-clients-section -->

    <div class="lets-talk-section bg-gray-dark ptb-120 plr-60">
        <div class="heading uppercase font-medium text-8v2xl mb-12"><?php echo get_field('lets_talk_heading', 'option'); ?></div>
        <div class="sub-heading uppercase text-4v2xl"><?php echo get_field('lets_talk_sub_heading', 'option'); ?></div>
        <div class="form-container plr-172">
            <?php echo do_shortcode("[formidable id=1]"); ?>
        </div>
    </div> <!-- end lets-talk-section -->

<?php

echo '</div>'; // end of site-main

get_footer();