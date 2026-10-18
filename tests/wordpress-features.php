<?php
define('ABSPATH', __DIR__ . '/');

$hooks = [];
$options = [];
$writes = [];
$can_manage = true;
$nonce_valid = true;
$admin_request = true;
$ajax_request = false;
$current_screen = null;
$pagenow = 'index.php';
$registered_post_types = ['post' => 'post', 'page' => 'page', 'book' => 'book'];
$revision_post_types = ['post', 'page'];
$redirect_url = '';
$assertions = 0;

function add_filter($hook, $callback, $priority = 10, $arguments = 1) {
	$GLOBALS['hooks'][$hook][$priority][] = ['callback' => $callback, 'arguments' => $arguments];
}
function add_action($hook, $callback, $priority = 10, $arguments = 1) {
	add_filter($hook, $callback, $priority, $arguments);
}
function remove_filter($hook, $callback, $priority = 10) {
	foreach ($GLOBALS['hooks'][$hook][$priority] ?? [] as $index => $entry) {
		if ($entry['callback'] === $callback) unset($GLOBALS['hooks'][$hook][$priority][$index]);
	}
}
function remove_action($hook, $callback, $priority = 10) {
	remove_filter($hook, $callback, $priority);
}
function has_action($hook) {
	foreach ($GLOBALS['hooks'][$hook] ?? [] as $callbacks) {
		if ($callbacks) return true;
	}
	return false;
}
function apply_filters($hook, $value, ...$arguments) {
	$priorities = $GLOBALS['hooks'][$hook] ?? [];
	ksort($priorities);
	foreach ($priorities as $callbacks) {
		foreach ($callbacks as $entry) $value = call_user_func_array($entry['callback'], array_slice([$value, ...$arguments], 0, $entry['arguments']));
	}
	return $value;
}
function __return_false() { return false; }
function __return_zero() { return 0; }
function __return_empty_array() { return []; }
function __return_empty_string() { return ''; }
function get_option($key, $default = false) { return $GLOBALS['options'][$key] ?? $default; }
function update_option($key, $value) {
	$GLOBALS['writes'][] = $key;
	$GLOBALS['options'][$key] = $value;
	return true;
}
function is_admin() { return $GLOBALS['admin_request']; }
function wp_doing_ajax() { return $GLOBALS['ajax_request']; }
function get_current_screen() { return $GLOBALS['current_screen']; }
function get_post_types() { return $GLOBALS['registered_post_types']; }
function post_type_supports($type, $feature) { return $feature === 'revisions' && in_array($type, $GLOBALS['revision_post_types'], true); }
function current_user_can($capability) { return $capability === 'manage_options' && $GLOBALS['can_manage']; }
function wp_unslash($value) {
	if (is_array($value)) return array_map('wp_unslash', $value);
	return is_string($value) ? stripslashes($value) : $value;
}
function wp_die($message, $title = '', $arguments = []) { throw new RuntimeException($message, $arguments['response'] ?? 500); }
function check_admin_referer($action) {
	if ($action !== 'corsivo_core_save_wordpress_features' || !$GLOBALS['nonce_valid']) wp_die('Invalid nonce.', '', ['response' => 403]);
}
function admin_url($path) { return 'https://example.test/wp-admin/' . $path; }
function add_query_arg($key, $value, $url) { return $url . '&' . $key . '=' . $value; }
function wp_safe_redirect($url) {
	$GLOBALS['redirect_url'] = $url;
	throw new RuntimeException('redirect', 302);
}
function esc_html($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function esc_attr($value) { return esc_html($value); }
function esc_url($value) { return esc_html($value); }
function selected($first, $second) { if ((string) $first === (string) $second) echo 'selected="selected"'; }
function checked($value) { if ($value) echo 'checked="checked"'; }
function disabled($value) { if ($value) echo 'disabled="disabled"'; }
function wp_nonce_field($action) { echo '<input type="hidden" name="_wpnonce" value="nonce">'; }
function submit_button($label) { echo '<button>' . esc_html($label) . '</button>'; }
function wp_get_schedules() { return ['hourly' => ['display' => 'Every hour']]; }
function wp_date($format, $timestamp) { return gmdate($format, $timestamp); }

function corsivo_core_feature_assert($condition, $message) {
	$GLOBALS['assertions']++;
	if (!$condition) throw new RuntimeException($message);
}
function corsivo_core_feature_assert_failure($status) {
	try {
		corsivo_core_save_wordpress_features();
	} catch (RuntimeException $exception) {
		corsivo_core_feature_assert($exception->getCode() === $status, 'Save stops with the expected status.');
		return;
	}
	throw new RuntimeException('Expected the save handler to stop.');
}

require dirname(__DIR__) . '/includes/wordpress-features.php';
require dirname(__DIR__) . '/includes/admin/wordpress-features.php';

corsivo_core_feature_assert(array_keys($hooks) === ['admin_post_corsivo_core_save_wordpress_features'], 'Default settings add no WordPress runtime hooks.');
corsivo_core_feature_assert($writes === [], 'Loading defaults writes no options.');
$defaults = corsivo_core_wordpress_features_defaults();
foreach ([null, true, 'broken', ['heartbeat_admin' => [], 'revisions_mode' => [], 'revisions_limit' => [], 'disable_emoji' => ['1']]] as $malformed) {
	corsivo_core_feature_assert(corsivo_core_wordpress_features_sanitize($malformed) === $defaults, 'Malformed settings preserve native defaults.');
}
$clean = corsivo_core_wordpress_features_sanitize(['disable_pingbacks_trackbacks' => '1', 'disable_emoji' => 'false', 'revisions_mode' => 'limited', 'revisions_limit' => '25', 'heartbeat_admin' => '60', 'heartbeat_editor' => 120, 'unknown' => 'secret']);
corsivo_core_feature_assert($clean['disable_pingbacks_trackbacks'] && !$clean['disable_emoji'] && $clean['revisions_limit'] === 25, 'Checkboxes and integer limits are normalized strictly.');
corsivo_core_feature_assert($clean['heartbeat_editor'] === '120' && !isset($clean['unknown']), 'Intervals are allowlisted and unknown keys removed.');
foreach (['0', '-1', '1001', '10 seconds', true, 12.5] as $invalid_limit) {
	corsivo_core_feature_assert(corsivo_core_wordpress_features_sanitize(['revisions_limit' => $invalid_limit])['revisions_limit'] === 10, 'Invalid revision limits cannot bypass the supported range.');
}

$options['corsivo_core_wordpress_features'] = array_replace($defaults, ['disable_pingbacks_trackbacks' => true]);
add_action('do_all_pings', 'do_all_pingbacks');
add_action('do_all_pings', 'do_all_trackbacks');
add_action('do_all_pings', 'generic_ping');
add_action('do_all_pings', 'do_all_enclosures');
corsivo_core_wordpress_features_register();
corsivo_core_feature_assert(apply_filters('pings_open', true) === false, 'Inbound pings close without changing post records.');
corsivo_core_feature_assert(!has_action('comments_open'), 'Normal comment availability is untouched.');
corsivo_core_feature_assert(apply_filters('pre_option_default_pingback_flag', false) === 0, 'New outgoing pingbacks are not queued.');
corsivo_core_feature_assert(apply_filters('pre_option_default_ping_status', false) === 'closed', 'New posts default to closed pings.');
corsivo_core_feature_assert(apply_filters('get_to_ping', ['https://example.test/trackback']) === [], 'Outgoing trackback lists are empty.');
corsivo_core_feature_assert(apply_filters('pre_option_ping_sites', false) === '', 'Update services receive no pings.');
$methods = apply_filters('xmlrpc_methods', ['pingback.ping' => 'ping', 'pingback.extensions.getPingbacks' => 'list', 'wp.getPosts' => 'posts']);
corsivo_core_feature_assert($methods === ['wp.getPosts' => 'posts'], 'Other XML-RPC methods remain available.');
$links = ['https://example.test/post'];
corsivo_core_clear_pingback_links($links);
corsivo_core_feature_assert($links === [], 'Direct pingback processing has no links to request.');
corsivo_core_disable_outgoing_pings();
$ping_callbacks = array_column($hooks['do_all_pings'][10], 'callback');
corsivo_core_feature_assert($ping_callbacks === ['do_all_enclosures'], 'Enclosure processing survives outgoing ping removal.');
corsivo_core_feature_assert($writes === [], 'Runtime filters never rewrite stored WordPress preferences.');

$options['corsivo_core_wordpress_features'] = array_replace($defaults, ['revisions_mode' => 'limited', 'revisions_limit' => 7]);
corsivo_core_feature_assert(corsivo_core_revisions_to_keep(-1, (object) ['post_type' => 'post']) === 7, 'Supported post types receive the revision limit.');
corsivo_core_feature_assert(corsivo_core_revisions_to_keep(-1, (object) ['post_type' => 'book']) === 0, 'Revision support is never enabled on unsupported types.');
$options['corsivo_core_wordpress_features']['revisions_mode'] = 'disabled';
corsivo_core_feature_assert(corsivo_core_revisions_to_keep(-1, (object) ['post_type' => 'post']) === 0, 'Revision disabling returns zero.');
$options['corsivo_core_wordpress_features']['revisions_mode'] = 'unlimited';
corsivo_core_feature_assert(corsivo_core_revisions_to_keep(5, (object) ['post_type' => 'post']) === -1, 'Unlimited mode returns the core unlimited value.');
$options['corsivo_core_wordpress_features']['revisions_mode'] = 'native';
corsivo_core_feature_assert(corsivo_core_revisions_to_keep(5, (object) ['post_type' => 'post']) === 5, 'Native mode preserves another policy.');
$GLOBALS['corsivo_core_revisions_environment_defined'] = true;
corsivo_core_feature_assert(corsivo_core_revisions_to_keep(3, (object) ['post_type' => 'post']) === 3, 'Environment revision configuration wins.');
$GLOBALS['corsivo_core_revisions_environment_defined'] = false;

$options['corsivo_core_wordpress_features'] = array_replace($defaults, ['heartbeat_admin' => '60', 'heartbeat_editor' => '120']);
$heartbeat = ['nonce' => 'preserved'];
corsivo_core_feature_assert(corsivo_core_heartbeat_settings($heartbeat) === ['nonce' => 'preserved', 'interval' => 60, 'minimalInterval' => 60], 'Admin Heartbeat receives a minimum interval and preserves core settings.');
$current_screen = (object) ['base' => 'post'];
corsivo_core_feature_assert(corsivo_core_heartbeat_settings($heartbeat)['minimalInterval'] === 120, 'Editor screens use the editor interval.');
$current_screen = null;
$pagenow = 'site-editor.php';
corsivo_core_feature_assert(corsivo_core_heartbeat_settings($heartbeat)['interval'] === 120, 'Site editor is recognized before the screen is available.');
$pagenow = 'admin-ajax.php';
$ajax_request = true;
foreach (['post', 'page', 'book', 'site-editor'] as $screen_id) {
	$_POST = ['action' => 'heartbeat', 'screen_id' => $screen_id];
	corsivo_core_feature_assert(corsivo_core_heartbeat_settings($heartbeat)['interval'] === 120, 'Heartbeat AJAX recognizes editor screen ' . $screen_id);
}
$_POST['screen_id'] = 'edit-post';
corsivo_core_feature_assert(corsivo_core_heartbeat_settings($heartbeat)['interval'] === 60, 'Content list screens keep the admin interval.');
$_POST['screen_id'] = [];
corsivo_core_feature_assert(corsivo_core_heartbeat_settings($heartbeat)['interval'] === 60, 'Malformed screen IDs are handled without type errors.');
corsivo_core_feature_assert(corsivo_core_heartbeat_settings(['interval' => 180, 'minimalInterval' => 180])['minimalInterval'] === 180, 'An existing higher minimum remains respected.');
$admin_request = false;
corsivo_core_feature_assert(corsivo_core_heartbeat_settings($heartbeat) === $heartbeat, 'Frontend Heartbeat is untouched.');
$admin_request = true;
$ajax_request = false;
$_POST = [];

$hooks = [];
add_action('wp_head', 'print_emoji_detection_script', 7);
add_action('admin_print_scripts', 'print_emoji_detection_script');
add_filter('wp_mail', 'wp_staticize_emoji_for_email');
$options['corsivo_core_wordpress_features'] = array_replace($defaults, ['disable_emoji' => true, 'disable_remote_patterns' => true]);
corsivo_core_wordpress_features_register();
corsivo_core_feature_assert(!has_action('wp_head') && !has_action('wp_mail'), 'Emoji scripts and email conversion callbacks are removed.');
corsivo_core_feature_assert(apply_filters('tiny_mce_plugins', ['lists', 'wpemoji', 'link']) === ['lists', 'link'], 'TinyMCE keeps its other plugins.');
corsivo_core_feature_assert(apply_filters('should_load_remote_block_patterns', true) === false, 'Remote pattern loading is disabled through the core filter.');
add_action('admin_print_scripts', 'print_emoji_detection_script');
corsivo_core_disable_emoji();
corsivo_core_feature_assert(!has_action('admin_print_scripts'), 'Late admin emoji registration can be removed at admin_init.');

$hooks = [];
add_action('fixture_job', '__return_false');
$options['cron'] = [
	'version' => 2,
	3000 => ['fixture_job' => ['signature' => ['schedule' => 'hourly', 'args' => ['secret-token']]]],
	2000 => ['fixture_job' => ['signature' => ['schedule' => 'hourly', 'args' => ['second-secret']]]],
	2500 => ['missing_callback' => ['signature' => ['schedule' => false, 'args' => ['hidden']]]],
	'broken' => ['ignored' => []],
	4000 => 'broken',
];
$before_writes = $writes;
$events = corsivo_core_cron_events();
corsivo_core_feature_assert(count($events) === 2 && $events[0]['count'] === 2 && $events[0]['next_run'] === 2000, 'Cron groups matching hooks and schedules around their earliest run.');
corsivo_core_feature_assert($events[0]['callback'] && !$events[1]['callback'], 'Cron reports callback presence in the current request.');
corsivo_core_feature_assert(!str_contains(json_encode($events), 'secret'), 'Cron diagnostics never expose arguments.');
ob_start();
corsivo_core_cron_render();
$cron_html = ob_get_clean();
corsivo_core_feature_assert(str_contains($cron_html, 'Every hour') && !str_contains($cron_html, 'secret'), 'Cron output renders recurrence labels and excludes arguments.');
corsivo_core_feature_assert($writes === $before_writes, 'Cron diagnostics do not modify scheduling or other options.');

$can_manage = false;
corsivo_core_feature_assert_failure(403);
$can_manage = true;
$_POST = ['_wpnonce' => []];
corsivo_core_feature_assert_failure(400);
$_POST = ['_wpnonce' => 'nonce', 'corsivo_core_wordpress_features' => []];
$nonce_valid = false;
corsivo_core_feature_assert_failure(403);
$nonce_valid = true;
$_POST['corsivo_core_wordpress_features'] = 'malformed';
corsivo_core_feature_assert_failure(400);
corsivo_core_feature_assert($writes === $before_writes, 'Unauthorized or malformed saves cannot change settings.');
$_POST['corsivo_core_wordpress_features'] = ['disable_emoji' => '1', 'revisions_mode' => 'limited', 'revisions_limit' => '15'];
corsivo_core_feature_assert_failure(302);
corsivo_core_feature_assert($writes === [...$before_writes, 'corsivo_core_wordpress_features'], 'Saving persists only the site WordPress features option.');
corsivo_core_feature_assert(str_ends_with($redirect_url, 'page=corsivo-core-editor&wordpress_features_saved=1#wordpress-features'), 'Saving returns to the dedicated features tab.');
corsivo_core_feature_assert($options['corsivo_core_wordpress_features']['disable_emoji'] && $options['corsivo_core_wordpress_features']['revisions_limit'] === 15, 'Saved settings are normalized.');
$GLOBALS['corsivo_core_revisions_environment_defined'] = true;
$_POST['corsivo_core_wordpress_features'] = ['disable_remote_patterns' => '1', 'revisions_mode' => 'disabled', 'revisions_limit' => '1'];
corsivo_core_feature_assert_failure(302);
corsivo_core_feature_assert($options['corsivo_core_wordpress_features']['revisions_mode'] === 'limited' && $options['corsivo_core_wordpress_features']['revisions_limit'] === 15, 'Environment-locked revision settings retain their previous stored selection.');
ob_start();
corsivo_core_wordpress_features_render();
$form_html = ob_get_clean();
corsivo_core_feature_assert(substr_count($form_html, '<form ') === 1 && str_contains($form_html, 'admin-post.php') && str_contains($form_html, 'name="_wpnonce"'), 'The dedicated form targets the authenticated admin action and contains a nonce.');
corsivo_core_feature_assert(str_contains($form_html, 'WP_POST_REVISIONS') && str_contains($form_html, 'disabled="disabled"'), 'Environment revision precedence is visible and controls are disabled.');
$can_manage = false;
ob_start();
corsivo_core_wordpress_features_render();
corsivo_core_feature_assert(ob_get_clean() === '', 'The options renderer requires manage_options.');

echo 'WordPress features: ' . $assertions . " assertions passed.\n";
