<?php
if (!defined('WP_CLI') || !WP_CLI || !defined('ABSPATH')) { exit; }
if (!defined('DOING_AJAX')) { define('DOING_AJAX', true); }
class TaggyFixtureEnd extends Error {}
add_filter('wp_die_ajax_handler', function () { return function () { throw new TaggyFixtureEnd(); }; });
$GLOBALS['taggy_fixture_checks'] = 0;
$GLOBALS['taggy_fixture_calls'] = 0;
function taggy_fixture_assert($ok, $message) { if (!$ok) { throw new RuntimeException($message); } $GLOBALS['taggy_fixture_checks']++; }
function taggy_fixture_ajax($post) {
    $_POST = $post; $_REQUEST = $post; ob_start();
    try { taggy_search(); } catch (TaggyFixtureEnd $e) {}
    return json_decode(ob_get_clean(), true);
}
$old_key = get_option('taggy_api_key', null); $old_user = get_current_user_id(); $old_post = $_POST; $old_request = $_REQUEST;
$user = wp_insert_user(array('user_login'=>'taggy_fixture_editor','user_pass'=>wp_generate_password(),'user_email'=>'taggy@example.test','role'=>'editor'));
$subscriber = wp_insert_user(array('user_login'=>'taggy_fixture_reader','user_pass'=>wp_generate_password(),'user_email'=>'taggy-reader@example.test','role'=>'subscriber'));
$fixture_filter = function ($preempt, $args, $url) {
    if (strpos($url, 'https://www.omdbapi.com/') !== 0) { return $preempt; }
    $GLOBALS['taggy_fixture_calls']++;
    taggy_fixture_assert(strpos($url, 'apikey=fictional-taggy-fixture') !== false, 'The upstream key must stay in the server request');
    taggy_fixture_assert($args['redirection'] === 0 && $args['timeout'] === 8, 'Upstream request must be bounded and not redirect credentials');
    return array('headers'=>array(), 'response'=>array('code'=>200), 'body'=>wp_json_encode(array('Response'=>'True','Search'=>array(array('Title'=>'The Moon Misplaced Its Hat'),array('Title'=>'<img src=x onerror=alert(1)>The Paper Astronaut'),array('Title'=>'The Moon Misplaced Its Hat')))));
};
try {
    update_option('taggy_api_key', ''); wp_set_current_user($user);
    taggy_fixture_assert(is_wp_error(taggy_movie_search('Missing key')), 'Missing key should fail gracefully');
    update_option('taggy_api_key', 'fictional-taggy-fixture'); add_filter('pre_http_request', $fixture_filter, 10, 3);
    $query = 'Moon fixture ' . wp_generate_uuid4();
    $result = taggy_movie_search($query);
    taggy_fixture_assert(count($result)===2 && $result[1]==='The Paper Astronaut', 'Remote titles must be sanitised and deduplicated');
    $calls = $GLOBALS['taggy_fixture_calls']; taggy_movie_search($query);
    taggy_fixture_assert($GLOBALS['taggy_fixture_calls']===$calls, 'Repeat query should use cached titles');
    taggy_fixture_assert(strpos(wp_json_encode($result),'fictional-taggy-fixture')===false, 'Results must not disclose the service key');
    $nonce = wp_create_nonce('taggy_search');
    $result = taggy_fixture_ajax(array('nonce'=>$nonce,'query'=>'ab'));
    taggy_fixture_assert(!$result['success'], 'Too-short query must be rejected');
    $result = taggy_fixture_ajax(array('nonce'=>$nonce,'query'=>array('bad')));
    taggy_fixture_assert(!$result['success'], 'Malformed query must be rejected');
    $result = taggy_fixture_ajax(array('nonce'=>$nonce,'query'=>$query));
    taggy_fixture_assert($result['success'] && count($result['data'])===2, 'Authorised editor search must succeed');
    wp_set_current_user($subscriber);
    $result = taggy_fixture_ajax(array('nonce'=>wp_create_nonce('taggy_search'),'query'=>$query));
    taggy_fixture_assert(!$result['success'], 'Subscriber with valid nonce must be denied');
    wp_set_current_user($user); set_transient('taggy_rate_' . $user, 30, MINUTE_IN_SECONDS);
    taggy_fixture_assert(is_wp_error(taggy_movie_search($query . 'new')), 'Uncached requests must respect per-user quota');
    delete_transient('taggy_search_' . md5($query . '|' . hash('sha256','fictional-taggy-fixture')));
} finally {
    remove_filter('pre_http_request', $fixture_filter, 10); delete_transient('taggy_rate_' . $user);
    if ($old_key===null) { delete_option('taggy_api_key'); } else { update_option('taggy_api_key',$old_key); }
    wp_set_current_user($old_user); $_POST=$old_post; $_REQUEST=$old_request;
    require_once ABSPATH . 'wp-admin/includes/user.php'; wp_delete_user($user); wp_delete_user($subscriber);
}
WP_CLI::success($GLOBALS['taggy_fixture_checks'] . ' Taggy real WordPress assertions passed');
