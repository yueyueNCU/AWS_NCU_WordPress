<?php
/**
 * Plugin Name: AWS_TEXT_AI_TOOL
 * Description: comprehend , textract , polly 
 * Author: YUE YUE, Weii07_chen , Yungtunchi
 * Version: 1.0.0
 */
if( !defined('ABSPATH')){
    exit;
}

require_once plugin_dir_path( __FILE__ ) . 'vendor/autoload.php';

require_once plugin_dir_path( __FILE__ ) . 'includes/comprehend/class-aws-comprehend.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/settings/comprehend.php';

require_once plugin_dir_path( __FILE__ ) . 'includes/textract/aws-textract-service.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/settings/textract.php';

require_once plugin_dir_path( __FILE__ ) . 'includes/polly/aws-polly-service.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/settings/polly.php';

add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'comprehend_api_plugin_settings_link' );
function comprehend_api_plugin_settings_link( $links ) {
    $comprehend_settings_link = '<a href="' . admin_url( 'options-general.php?page=comprehend_menu_slug' ) . '">' . __( 'Comprehend Settings', 'my-api-plugin' ) . '</a>';
    array_unshift( $links, $comprehend_settings_link );
    $textract_settings_link = '<a href="' . admin_url( 'options-general.php?page=textract_menu_slug' ) . '">' . __( 'Textract Settings', 'my-api-plugin' ) . '</a>';
    array_unshift( $links, $textract_settings_link );
    $textract_settings_link = '<a href="' . admin_url( 'options-general.php?page=polly_menu_slug' ) . '">' . __( 'Polly Settings', 'my-api-plugin' ) . '</a>';
    array_unshift( $links, $textract_settings_link );
    return $links;
}


add_action('wp_enqueue_scripts','load_assets');
function load_assets(){
    wp_enqueue_style(
        'bootstrap-css',
        'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css',
        array(),
        '5.3.0',
        'all'
    );
    // 載入 jQuery（WordPress 內建 jQuery）
    wp_enqueue_script('jquery');

    // 載入 Bootstrap JS
    wp_enqueue_script(
        'bootstrap-js',
        'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js',
        array('jquery'),
        '5.3.0',
        true
    );
}