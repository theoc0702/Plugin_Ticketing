<?php
/**
 * Plugin Name: Mon Booking Plugin
 * Plugin URI: https://github.com/theoc0702/Plugin_Ticketing
 * Description: Plugin de gestion de réservations similaire à BookingPress.
 * Version: 1.0
 * Author: Chelly
 * Author URI: https://theoc0702.github.io
 * License: GPL2
 */

if (!defined('ABSPATH')) {
    exit; // Sécurité
}
function mbp_enqueue_assets() {
    wp_enqueue_style('mbp-style', plugin_dir_url(__FILE__) . 'assets/style.css');
}
add_action('wp_enqueue_scripts', 'mbp_enqueue_assets');

function mbp_enqueue_scripts() {
    wp_enqueue_script('mbp-script', plugin_dir_url(__FILE__) . 'assets/script.js', array('jquery'), null, true);
    wp_localize_script('mbp-script', 'mbp_ajax', array('ajax_url' => admin_url('admin-ajax.php')));
}
add_action('wp_enqueue_scripts', 'mbp_enqueue_scripts');



// Création du custom post type "Réservations"
function mbp_create_post_type() {
    register_post_type('reservation', array(
        'labels'      => array(
            'name'          => __('Réservations'),
            'singular_name' => __('Réservation')
        ),
        'public'      => false,
        'has_archive' => false,
        'show_ui'     => true,
        'supports'    => array('title', 'editor', 'custom-fields')
    ));
}
add_action('init', 'mbp_create_post_type');

// Création du menu d'administration
function mbp_admin_menu() {
    add_menu_page(
        'Mon Booking Plugin',
        'Réservations',
        'manage_options',
        'mbp_reservations',
        'mbp_reservations_page',
        'dashicons-calendar-alt',
        20
    );
}
add_action('admin_menu', 'mbp_admin_menu');

function mbp_reservations_page() {
    echo '<h1>Gestion des Réservations</h1>';
}

// Création d'un shortcode pour le formulaire de réservation
function mbp_reservation_form() {
    ob_start();
    ?>
    <form method="post">
        <label for="name">Nom :</label>
        <input type="text" name="name" required>
        <label for="email">Email :</label>
        <input type="email" name="email" required>
        <label for="date">Date de réservation :</label>
        <input type="date" name="date" required>
        <button type="submit">Réserver</button>
    </form>
    <?php
    return ob_get_clean();
}
add_shortcode('reservation_form', 'mbp_reservation_form');

// Traitement du formulaire
function mbp_handle_reservation() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['name'], $_POST['email'], $_POST['date'])) {
        $post_id = wp_insert_post(array(
            'post_title'   => sanitize_text_field($_POST['name']),
            'post_content' => sanitize_text_field($_POST['email']),
            'post_type'    => 'reservation',
            'post_status'  => 'publish'
        ));

        if ($post_id) {
            update_post_meta($post_id, 'date_reservation', sanitize_text_field($_POST['date']));
        }
    }
}
add_action('init', 'mbp_handle_reservation');
