<?php
if (!defined('ABSPATH')) exit;

$GLOBALS['corsivo_core_revisions_environment_defined'] = defined('WP_POST_REVISIONS');

function corsivo_core_wordpress_features_defaults() {
	return [
		'disable_pingbacks_trackbacks' => false,
		'revisions_mode' => 'native',
		'revisions_limit' => 10,
		'heartbeat_admin' => 'native',
		'heartbeat_editor' => 'native',
		'disable_remote_patterns' => false,
		'disable_emoji' => false,
	];
}

function corsivo_core_wordpress_features_sanitize($settings) {
	$clean = corsivo_core_wordpress_features_defaults();
	if (!is_array($settings)) return $clean;

	foreach (['disable_pingbacks_trackbacks', 'disable_remote_patterns', 'disable_emoji'] as $key) {
		$clean[$key] = isset($settings[$key]) && in_array($settings[$key], [true, 1, '1'], true);
	}
	if (isset($settings['revisions_mode']) && in_array($settings['revisions_mode'], ['native', 'unlimited', 'disabled', 'limited'], true)) {
		$clean['revisions_mode'] = $settings['revisions_mode'];
	}
	if (isset($settings['revisions_limit']) && (is_int($settings['revisions_limit']) || is_string($settings['revisions_limit']))) {
		$limit = filter_var($settings['revisions_limit'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000]]);
		if ($limit !== false) $clean['revisions_limit'] = $limit;
	}
	foreach (['heartbeat_admin', 'heartbeat_editor'] as $key) {
		if (isset($settings[$key]) && in_array($settings[$key], ['native', '60', '120', 60, 120], true)) {
			$clean[$key] = (string) $settings[$key];
		}
	}
	return $clean;
}

function corsivo_core_wordpress_features_get() {
	return corsivo_core_wordpress_features_sanitize(get_option('corsivo_core_wordpress_features', []));
}

function corsivo_core_revisions_locked() {
	return !empty($GLOBALS['corsivo_core_revisions_environment_defined']);
}

function corsivo_core_revisions_to_keep($number, $post) {
	if (corsivo_core_revisions_locked() || !is_object($post) || !isset($post->post_type)) return $number;
	if (!post_type_supports($post->post_type, 'revisions')) return 0;
	$settings = corsivo_core_wordpress_features_get();
	if ($settings['revisions_mode'] === 'unlimited') return -1;
	if ($settings['revisions_mode'] === 'disabled') return 0;
	if ($settings['revisions_mode'] === 'limited') return $settings['revisions_limit'];
	return $number;
}

function corsivo_core_heartbeat_context($settings) {
	if (!is_admin()) return '';
	$screen = function_exists('get_current_screen') ? get_current_screen() : null;
	if ($screen && in_array($screen->base, ['post', 'site-editor'], true)) return 'editor';
	global $pagenow;
	if (in_array($pagenow, ['post.php', 'post-new.php', 'site-editor.php'], true)) return 'editor';
	if (wp_doing_ajax() && isset($_POST['action']) && $_POST['action'] === 'heartbeat') {
		$screen_id = isset($_POST['screen_id']) && is_string($_POST['screen_id']) ? wp_unslash($_POST['screen_id']) : '';
		if ($screen_id === 'site-editor' || in_array($screen_id, get_post_types(), true)) return 'editor';
	}
	if (isset($settings['screenId']) && is_string($settings['screenId'])) {
		if ($settings['screenId'] === 'site-editor' || in_array($settings['screenId'], get_post_types(), true)) return 'editor';
	}
	return 'admin';
}

