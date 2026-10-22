<?php
/**
 * Plugin Name: Taggy
 * Plugin URI: https://acemedia.ninja/
 * Description: Find movie titles with OMDb and add them as WordPress tags.
 * Version: 0.421.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Shane Rounce
 * Author URI: https://shanerounce.com/
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: taggy
 */
if (!defined('ABSPATH')) { exit; }
if (version_compare(PHP_VERSION, '7.4', '<')) {
    add_action('admin_notices', function () {
        echo '<div class="notice notice-error"><p>' . esc_html__('Taggy requires PHP 7.4 or later.', 'taggy') . '</p></div>';
    });
    return;
}
add_action('admin_menu', 'taggy_api_key_settings_add_admin_menu');
function taggy_api_key_settings_add_admin_menu() {
    add_options_page(__('Taggy Settings', 'taggy'), __('Taggy', 'taggy'), 'manage_options', 'taggy-settings', 'taggy_api_key_settings_page');
}
add_action('admin_init', 'taggy_api_key_settings_init');
function taggy_api_key_settings_init() {
    register_setting('taggy_api_key_settings_group', 'taggy_api_key', array(
        'type'=>'string', 'sanitize_callback'=>'sanitize_text_field', 'show_in_rest'=>false, 'default'=>''
    ));
}
function taggy_api_key_settings_page() {
    if (!current_user_can('manage_options')) { wp_die(esc_html__('You cannot manage these settings.', 'taggy')); }
    ?>
    <div class="wrap"><h1><?php esc_html_e('Taggy Settings', 'taggy'); ?></h1>
    <p><?php esc_html_e('Search OMDb for movie titles in the block editor. Searches are sent from your server to OMDb only when an editor enters a search. Your API key stays on the server.', 'taggy'); ?></p>
    <form method="post" action="options.php">
        <?php settings_fields('taggy_api_key_settings_group'); ?>
        <table class="form-table"><tr><th><label for="taggy-api-key"><?php esc_html_e('OMDb API key', 'taggy'); ?></label></th>
        <td><input id="taggy-api-key" type="password" autocomplete="new-password" name="taggy_api_key" value="<?php echo esc_attr(get_option('taggy_api_key', '')); ?>" />
        <p><a href="https://www.omdbapi.com/apikey.aspx" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Get an OMDb key', 'taggy'); ?></a></p></td></tr></table>
        <?php submit_button(); ?>
    </form><p><a href="https://acemedia.ninja/">Ace Media</a> · <a href="https://acemedia.host/">Ace Media hosting</a></p></div>
    <?php
}
/** Existing helper retained; each site's browser/editor already used its own option. */
function get_taggy_api_key() { return get_option('taggy_api_key', ''); }
add_action('enqueue_block_editor_assets', 'taggy_enqueue_editor');
function taggy_enqueue_editor() {
    $screen = get_current_screen();
    if (!$screen || !$screen->post_type || !is_object_in_taxonomy($screen->post_type, 'post_tag') || !current_user_can('edit_posts')) { return; }
    wp_enqueue_script('taggy', plugins_url('js/taggy.js', __FILE__), array('wp-plugins','wp-edit-post','wp-element','wp-components','wp-data','wp-api-fetch','wp-i18n'), '0.421.0', true);
    wp_localize_script('taggy', 'taggyConfig', array('url'=>admin_url('admin-ajax.php'), 'nonce'=>wp_create_nonce('taggy_search'), 'configured'=>get_taggy_api_key() !== ''));
}
add_action('wp_ajax_taggy_search', 'taggy_search');
function taggy_search() {
    check_ajax_referer('taggy_search', 'nonce');
    $taxonomy = get_taxonomy('post_tag');
    if (!$taxonomy || !current_user_can('edit_posts') || !current_user_can($taxonomy->cap->assign_terms)) {
        wp_send_json_error(array('message'=>__('You cannot assign tags.', 'taggy')), 403);
    }
    $query = isset($_POST['query']) && is_string($_POST['query']) ? sanitize_text_field(wp_unslash($_POST['query'])) : '';
    if (strlen($query) < 3 || strlen($query) > 100) { wp_send_json_error(array('message'=>__('Enter between 3 and 100 characters.', 'taggy')), 400); }
    $result = taggy_movie_search($query);
    if (is_wp_error($result)) { wp_send_json_error(array('message'=>$result->get_error_message()), 400); }
    wp_send_json_success($result);
}
/** Server-side OMDb lookup. Responses contain titles only, never credentials or upstream URLs. */
function taggy_movie_search($query) {
    $key = get_taggy_api_key();
    if (!is_string($key) || $key === '') { return new WP_Error('taggy_configuration', __('Ask an administrator to configure Taggy.', 'taggy')); }
    $cache_key = 'taggy_search_' . md5($query . '|' . hash('sha256', $key));
    $cached = get_transient($cache_key);
    if (is_array($cached)) { return $cached; }
    $rate_key = 'taggy_rate_' . get_current_user_id();
    $rate = (int) get_transient($rate_key);
    if ($rate >= 30) { return new WP_Error('taggy_rate', __('Please wait a minute before searching again.', 'taggy')); }
    set_transient($rate_key, $rate + 1, MINUTE_IN_SECONDS);
    $response = wp_remote_get(add_query_arg(array('s'=>$query, 'type'=>'movie', 'apikey'=>$key), 'https://www.omdbapi.com/'), array('timeout'=>8, 'redirection'=>0, 'limit_response_size'=>65536));
    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) { return new WP_Error('taggy_service', __('OMDb is unavailable. Please try again later.', 'taggy')); }
    $data = json_decode(wp_remote_retrieve_body($response), true);
    if (!is_array($data) || !isset($data['Response'])) { return new WP_Error('taggy_service', __('OMDb returned an unreadable response.', 'taggy')); }
    if ($data['Response'] === 'False' && isset($data['Error']) && $data['Error'] !== 'Movie not found!') { return new WP_Error('taggy_service', __('OMDb could not complete the search. Check your key and quota.', 'taggy')); }
    $titles = array();
    foreach (isset($data['Search']) && is_array($data['Search']) ? array_slice($data['Search'], 0, 10) : array() as $item) {
        if (is_array($item) && isset($item['Title']) && is_string($item['Title'])) {
            $title = sanitize_text_field($item['Title']);
            if ($title !== '') { $titles[] = $title; }
        }
    }
    $titles = array_values(array_unique($titles));
    set_transient($cache_key, $titles, 5 * MINUTE_IN_SECONDS);
    return $titles;
}
