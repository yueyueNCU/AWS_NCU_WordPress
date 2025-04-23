<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Registers the settings menu.
 */
function comprehend_register_settings_menu() {
    add_options_page(
        'Comprehend API Settings',       // Page title
        'Comprehend API',             // Menu title
        'manage_options',        // Capability required
        'comprehend_menu_slug', // Menu slug
        'display_comprehend_settings_page' // Callback function to display the page
    );
}
add_action( 'admin_menu', 'comprehend_register_settings_menu' );

/**
 * Registers the settings.
 */
function comprehend_register_settings() {
    register_setting(
        'comprehend_settings_group',
        'comprehend_plugin_authentication_method',
        'sanitize_text_field'
    );

    register_setting(
        'comprehend_settings_group', // Option group name
        'comprehend_plugin_aws_region',     // Option name for Region
        'sanitize_text_field'          // Sanitize the input
    );

    register_setting(
        'comprehend_settings_group', // Option group name
        'comprehend_plugin_api_key',        // Option name for API key
        'sanitize_text_field'          // Sanitize the input
    );

    register_setting(
        'comprehend_settings_group', // Option group name
        'comprehend_plugin_secret_key',     // Option name for Secret key
        'sanitize_text_field'          // Sanitize the input
    );
}
add_action( 'admin_init', 'comprehend_register_settings' );


/**
 * Displays the settings page.
 */
function display_comprehend_settings_page() {
    // Check user capabilities
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    // Get the settings from the database
    $authentication_method = get_option( 'comprehend_plugin_authentication_method', 'api_key' ); // Default to API key
    $api_key    = get_option( 'comprehend_plugin_api_key' );
    $secret_key = get_option( 'comprehend_plugin_secret_key' );
    $region = get_option( 'comprehend_plugin_aws_region');
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'Comprehend API Settings', 'my-api-plugin' ); ?></h1>
        <form action="options.php" method="post">
            <?php
            // Output security fields for the registered setting "my_api_plugin_settings_group"
            settings_fields( 'comprehend_settings_group' );

            // Output setting sections and their fields
            do_settings_sections( 'comprehend_menu_slug' );
            ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row"><?php esc_html_e( 'Authentication Method', 'my-api-plugin' ); ?></th>
                    <td>
                        <select name="comprehend_plugin_authentication_method">
                            <option value="api_key" <?php selected( $authentication_method, 'api_key' ); ?>><?php esc_html_e( 'API Key & Secret Key', 'my-api-plugin' ); ?></option>
                            <option value="iam_role" <?php selected( $authentication_method, 'iam_role' ); ?>><?php esc_html_e( 'IAM Role (EC2)', 'my-api-plugin' ); ?></option>
                        </select>
                        <p class="description"><?php esc_html_e( 'Choose how to authenticate with the Comprehend API.', 'my-api-plugin' ); ?></p>
                    </td>
                </tr>


                    <tr valign="top">
                        <th scope="row"><?php esc_html_e( 'Region', 'my-api-plugin' ); ?></th>
                        <td><input type="text" name="comprehend_plugin_aws_region" value="<?php echo esc_attr( $region ); ?>" class="regular-text"></td>
                    </tr>
                <?php if ( $authentication_method == 'api_key' ) : ?>
                    <tr valign="top">
                        <th scope="row"><?php esc_html_e( 'API Key', 'my-api-plugin' ); ?></th>
                        <td><input type="text" name="comprehend_plugin_api_key" value="<?php echo esc_attr( $api_key ); ?>" class="regular-text"></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><?php esc_html_e( 'Secret Key', 'my-api-plugin' ); ?></th>
                        <td><input type="text" name="comprehend_plugin_secret_key" value="<?php echo esc_attr( $secret_key ); ?>" class="regular-text"></td>
                    </tr>
                <?php endif; ?>

            </table>
            <?php
            // Output save settings button
            submit_button( __( 'Save Settings', 'my-api-plugin' ) );
            ?>
        </form>
    </div>
    <?php
}