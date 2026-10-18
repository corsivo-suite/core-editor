<?php

if (PHP_SAPI !== 'cli') exit(1);

define('ABSPATH', dirname(__DIR__, 4) . '/');
define('MB_IN_BYTES', 1024 * 1024);
require ABSPATH . 'wp-includes/class-wp-error.php';

$fixture_directory = sys_get_temp_dir() . '/corsivo-admin-save-' . bin2hex(random_bytes(8));
mkdir($fixture_directory);
$actions = array();
$assertions = 0;
$can_manage_options = true;
$settings_errors = array();
$localized_config = null;
$redirect = null;

register_shutdown_function(function () use ($fixture_directory) {
	foreach (glob($fixture_directory . '/*') as $file) unlink($file);
	rmdir($fixture_directory);
});

function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {
	$GLOBALS['actions'][$hook][] = $callback;
}
function do_action(...$arguments) {}
function add_options_page(...$arguments) { return 'settings_page_corsivo-core-editor'; }
function current_user_can($capability) { return $GLOBALS['can_manage_options']; }
function is_multisite() { return false; }
function get_stylesheet_directory() { return $GLOBALS['fixture_directory']; }
function trailingslashit($path) { return rtrim($path, '/\\') . '/'; }
function apply_filters($hook, $value) { return $value; }
function wp_json_encode($value, $flags = 0) { return json_encode($value, $flags); }
function wp_mkdir_p($path) { return mkdir($path, 0777, true); }
function wp_generate_password($length, ...$arguments) { return substr(bin2hex(random_bytes($length)), 0, $length); }
function is_wp_error($value) { return $value instanceof WP_Error; }
function sanitize_key($value) { return strtolower(preg_replace('/[^a-zA-Z0-9_-]/', '', $value)); }
function sanitize_text_field($value) { return trim(strip_tags($value)); }
function wp_unslash($value) { return is_array($value) ? array_map('wp_unslash', $value) : stripslashes($value); }
function absint($value) { return abs((int) $value); }
function esc_html($value) { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
function __($value, $domain = null) { return $value; }
function esc_html__($value, $domain = null) { return esc_html($value); }
function corsivo_theme_log_activity(...$arguments) {}
function corsivo_theme_clear_theme_json_cache() {}
function corsivo_theme_current_language() { return 'default'; }
function corsivo_theme_default_language() { return 'default'; }
function corsivo_theme_active_languages() { return array(); }
function plugin_dir_path($file) { return trailingslashit(dirname($file)); }
function plugin_dir_url($file) { return 'https://example.test/wp-content/plugins/corsivo-core-editor/'; }
function wp_enqueue_style(...$arguments) {}
function wp_enqueue_script(...$arguments) {}
function wp_set_script_translations(...$arguments) {}
function wp_style_add_data(...$arguments) {}
function get_post_types(...$arguments) { return array(); }
function rest_url($path) { return 'https://example.test/wp-json/' . $path; }
function wp_create_nonce($action) { return 'valid-' . $action; }
function admin_url($path) { return 'https://example.test/wp-admin/' . $path; }
function get_option($key, $default = false) { return $default; }
function current_time($format) { return date($format); }
function get_bloginfo($key) { return 'Save flow fixture'; }
function wp_insert_post($post, $return_error = false) { return new WP_Error('fixture_footer_failure', 'Footer creation failed.'); }
function wp_get_theme() {
	return new class {
		public function get_page_templates() { return array(); }
	};
}
function wp_localize_script($handle, $object_name, $config) {
	if ($object_name === 'corsivoThemeEditor') $GLOBALS['localized_config'] = $config;
}
function add_query_arg($key, $value, $url = null) {
	$query = is_array($key) ? $key : array($key => $value);
	$target = is_array($key) ? $value : $url;
	return $target . (str_contains($target, '?') ? '&' : '?') . http_build_query($query);
}
function add_settings_error($setting, $code, $message, $type = 'error') {
	$GLOBALS['settings_errors'][] = compact('setting', 'code', 'message', 'type');
}
function wp_safe_redirect($url, $status = 302) {
	$GLOBALS['redirect'] = compact('url', 'status');
	throw new RuntimeException('fixture-redirect');
}
function wp_die($message, $title = '', $arguments = array()) {
	throw new RuntimeException('fixture-denied', is_int($title) ? $title : ($arguments['response'] ?? 500));
}
function check_admin_referer($action, $query_arg = '_wpnonce') {
	if (($_POST[$query_arg] ?? '') !== wp_create_nonce($action)) wp_die('Invalid nonce.', 403);
	return 1;
}

function check($condition, $message) {
	$GLOBALS['assertions']++;
	if ($condition) return;
	fwrite(STDERR, 'FAIL: ' . $message . PHP_EOL);
	exit(1);
}

function run_admin_request($method, $post = array(), $query = array()) {
	unset($GLOBALS['corsivo_theme_config'], $GLOBALS['corsivo_theme_settings_file_corrupt']);
	$GLOBALS['settings_errors'] = array();
	$GLOBALS['localized_config'] = null;
	$GLOBALS['redirect'] = null;
	$_SERVER['REQUEST_METHOD'] = $method;
	$_POST = $post;
	$_GET = $query;

	try {
		foreach ($GLOBALS['actions']['load-settings_page_corsivo-core-editor'] ?? array() as $callback) $callback();
		foreach ($GLOBALS['actions']['admin_enqueue_scripts'] ?? array() as $callback) $callback('settings_page_corsivo-core-editor');
	} catch (RuntimeException $exception) {
		if ($exception->getMessage() === 'fixture-denied') return $exception->getCode();
		if ($exception->getMessage() !== 'fixture-redirect') throw $exception;
	}
	return null;
}

function settings_submission($revision, $enabled = false, $section = 'global-settings') {
	$post = array(
		'corsivo_theme_settings_nonce' => wp_create_nonce('corsivo_theme_settings_action'),
		'settings_revision' => $revision,
		'settings_section' => $section,
	);
	if ($enabled) $post['corsivo-theme-core-renders'] = 'on';
	return $post;
}

function follow_settings_redirect() {
	check(($GLOBALS['redirect']['status'] ?? null) === 303, 'A successful POST redirects before rendering with HTTP 303.');
	check($GLOBALS['localized_config'] === null, 'A successful POST cannot localize the pre-save revision.');
	$location = parse_url($GLOBALS['redirect']['url']);
	parse_str($location['query'], $query);
	check($location['host'] === 'example.test' && $location['path'] === '/wp-admin/options-general.php', 'The redirect remains on the local settings page.');
	check(($query['page'] ?? '') === 'corsivo-core-editor' && ($query['corsivo-theme-notice'] ?? '') === 'settings-saved', 'The redirected GET identifies the settings page and successful save.');
	check(($location['fragment'] ?? '') === ($query['section'] ?? null), 'The redirect restores the selected subsection.');
	run_admin_request('GET', array(), $query);
	check(isset($GLOBALS['localized_config']['configRevision']), 'The redirected GET localizes the React configuration.');
	$stored = json_decode(file_get_contents(corsivo_theme_get_settings_file_path()), true);
	check($GLOBALS['localized_config']['configRevision'] === corsivo_theme_settings_revision($stored), 'React receives the revision currently persisted on disk.');
	return $query;
}

require dirname(__DIR__) . '/includes/admin/settings.php';

$initial_settings = array(
	'global' => array_fill_keys(array('tab_functional_pages', 'tab_custom_slugs', 'core_renders', 'gutenberg_footer', 'cpt_menu_parent', 'force_style_injection', 'minify_css', 'minify_js'), false),
	'configurator' => array('colors' => array(array('slug' => 'brand', 'color' => '#123456'))),
);
file_put_contents(corsivo_theme_get_settings_file_path(), wp_json_encode($initial_settings));
corsivo_theme_settings_menu();

run_admin_request('GET');
check($localized_config !== null && $redirect === null && $settings_errors === array(), 'An initial GET loads the configurator without saving.');
$initial_revision = $localized_config['configRevision'];
run_admin_request('POST', settings_submission($initial_revision, true), array('lang' => 'it'));
$first_query = follow_settings_redirect();
check(($first_query['lang'] ?? '') === 'it', 'Saving preserves the requested language.');
check(corsivo_theme_get_settings()['global']['core_renders'] === true, 'The first POST persists the enabled option.');
$first_revision = $localized_config['configRevision'];
check($first_revision !== $initial_revision, 'The first save changes the revision exposed to React.');

run_admin_request('POST', settings_submission($first_revision, false), $first_query);
follow_settings_redirect();
check(corsivo_theme_get_settings()['global']['core_renders'] === false, 'The consecutive POST persists the disabled option.');
check($settings_errors === array(), 'Two consecutive saves do not produce a revision conflict.');
check(corsivo_theme_get_settings()['configurator'] === $initial_settings['configurator'], 'PHP saves preserve the design configuration.');
$current_revision = $localized_config['configRevision'];
check($current_revision !== $first_revision, 'The second save also refreshes the React revision.');

$before_conflict = file_get_contents(corsivo_theme_get_settings_file_path());
run_admin_request('POST', settings_submission($first_revision, true));
check($redirect === null, 'A stale revision does not redirect to a success notice.');
check(($settings_errors[0]['code'] ?? '') === 'corsivo_theme_revision_conflict' && ($settings_errors[0]['type'] ?? '') === 'error', 'A genuine stale revision remains a visible error.');
check(file_get_contents(corsivo_theme_get_settings_file_path()) === $before_conflict, 'A stale submission does not overwrite the current file.');
check(($localized_config['configRevision'] ?? '') === $current_revision, 'The rejected POST still initializes React with the current persisted revision.');
$conflict = corsivo_theme_write_settings_file($initial_settings, $first_revision);
check(is_wp_error($conflict) && ($conflict->get_error_data()['status'] ?? null) === 409, 'The persistence layer retains HTTP 409 revision-conflict semantics.');

$invalid_nonce = settings_submission($current_revision, true);
$invalid_nonce['corsivo_theme_settings_nonce'] = 'invalid';
check(run_admin_request('POST', $invalid_nonce) === 403, 'An invalid nonce is rejected.');
check($localized_config === null && file_get_contents(corsivo_theme_get_settings_file_path()) === $before_conflict, 'An invalid nonce cannot mutate settings or reach enqueue.');
$can_manage_options = false;
check(run_admin_request('POST', settings_submission($current_revision, true)) === 403, 'A user without manage_options is rejected.');
check($localized_config === null && file_get_contents(corsivo_theme_get_settings_file_path()) === $before_conflict, 'A denied user cannot mutate settings or reach enqueue.');
$can_manage_options = true;

foreach (array('wordpress', 'custom-slugs', 'https://untrusted.test/') as $section) {
	run_admin_request('POST', settings_submission($current_revision, false, $section), array('lang' => array('it')));
	$query = follow_settings_redirect();
	check($query['section'] === (str_starts_with($section, 'https:') ? 'global-settings' : $section), 'The redirect retains a valid form subsection and rejects an arbitrary target.');
	check(!isset($query['lang']), 'A non-scalar language is excluded from the redirect.');
	$current_revision = $localized_config['configRevision'];
}

$footer_submission = settings_submission($current_revision);
$footer_submission['corsivo-theme-gutenberg-footer'] = 'on';
run_admin_request('POST', $footer_submission);
check($redirect === null, 'A follow-up failure does not redirect to an unconditional success notice.');
check(($settings_errors[0]['code'] ?? '') === 'corsivo_theme_settings_side_effect_failed' && ($settings_errors[0]['type'] ?? '') === 'warning', 'A persisted save with a failed follow-up operation remains a warning.');
check(corsivo_theme_get_settings()['global']['gutenberg_footer'] === true, 'The warning correctly represents settings already saved on disk.');
check(($localized_config['configRevision'] ?? '') === corsivo_theme_settings_revision(corsivo_theme_get_settings(true)), 'The warning response also localizes the post-save revision.');

echo 'Corsivo Core Editor admin save flow passed (' . $assertions . ' assertions).' . PHP_EOL;
