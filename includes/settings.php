<?php
if (!defined('ABSPATH')) exit;

function corsivo_theme_download_theme_json() {
	if (!current_user_can('manage_options')) wp_die('Unauthorized.', '', array('response' => 403));
	check_ajax_referer('corsivo_theme_download_theme_json', 'nonce');

	corsivo_theme_to_css();
	$theme_data = WP_Theme_JSON_Resolver::get_theme_data()->get_data();
	$json = wp_json_encode($theme_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
	if (!is_string($json)) wp_die('Theme JSON generation failed.', '', array('response' => 500));

	corsivo_theme_log_activity('theme_json_exported', 'success', 'Enriched theme.json exported.');
	nocache_headers();
	header('Content-Type: application/json; charset=utf-8');
	header('Content-Disposition: attachment; filename="theme.json"');
	header('Content-Length: ' . strlen($json));
	echo $json;
	exit;
}
add_action('wp_ajax_corsivo_theme_download_theme_json', 'corsivo_theme_download_theme_json');

function corsivo_theme_download_scss() {
	if (!current_user_can('manage_options')) wp_die('Unauthorized.', '', array('response' => 403));
	check_ajax_referer('corsivo_theme_download_scss', 'nonce');

	corsivo_theme_to_css();
	$scss = corsivo_theme_generate_scss_build();
	if (!is_string($scss) || $scss === '') wp_die('SCSS build failed.', '', array('response' => 500));

	corsivo_theme_log_activity('scss_exported', 'success', 'Generated theme-json.scss exported.');
	nocache_headers();
	header('Content-Type: text/x-scss; charset=utf-8');
	header('Content-Disposition: attachment; filename="theme-json.scss"');
	header('Content-Length: ' . strlen($scss));
	echo $scss;
	exit;
}
add_action('wp_ajax_corsivo_theme_download_scss', 'corsivo_theme_download_scss');

function corsivo_theme_generated_cache_location($create = true) {
	$upload_dir = wp_upload_dir(null, false);
	if (!empty($upload_dir['error'])) {
		return new WP_Error('corsivo_theme_cache_unavailable', $upload_dir['error']);
	}

	$key = sanitize_key(get_stylesheet()) . '-' . get_current_blog_id();
	$path = trailingslashit($upload_dir['basedir']) . 'corsivo-core-editor/cache/' . $key;
	$url = trailingslashit($upload_dir['baseurl']) . 'corsivo-core-editor/cache/' . $key;
	if ($create && !is_dir($path) && !wp_mkdir_p($path)) {
		return new WP_Error('corsivo_theme_cache_directory_failed', 'Unable to create the generated asset cache directory.');
	}

	return array('path' => $path, 'url' => $url);
}

function corsivo_theme_clear_generated_cache() {
	$location = corsivo_theme_generated_cache_location(false);
	if (is_wp_error($location)) return $location;
	if (!is_dir($location['path'])) return true;

	$entries = @scandir($location['path']);
	if (!is_array($entries)) return true;
	foreach ($entries as $entry) {
		if (!preg_match('/^corsivo-theme-[a-f0-9]{16}\.min\.(?:css|js)$/', $entry)) continue;
		$path = trailingslashit($location['path']) . $entry;
		if (is_file($path)) @unlink($path);
	}
	return true;
}

function corsivo_theme_log_minify_error($type, $error) {
	$message = is_wp_error($error) ? $error->get_error_message() : (string) $error;
	$key = 'corsivo_theme_minify_error_' . md5($type . $message);
	if (get_transient($key)) return;
	corsivo_theme_log_activity('minify_' . $type . '_failed', 'error', $message);
	set_transient($key, 1, HOUR_IN_SECONDS);
}

function corsivo_theme_enqueue_min_css() {
	global $wp_styles;
	require_once __DIR__ . '/minify.php';
	$bundle = corsivo_theme_build_css();
	if (is_wp_error($bundle)) {
		corsivo_theme_log_minify_error('css', $bundle);
		return;
	}

	wp_enqueue_style('corsivo-theme-style-min', $bundle['url'], $bundle['dependencies'], $bundle['version']);
	corsivo_theme_replace_enqueued_assets($wp_styles, $bundle['handles'], 'corsivo-theme-style-min');
}

function corsivo_theme_enqueue_min_js() {
	global $wp_scripts;
	require_once __DIR__ . '/minify.php';
	$bundle = corsivo_theme_build_js();
	if (is_wp_error($bundle)) {
		corsivo_theme_log_minify_error('js', $bundle);
		return;
	}

	wp_enqueue_script('corsivo-theme-script-min', $bundle['url'], $bundle['dependencies'], $bundle['version'], true);
	corsivo_theme_replace_enqueued_assets($wp_scripts, $bundle['handles'], 'corsivo-theme-script-min');
}

function corsivo_theme_minify_enabled($key) {
	$config = corsivo_theme_get_settings();
	$global = isset($config['global']) && is_array($config['global']) ? $config['global'] : array();
	return !empty($global[$key]);
}

if (corsivo_theme_minify_enabled('minify_css')) add_action('wp_enqueue_scripts', 'corsivo_theme_enqueue_min_css', 1000);
if (corsivo_theme_minify_enabled('minify_js')) add_action('wp_enqueue_scripts', 'corsivo_theme_enqueue_min_js', 1000);