function corsivo_core_heartbeat_settings($heartbeat) {
	if (!is_array($heartbeat)) return $heartbeat;
	$context = corsivo_core_heartbeat_context($heartbeat);
	if ($context === '') return $heartbeat;
	$settings = corsivo_core_wordpress_features_get();
	$interval = $settings['heartbeat_' . $context];
	if ($interval === 'native') return $heartbeat;
	$current_interval = isset($heartbeat['interval']) && is_numeric($heartbeat['interval']) ? (int) $heartbeat['interval'] : 0;
	$current_minimum = isset($heartbeat['minimalInterval']) && is_numeric($heartbeat['minimalInterval']) ? (int) $heartbeat['minimalInterval'] : 0;
	$heartbeat['interval'] = max((int) $interval, $current_interval);
	$heartbeat['minimalInterval'] = max((int) $interval, $current_minimum);
	return $heartbeat;
}

function corsivo_core_disable_outgoing_pings() {
	remove_action('do_all_pings', 'do_all_pingbacks');
	remove_action('do_all_pings', 'do_all_trackbacks');
	remove_action('do_all_pings', 'generic_ping');
}

function corsivo_core_close_default_pings() {
	return 'closed';
}

function corsivo_core_remove_pingback_methods($methods) {
	if (!is_array($methods)) return $methods;
	unset($methods['pingback.ping'], $methods['pingback.extensions.getPingbacks']);
	return $methods;
}

function corsivo_core_clear_pingback_links(&$links) {
	$links = [];
}

function corsivo_core_disable_emoji() {
	remove_action('wp_head', 'print_emoji_detection_script', 7);
	remove_action('admin_print_scripts', 'print_emoji_detection_script');
	remove_action('embed_head', 'print_emoji_detection_script');
	remove_action('wp_enqueue_scripts', 'wp_enqueue_emoji_styles');
	remove_action('admin_enqueue_scripts', 'wp_enqueue_emoji_styles');
	remove_action('enqueue_embed_scripts', 'wp_enqueue_emoji_styles');
	remove_action('wp_print_styles', 'print_emoji_styles');
	remove_action('admin_print_styles', 'print_emoji_styles');
	remove_filter('the_content_feed', 'wp_staticize_emoji');
	remove_filter('comment_text_rss', 'wp_staticize_emoji');
	remove_filter('wp_mail', 'wp_staticize_emoji_for_email');
}

function corsivo_core_remove_tinymce_emoji($plugins) {
	return is_array($plugins) ? array_values(array_diff($plugins, ['wpemoji'])) : $plugins;
}

function corsivo_core_wordpress_features_register() {
	$settings = corsivo_core_wordpress_features_get();
	if ($settings['disable_pingbacks_trackbacks']) {
		add_filter('pings_open', '__return_false');
		add_filter('pre_option_default_ping_status', 'corsivo_core_close_default_pings');
		add_filter('pre_option_default_pingback_flag', '__return_zero');
		add_filter('pre_option_ping_sites', '__return_empty_string');
		add_filter('xmlrpc_methods', 'corsivo_core_remove_pingback_methods');
		add_filter('get_to_ping', '__return_empty_array');
		add_action('pre_ping', 'corsivo_core_clear_pingback_links');
		add_action('do_all_pings', 'corsivo_core_disable_outgoing_pings', 0);
	}
	if ($settings['revisions_mode'] !== 'native' && !corsivo_core_revisions_locked()) {
		add_filter('wp_revisions_to_keep', 'corsivo_core_revisions_to_keep', 10, 2);
	}
	if ($settings['heartbeat_admin'] !== 'native' || $settings['heartbeat_editor'] !== 'native') {
		add_filter('heartbeat_settings', 'corsivo_core_heartbeat_settings', 20);
	}
	if ($settings['disable_remote_patterns']) add_filter('should_load_remote_block_patterns', '__return_false');
	if ($settings['disable_emoji']) {
		corsivo_core_disable_emoji();
		add_action('admin_init', 'corsivo_core_disable_emoji');
		add_filter('tiny_mce_plugins', 'corsivo_core_remove_tinymce_emoji');
		add_filter('emoji_svg_url', '__return_false');
	}
}

corsivo_core_wordpress_features_register();
