<?php
/*
Plugin Name: Simple FAQ Accordion
Plugin URI: https://github.com/demovik
Description: Adds an FAQ section with jQuery UI accordion, supports native options, duplication, and reordering.
Version: 1.0.0
Author: Viktor Demchuk
Author URI: https://www.linkedin.com/in/demovik
*/

// Register Custom Post Type with Menu Order Support
function sfaq_register_faq_post_type() {
    $labels = array(
        'name' => 'FAQs',
        'singular_name' => 'FAQ',
        'add_new' => 'Add New FAQ',
        'add_new_item' => 'Add New FAQ',
        'edit_item' => 'Edit FAQ',
        'new_item' => 'New FAQ',
        'view_item' => 'View FAQ',
        'search_items' => 'Search FAQs',
        'not_found' => 'No FAQs found',
        'not_found_in_trash' => 'No FAQs found in Trash',
        'all_items' => 'All FAQs',
        'menu_name' => 'FAQs',
    );

    $args = array(
        'public' => true,
        'label' => 'FAQs',
        'labels' => $labels,
        'supports' => array('title', 'editor', 'page-attributes'),
        'menu_icon' => 'dashicons-editor-help',
        'show_in_rest' => true,
        'hierarchical' => false,
    );
    register_post_type('faq', $args);
}
add_action('init', 'sfaq_register_faq_post_type');

// Shortcode Function
function sfaq_faq_shortcode($atts) {
    $atts = shortcode_atts(array(
        'collapsible' => 'true',
        'heightStyle' => 'content',
        'active' => 'false',
        'animate' => '400'
    ), $atts);

    $faqs = new WP_Query(array(
        'post_type' => 'faq',
        'posts_per_page' => -1,
        'orderby' => 'menu_order',
        'order' => 'ASC',
    ));

    if (!$faqs->have_posts()) {
        return '<p>No FAQs found.</p>';
    }

    // Validate and adjust active attribute
    if ($atts['active'] === 'false' || $atts['active'] === false) {
        $atts['active'] = 'false'; // Ensure consistent string for JS
    } else {
        $active_int = intval($atts['active']);
        if ($active_int < 0 || $active_int >= $faqs->post_count) {
            $atts['active'] = 'false'; // Default to false if invalid
        } else {
            $atts['active'] = strval($active_int); // Convert to string for consistency
        }
    }

    $output = '<div class="sfaq-accordion" data-options="' . esc_attr(json_encode($atts)) . '">';
    while ($faqs->have_posts()) {
        $faqs->the_post();
        $output .= '<h3 class="sfaq-question">' . get_the_title() . '<span class="sfaq-indicator"></span></h3>';
        $output .= '<div class="sfaq-answer">' . apply_filters('the_content', get_the_content()) . '</div>';
    }
    $output .= '</div>';

    wp_reset_postdata();
    return $output;
}
add_shortcode('faq_accordion', 'sfaq_faq_shortcode');

// Enqueue Assets
function sfaq_enqueue_assets() {
    wp_enqueue_script('jquery-ui-accordion');
    wp_enqueue_style('sfaq-style', plugins_url('/assets/faq.css', __FILE__), array(), '1.8');
    wp_enqueue_script('sfaq-script', plugins_url('/assets/faq.js', __FILE__), array('jquery', 'jquery-ui-accordion'), '1.8', true);
}
add_action('wp_enqueue_scripts', 'sfaq_enqueue_assets');

// Add Duplicate Link to Admin List
function sfaq_add_duplicate_link($actions, $post) {
    if ($post->post_type === 'faq' && current_user_can('edit_posts')) {
        $nonce = wp_create_nonce('sfaq_duplicate_nonce');
        $actions['duplicate'] = '<a href="' . admin_url('admin.php?action=sfaq_duplicate_faq&post=' . $post->ID . '&nonce=' . $nonce) . '">Duplicate</a>';
    }
    return $actions;
}
add_filter('post_row_actions', 'sfaq_add_duplicate_link', 10, 2);

// Handle Duplication
function sfaq_duplicate_faq() {
    if (!isset($_GET['post']) || !isset($_GET['nonce']) || !wp_verify_nonce($_GET['nonce'], 'sfaq_duplicate_nonce')) {
        wp_die('Security check failed.');
    }

    $post_id = intval($_GET['post']);
    $post = get_post($post_id);

    if ($post && $post->post_type === 'faq') {
        $new_post = array(
            'post_title' => $post->post_title . ' (Copy)',
            'post_content' => $post->post_content,
            'post_type' => 'faq',
            'post_status' => 'draft',
            'menu_order' => $post->menu_order,
        );
        $new_post_id = wp_insert_post($new_post);

        if ($new_post_id) {
            wp_redirect(admin_url('edit.php?post_type=faq'));
            exit;
        }
    }
    wp_die('Duplication failed.');
}
add_action('admin_action_sfaq_duplicate_faq', 'sfaq_duplicate_faq');

// Enable Sorting in Admin
function sfaq_admin_columns($columns) {
    $columns['menu_order'] = 'Order';
    return $columns;
}
add_filter('manage_faq_posts_columns', 'sfaq_admin_columns');

function sfaq_admin_column_content($column, $post_id) {
    if ($column === 'menu_order') {
        echo get_post($post_id)->menu_order;
    }
}
add_action('manage_faq_posts_custom_column', 'sfaq_admin_column_content', 10, 2);

function sfaq_sortable_columns($columns) {
    $columns['menu_order'] = 'menu_order';
    return $columns;
}
add_filter('manage_edit-faq_sortable_columns', 'sfaq_sortable_columns');