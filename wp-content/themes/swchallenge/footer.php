
<footer class="flex flex-col items-center pb-6 pt-10 md:pt-20">
    <div class="ft-logo">
        <img src="<?php echo site_url(); ?>/wp-content/uploads/2025/01/footer-logo.png" alt="">
    </div>
    <div class="ft-menu">
        <?php
            wp_nav_menu(array(
                'theme_location' => 'footer-menu',
                'menu_class' => 'md:px-auto flex flex-row flex-wrap gap-[20px] px-5 text-gray-light underline pt-16 text-sm uppercase md:text-lg lg:gap-[70px]',
            ));
        ?>
    </div>
    <div class="ft-bottom pt-10 md:pt-120 text-center">
        <a href="#" class="text-xl text-gray-medium"><i class="fa fa-linkedin-square" aria-hidden="true"></i></a>
        <div class="text-sm text-gray-medium mt-20">©ShakeWell Agency 2023 . All Rights Reserved.</div>
    </div>
</footer>

<?php wp_footer(); ?>

</body>
</html>