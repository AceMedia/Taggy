<?php
/**
 * Plugin Name: Taggy
 * Plugin URI: https://acemedia.ninja
 * Description: Provides WordPress tag suggestions for Movies via OMDb.
 * Version: 0.421.0
 * Author: Shane Rounce
 * Author URI: https://shanerounce.com
 * License:    GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
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
            'show_in_rest' => false, // the key stays on the server
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
        wp_enqueue_script( 'taggy', plugins_url( '/js/taggy.js', __FILE__ ), array( 'jquery' ), '0.421.0', true );
        
        // The browser talks to our REST route; the OMDb key never leaves the server.
        wp_localize_script( 'taggy', 'taggy_settings', array(
            'searchUrl' => esc_url_raw( rest_url( 'taggy/v1/search' ) ),
            'nonce'     => wp_create_nonce( 'wp_rest' ),
        ));
    }
}
add_action( 'admin_enqueue_scripts', 'load_custom_admin_scripts' );

// Proxy OMDb title searches so the API key stays server side.
add_action( 'rest_api_init', 'taggy_register_search_route' );

function taggy_register_search_route() {
    register_rest_route( 'taggy/v1', '/search', array(
        'methods'             => 'GET',
        'callback'            => 'taggy_search',
        'permission_callback' => function () {
            return current_user_can( 'edit_posts' );
        },
        'args'                => array(
            's' => array(
                'type'              => 'string',
                'required'          => true,
                'sanitize_callback' => 'sanitize_text_field',
            ),
        ),
    ) );
}

function taggy_search( WP_REST_Request $request ) {
    $query = trim( (string) $request->get_param( 's' ) );
    $key   = get_option( 'taggy_api_key', '' );
    if ( strlen( $query ) < 3 || $key === '' ) {
        return rest_ensure_response( array() );
    }

    $url = add_query_arg( array(
        's'      => rawurlencode( $query ),
        'apikey' => rawurlencode( $key ),
    ), 'https://www.omdbapi.com/' );
    $response = wp_safe_remote_get( $url, array( 'timeout' => 8 ) );
    if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
        return new WP_Error( 'taggy_omdb', __( 'Could not reach OMDb.' ), array( 'status' => 502 ) );
    }

    $data   = json_decode( wp_remote_retrieve_body( $response ), true );
    $titles = array();
    if ( isset( $data['Search'] ) && is_array( $data['Search'] ) ) {
        foreach ( $data['Search'] as $result ) {
            if ( isset( $result['Title'] ) && is_string( $result['Title'] ) ) {
                $titles[] = $result['Title'];
            }
        }
    }
    return rest_ensure_response( array_values( array_unique( $titles ) ) );
}
