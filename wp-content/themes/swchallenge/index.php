<?php

get_header();

echo '<div class="site-main">';
    if( have_posts() ) {
        the_post();

        the_content();
    }
echo '</div>';

get_footer();