<?php
/*
Plugin Name: Shakewell List Challenge
Description: Shakewell List Challenge.
Version: 1.0
Author: Jhon Parba
*/

if (!defined('ABSPATH')) exit;

class ShakewellListChallenge {

    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        add_action('wp_ajax_update_my_list', [$this, 'update_my_list']);
        add_shortcode('mylistdemo', [$this, 'display_list']);
    }

    public function add_admin_menu() {
        add_menu_page(
            'SW List Challenge',
            'SW List Challenge',
            'manage_options',
            'my-list-challenge',
            [$this, 'settings_page'],
            'dashicons-editor-ol'
        );
    }

    public function enqueue_admin_scripts($hook) {
        if ($hook !== 'toplevel_page_my-list-challenge') return;

        wp_enqueue_script('jquery-ui-sortable');
        wp_enqueue_script('my-list-challenge-script', plugin_dir_url(__FILE__) . 'js/main.js', ['jquery', 'jquery-ui-sortable'], null, true);
        wp_localize_script('my-list-challenge-script', 'MyListChallengeAjax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('my-list-challenge-nonce')
        ]);

        wp_enqueue_style('my-list-challenge-style', plugin_dir_url(__FILE__) . 'css/style.css');
    }

    public function settings_page() {
        $list_items = get_option('my_list_challenge_items', []);
        ?>
        <div class="wrap">
            <h1>My List</h1>
            <ul id="my-list" class="sortable">
                <?php foreach ($list_items as $item) : ?>
                    <li class="list-item"><?php echo esc_html($item); ?><button class="remove-item">&times;</button></li>
                <?php endforeach; ?>
            </ul>
            <input type="text" id="new-item" placeholder="Add new item">
            <button id="add-item" class="button button-primary">Add Item</button>
            <button id="save-list" class="button button-secondary">Save List</button>
        </div>
        <?php
    }

    public function update_my_list() {
        check_ajax_referer('my-list-challenge-nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized', 403);
        }

        $items = isset($_POST['items']) ? array_map('sanitize_text_field', $_POST['items']) : [];
        update_option('my_list_challenge_items', $items);

        wp_send_json_success('List updated successfully.');
    }

    public function display_list() {
        $list_items = get_option('my_list_challenge_items', []);
        if (empty($list_items)) return '<p>No items in the list.</p>';

        $output = '<ul class="my-list-challenge">';
        foreach ($list_items as $item) {
            $output .= '<li>' . esc_html($item) . '</li>';
        }
        $output .= '</ul>';

        return $output;
    }
}

new ShakewellListChallenge();