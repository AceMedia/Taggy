<?php
/**
 * Plugin Name: Taggy
 * Plugin URI: https://acemedia.ninja
 * Description: Provides WordPress tag suggestions for Movies via OMDb.
 * Version: 0.420.0
 * Author: Shane Rounce
 * Author URI: https://shanerounce.com
 */


// Add admin menu item
add_action( 'admin_menu', 'taggy_api_key_settings_add_admin_menu' );

function taggy_api_key_settings_add_admin_menu() {
    add_menu_page(
        'Taggy Settings',
        'Taggy Settings',
        'manage_options',
        'taggy-settings',
        'taggy_api_key_settings_page',
        'dashicons-admin-settings'
    );
}

// Register settings
add_action( 'admin_init', 'taggy_api_key_settings_init' );

function taggy_api_key_settings_init() {
    register_setting(
        'taggy_api_key_settings_group',
        'taggy_api_key',
        array(
            'type' => 'string',
            'description' => 'API key for external service',
            'sanitize_callback' => 'sanitize_text_field',
            'show_in_rest' => true,
            'default' => ''
        )
    );
}

// Render settings page
function taggy_api_key_settings_page() {
    ?>
    <div class="wrap">
        <h1>Taggy Settings</h1>
        <form method="post" action="options.php">
            <?php settings_fields( 'taggy_api_key_settings_group' ); ?>
            <?php do_settings_sections( 'taggy_api_key_settings_group' ); ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row"><?php esc_html_e( 'OMDb API Key' ); ?><br /><a href="https://www.omdbapi.com/apikey.aspx" target="_blank">Get a key</a></th>
                    <td><input type="text" name="taggy_api_key" value="<?php echo esc_attr( get_option('taggy_api_key') ); ?>" /></td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

// Save API key as site option meta
add_action( 'update_option_taggy_api_key', 'save_taggy_api_key_site_option_meta', 10, 2 );

function save_taggy_api_key_site_option_meta( $old_value, $new_value ) {
    update_site_option( 'taggy_api_key', $new_value );
}

// Retrieve API key from site option meta
function get_taggy_api_key() {
    return get_site_option( 'taggy_api_key', '' );
}

function load_custom_admin_scripts() {
    $screen = get_current_screen();
    if ( $screen->id === 'post' || $screen->id === 'post-new' ) {
        // Enqueue the compiled React bundle
        
        // Enqueue the custom jQuery-based script
        wp_enqueue_script( 'taggy', plugins_url( '/js/taggy.js', __FILE__ ), array( 'jquery' ), '1.0.0', true );
        
        // Localize the script with the API key
        $taggy_api_key = get_option( 'taggy_api_key', '' );
        wp_localize_script( 'taggy', 'taggy_api_key', array(
            'apiKey' => $taggy_api_key,
        ));
    }
}
add_action( 'admin_enqueue_scripts', 'load_custom_admin_scripts' );

