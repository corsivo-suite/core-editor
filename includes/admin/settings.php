<?php
if (!defined('ABSPATH')) exit;
require_once __DIR__ . '/tabs.php';

function corsivo_theme_get_settings_file_path() {
	if (is_multisite()) {
		$upload_dir = wp_upload_dir(null, false);
		$base_dir = trailingslashit($upload_dir['basedir']) . 'corsivo-theme-editor/' . sanitize_key(get_stylesheet());
		return apply_filters('corsivo_theme_settings_file_path', trailingslashit($base_dir) . 'corsivo-theme.json');
	}

	return apply_filters('corsivo_theme_settings_file_path', trailingslashit(get_stylesheet_directory()) . 'corsivo-theme.json');
}

function corsivo_theme_get_settings_backup_path() {
	return corsivo_theme_get_settings_file_path() . '.bak';
}

function corsivo_theme_decode_settings_json($json) {
    if (!is_string($json) || trim($json) === '') {
        return null;
    }

    $decoded = json_decode($json, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
        return null;
    }

    return $decoded;
}

function corsivo_theme_settings_file_is_corrupt() {
    global $corsivo_theme_settings_file_corrupt;
    return !empty($corsivo_theme_settings_file_corrupt);
}

function corsivo_theme_corrupt_settings_error() {
	return new WP_Error(
		'corsivo_theme_settings_file_corrupt',
		'The Theme settings file (corsivo-theme.json) exists but contains invalid JSON. Saving is disabled to avoid overwriting recoverable data.'
	);
}

function corsivo_theme_normalize_settings_for_hash($value) {
	if (!is_array($value)) {
		return $value;
	}

	if (!array_is_list($value)) {
		ksort($value, SORT_STRING);
	}

	foreach ($value as $key => $item) {
		$value[$key] = corsivo_theme_normalize_settings_for_hash($item);
	}

	return $value;
}

function corsivo_theme_settings_revision($settings = null) {
	if ($settings === null) {
		$settings = corsivo_theme_get_settings();
	}

	$json = wp_json_encode(corsivo_theme_normalize_settings_for_hash($settings), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
	return is_string($json) ? hash('sha256', $json) : '';
}

function corsivo_theme_replace_file($temporary_path, $destination_path) {
	if (@rename($temporary_path, $destination_path)) {
		return true;
	}

	if (!file_exists($destination_path)) {
		return false;
	}

	$rollback_path = $destination_path . '.replace-' . wp_generate_password(12, false, false);
	if (!@rename($destination_path, $rollback_path)) {
		return false;
	}

	if (@rename($temporary_path, $destination_path)) {
		@unlink($rollback_path);
		return true;
	}

	@rename($rollback_path, $destination_path);
	return false;
}

function corsivo_theme_write_settings_file($settings, $expected_revision = null, $after_write = null) {
	if (!is_array($settings)) {
		return new WP_Error('corsivo_theme_invalid_settings', 'Theme settings must be an object.');
	}

	$file_path = corsivo_theme_get_settings_file_path();
	$directory = dirname($file_path);
	if (!is_dir($directory) && !wp_mkdir_p($directory)) {
		return new WP_Error('corsivo_theme_settings_directory_failed', 'Unable to create the Theme settings directory.');
	}

	$json = wp_json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
	if (!is_string($json) || $json === '') {
		return new WP_Error('corsivo_theme_json_encode_failed', 'Unable to encode Theme settings.');
	}

	$json .= PHP_EOL;
	if (strlen($json) > 5 * MB_IN_BYTES) {
		return new WP_Error('corsivo_theme_settings_too_large', 'Theme settings exceed the 5 MB limit.');
	}

	$lock_path = $file_path . '.lock';
	$lock_handle = @fopen($lock_path, 'c');
	$lock_acquired = false;
	$lock_timeout = max(0.1, min(30, (float) apply_filters('corsivo_theme_settings_lock_timeout', 5)));
	$lock_deadline = microtime(true) + $lock_timeout;
	while ($lock_handle && microtime(true) < $lock_deadline) {
		$lock_acquired = @flock($lock_handle, LOCK_EX | LOCK_NB);
		if ($lock_acquired) break;
		usleep(50000);
	}
	if (!$lock_handle || !$lock_acquired) {
		if ($lock_handle) fclose($lock_handle);
		return new WP_Error('corsivo_theme_settings_lock_failed', 'Theme settings are busy. Retry the save in a few seconds.', array('status' => 503));
	}

	$current_settings = array();
	if (file_exists($file_path)) {
		$current_json = @file_get_contents($file_path);
		$current_settings = corsivo_theme_decode_settings_json($current_json);
		if (!is_array($current_settings)) {
			flock($lock_handle, LOCK_UN);
			fclose($lock_handle);
			return corsivo_theme_corrupt_settings_error();
		}
	}

	$current_revision = corsivo_theme_settings_revision($current_settings);
	if (is_string($expected_revision) && $expected_revision !== '' && !hash_equals($current_revision, $expected_revision)) {
		flock($lock_handle, LOCK_UN);
		fclose($lock_handle);
		return new WP_Error(
			'corsivo_theme_revision_conflict',
			'Theme settings changed in another session. Reload before saving again.',
			array('status' => 409, 'current_revision' => $current_revision)
		);
	}

	$temporary_path = tempnam($directory, '.corsivo-theme-');
	if (!$temporary_path) {
		flock($lock_handle, LOCK_UN);
		fclose($lock_handle);
		return new WP_Error('corsivo_theme_settings_write_failed', 'Unable to create a temporary settings file.');
	}

	$written = @file_put_contents($temporary_path, $json, LOCK_EX);
	$temporary_json = $written === strlen($json) ? @file_get_contents($temporary_path) : false;
	if ($temporary_json === false || !is_array(corsivo_theme_decode_settings_json($temporary_json))) {
		@unlink($temporary_path);
		flock($lock_handle, LOCK_UN);
		fclose($lock_handle);
		return new WP_Error('corsivo_theme_settings_write_failed', 'Unable to write a complete Theme settings file.');
	}

	if (file_exists($file_path)) {
		$backup_path = corsivo_theme_get_settings_backup_path();
		$backup_temporary_path = tempnam($directory, '.corsivo-theme-backup-');
		if (!$backup_temporary_path || !@copy($file_path, $backup_temporary_path) || !corsivo_theme_replace_file($backup_temporary_path, $backup_path)) {
			if ($backup_temporary_path) @unlink($backup_temporary_path);
			@unlink($temporary_path);
			flock($lock_handle, LOCK_UN);
			fclose($lock_handle);
			return new WP_Error('corsivo_theme_settings_backup_failed', 'Unable to create a backup of the current Theme settings.');
		}
	}

	if (!corsivo_theme_replace_file($temporary_path, $file_path)) {
		@unlink($temporary_path);
		flock($lock_handle, LOCK_UN);
		fclose($lock_handle);
		return new WP_Error('corsivo_theme_settings_write_failed', 'Unable to replace the Theme settings file.');
	}

	$stored_settings = corsivo_theme_decode_settings_json(@file_get_contents($file_path));
	if (!is_array($stored_settings) || !hash_equals(corsivo_theme_settings_revision($settings), corsivo_theme_settings_revision($stored_settings))) {
		$backup_path = corsivo_theme_get_settings_backup_path();
		if (file_exists($backup_path)) {
			@copy($backup_path, $file_path);
		}
		flock($lock_handle, LOCK_UN);
		fclose($lock_handle);
		return new WP_Error('corsivo_theme_settings_verification_failed', 'Theme settings verification failed and the previous backup was restored.');
	}

	$permissions = fileperms($directory);
	if ($permissions) {
		@chmod($file_path, $permissions & 0000666);
	}

	try {
		global $corsivo_theme_config, $corsivo_theme_settings_file_corrupt;
		$corsivo_theme_config = $settings;
		$corsivo_theme_settings_file_corrupt = false;
		if (is_callable($after_write)) $after_write($settings, $current_settings);
	} finally {
		flock($lock_handle, LOCK_UN);
		fclose($lock_handle);
	}

	return true;
}

function corsivo_theme_get_settings($force = false) {

    global $corsivo_theme_config, $corsivo_theme_settings_file_corrupt;

    if (isset($corsivo_theme_config) && !$force) {
        return $corsivo_theme_config;
    }

    $file_path = corsivo_theme_get_settings_file_path();
    $file_exists = file_exists($file_path);
    $settings = null;

	if ($file_exists && is_readable($file_path)) {
		$settings = corsivo_theme_decode_settings_json(file_get_contents($file_path));
	}

    if (is_array($settings)) {
        $corsivo_theme_settings_file_corrupt = false;
    } elseif ($file_exists) {
        $corsivo_theme_settings_file_corrupt = true;
        $settings = array();
    } else {
		$settings = array();
    }

    $corsivo_theme_config = $settings;

    return $corsivo_theme_config;
}

function corsivo_theme_corrupt_settings_notice() {
    if (!current_user_can('manage_options')) {
        return;
    }

    corsivo_theme_get_settings();

    if (!corsivo_theme_settings_file_is_corrupt()) {
        return;
    }

	$backup_path = corsivo_theme_get_settings_backup_path();
	$backup_settings = file_exists($backup_path) ? corsivo_theme_decode_settings_json(@file_get_contents($backup_path)) : null;
	$restore_url = wp_nonce_url(
		admin_url('admin-post.php?action=corsivo_theme_restore_settings_backup'),
		'corsivo_theme_restore_settings_backup'
	);

	echo '<div class="notice notice-error"><p><strong>Theme:</strong> ' . esc_html(corsivo_theme_corrupt_settings_error()->get_error_message()) . '</p>';
	if (is_array($backup_settings)) {
		echo '<p><a class="button button-primary" href="' . esc_url($restore_url) . '">Restore last valid backup</a></p>';
	}
	echo '</div>';

	$corrupt_hash = file_exists(corsivo_theme_get_settings_file_path()) ? hash_file('sha256', corsivo_theme_get_settings_file_path()) : '';
	$transient_key = 'corsivo_theme_corrupt_logged_' . substr($corrupt_hash, 0, 24);
	if (!get_transient($transient_key)) {
		corsivo_theme_log_activity('settings_corrupt', 'error', 'The Theme settings file contains invalid JSON.', array(), array('after_hash' => $corrupt_hash));
		set_transient($transient_key, 1, DAY_IN_SECONDS);
	}
}
add_action('admin_notices', 'corsivo_theme_corrupt_settings_notice');

function corsivo_theme_restore_settings_backup() {
	if (!current_user_can('manage_options')) {
		wp_die(esc_html__('You are not allowed to restore Theme settings.', 'corsivo-theme-editor'), 403);
	}

	check_admin_referer('corsivo_theme_restore_settings_backup');

	$file_path = corsivo_theme_get_settings_file_path();
	$backup_path = corsivo_theme_get_settings_backup_path();
	$backup_settings = file_exists($backup_path) ? corsivo_theme_decode_settings_json(@file_get_contents($backup_path)) : null;
	if (!is_array($backup_settings)) {
		wp_safe_redirect(admin_url('options-general.php?page=corsivo-theme-editor&corsivo-theme-notice=restore-unavailable'));
		exit;
	}
	$before_hash = file_exists($file_path) ? (hash_file('sha256', $file_path) ?: '') : '';

	$corrupt_copy = $file_path . '.corrupt-' . gmdate('YmdHis');
	if (file_exists($file_path) && !@rename($file_path, $corrupt_copy)) {
		wp_safe_redirect(admin_url('options-general.php?page=corsivo-theme-editor&corsivo-theme-notice=restore-failed'));
		exit;
	}

	$result = corsivo_theme_write_settings_file($backup_settings);
	if (is_wp_error($result)) {
		if (file_exists($corrupt_copy)) corsivo_theme_replace_file($corrupt_copy, $file_path);
		corsivo_theme_log_activity('settings_restore_failed', 'error', $result->get_error_message());
		wp_safe_redirect(admin_url('options-general.php?page=corsivo-theme-editor&corsivo-theme-notice=restore-failed'));
		exit;
	}

	corsivo_theme_clear_theme_json_cache();
	$restored_settings = corsivo_theme_get_settings(true);
	corsivo_theme_log_activity(
		'settings_restored',
		'success',
		'Theme settings restored from the last valid backup.',
		array('preserved_corrupt_file' => basename($corrupt_copy)),
		array('before_hash' => $before_hash, 'after_hash' => corsivo_theme_settings_revision($restored_settings))
	);
	wp_safe_redirect(admin_url('options-general.php?page=corsivo-theme-editor&corsivo-theme-notice=restore-success'));
	exit;
}
add_action('admin_post_corsivo_theme_restore_settings_backup', 'corsivo_theme_restore_settings_backup');

// Apply the chosen front page to WordPress. Under WPML the front page is stored
// in the default language (WPML resolves the translation), so the selected page
// is mapped back to the default language before saving.
function corsivo_theme_apply_front_page($homepage_by_lang) {
	$current_language = corsivo_theme_current_language();
	$default_language = corsivo_theme_default_language();
	$before = array(
		'show_on_front' => (string) get_option('show_on_front', 'posts'),
		'page_on_front' => absint(get_option('page_on_front', 0)),
	);
	$page_id = isset($homepage_by_lang[$default_language]) ? absint($homepage_by_lang[$default_language]) : 0;

	if (!$page_id && isset($homepage_by_lang[$current_language])) {
		$page_id = absint($homepage_by_lang[$current_language]);
	}

	if ($page_id && corsivo_theme_wpml_enabled()) {
		$page_id = corsivo_theme_translate_object_id($page_id, 'page', true, $default_language);
	}

	if ($page_id) {
		$page = get_post($page_id);
		if (!$page || $page->post_type !== 'page') {
			return new WP_Error('corsivo_theme_invalid_homepage', 'The selected homepage is not a valid page.');
		}
	}

	$after = array(
		'show_on_front' => $page_id ? 'page' : 'posts',
		'page_on_front' => $page_id,
	);
	update_option('show_on_front', $after['show_on_front']);
	update_option('page_on_front', $after['page_on_front']);
	$stored_after = array(
		'show_on_front' => (string) get_option('show_on_front', 'posts'),
		'page_on_front' => absint(get_option('page_on_front', 0)),
	);
	if ($stored_after !== $after) {
		corsivo_theme_log_activity(
			'front_page_change_failed',
			'error',
			'WordPress front page settings could not be updated.',
			array('before' => $before, 'attempted' => $after, 'stored' => $stored_after)
		);
		return new WP_Error('corsivo_theme_front_page_update_failed', 'WordPress did not persist the selected front page.');
	}

	if ($before !== $after) {
		corsivo_theme_log_activity(
			'front_page_changed',
			'success',
			'WordPress front page settings changed.',
			array('before' => $before, 'after' => $after, 'default_language' => $default_language),
			array('object_type' => 'page', 'object_key' => (string) $page_id)
		);
	}

	return true;
}

function corsivo_theme_sanitize_homepage_settings($homepage_by_lang) {
	$result = array();
	if (!is_array($homepage_by_lang)) return $result;

	foreach ($homepage_by_lang as $language => $page_id) {
		$language = sanitize_key($language);
		$page_id = absint($page_id);
		if ($page_id) {
			$page = get_post($page_id);
			if (!$page || $page->post_type !== 'page') continue;
		}
		$result[$language] = $page_id;
	}

	return $result;
}

function corsivo_theme_sanitize_custom_slugs($custom_slugs) {
	$result = array();
	if (!is_array($custom_slugs)) return $result;

	foreach ($custom_slugs as $post_type => $languages) {
		$post_type = sanitize_key($post_type);
		if (!post_type_exists($post_type) || !corsivo_theme_custom_slug_post_type_allowed($post_type) || !is_array($languages)) continue;

		foreach ($languages as $language => $values) {
			$language = sanitize_key($language);
			if (!is_array($values)) continue;

			$singular = isset($values['singular']) ? trim(wp_unslash((string) $values['singular'])) : '';
			$plural = isset($values['plural']) ? trim(wp_unslash((string) $values['plural'])) : '';
			$result[$post_type][$language] = array(
				'singular' => $singular === '-' ? '-' : sanitize_title($singular),
				'plural' => $plural === '-' ? '-' : sanitize_title($plural),
				'archive_page' => isset($values['archive_page']) ? absint($values['archive_page']) : 0,
			);

			if ($result[$post_type][$language]['archive_page']) {
				$page = get_post($result[$post_type][$language]['archive_page']);
				if (!$page || $page->post_type !== 'page') {
					$result[$post_type][$language]['archive_page'] = 0;
				}
			}
		}
	}

	return $result;
}

function corsivo_theme_create_footer_page() {
	$existing_id = absint(get_option('corsivo_theme_footer_page_id'));
	if ($existing_id && get_post($existing_id)) {
		return $existing_id;
	}

	$page_id = wp_insert_post(array(
		'post_title' => 'Footer',
		'post_status' => 'private',
		'post_type' => 'page',
		'post_content' => '<!-- wp:paragraph --><p>© ' . current_time('Y') . ' ' . esc_html(get_bloginfo('name')) . '</p><!-- /wp:paragraph -->',
	), true);

	if (is_wp_error($page_id)) {
		return $page_id;
	}

	update_option('corsivo_theme_footer_page_id', (int) $page_id, false);
	corsivo_theme_log_activity('footer_created', 'success', 'The Gutenberg footer page was created.', array(), array('object_type' => 'page', 'object_key' => (string) $page_id));
	return (int) $page_id;
}

function corsivo_theme_changed_settings_sections($before, $after) {
	$sections = array_unique(array_merge(array_keys($before), array_keys($after)));
	return array_values(array_filter($sections, function ($section) use ($before, $after) {
		return ($before[$section] ?? null) !== ($after[$section] ?? null);
	}));
}

function corsivo_theme_save_settings($settings, $expected_revision = null) {
	$old_settings = corsivo_theme_get_settings(true);
	if (corsivo_theme_settings_file_is_corrupt()) {
		return corsivo_theme_corrupt_settings_error();
	}

	$new_settings = $old_settings;
	if (isset($settings['global']) && is_array($settings['global'])) {
		$global_keys = array(
			'tab_functional_pages',
			'tab_custom_slugs',
			'core_renders',
			'gutenberg_footer',
			'cpt_menu_parent',
			'force_style_injection',
			'minify_css',
			'minify_js',
		);
		$new_settings['global'] = isset($old_settings['global']) && is_array($old_settings['global']) ? $old_settings['global'] : array();
		foreach ($global_keys as $global_key) {
			if (array_key_exists($global_key, $settings['global'])) $new_settings['global'][$global_key] = !empty($settings['global'][$global_key]);
		}
	}

	if (isset($settings['functional_pages']) && is_array($settings['functional_pages'])) {
		$new_settings['functional_pages'] = array();
		foreach ($settings['functional_pages'] as $slug => $languages) {
			if (!is_array($languages)) continue;
			foreach ($languages as $language => $page_id) {
				$new_settings['functional_pages'][sanitize_key($slug)][sanitize_key($language)] = absint($page_id);
			}
		}
	}

	if (isset($settings['wordpress']['homepage'])) {
		$homepage_settings = corsivo_theme_sanitize_homepage_settings($settings['wordpress']['homepage']);
		foreach ($homepage_settings as $language => $page_id) {
			$new_settings['wordpress']['homepage'][$language] = $page_id;
		}
	}

	if (isset($settings['custom_slugs'])) {
		$custom_slugs = corsivo_theme_sanitize_custom_slugs($settings['custom_slugs']);
		foreach ($custom_slugs as $post_type => $languages) {
			foreach ($languages as $language => $values) {
				$new_settings['custom_slugs'][$post_type][$language] = $values;
			}
		}
	}

	$before_hash = corsivo_theme_settings_revision($old_settings);
	$attempted_hash = corsivo_theme_settings_revision($new_settings);
	$save_result = corsivo_theme_write_settings_file($new_settings, $expected_revision);
	if (is_wp_error($save_result)) {
		corsivo_theme_log_activity('settings_save_failed', 'error', $save_result->get_error_message(), array(), array('before_hash' => $before_hash, 'after_hash' => $attempted_hash));
		return $save_result;
	}
	$stored_settings = corsivo_theme_get_settings(true);
	$after_hash = corsivo_theme_settings_revision($stored_settings);

	$side_effect_errors = array();
	if (($old_settings['wordpress']['homepage'] ?? array()) !== ($new_settings['wordpress']['homepage'] ?? array())) {
		$homepage_result = corsivo_theme_apply_front_page($new_settings['wordpress']['homepage'] ?? array());
		if (is_wp_error($homepage_result)) $side_effect_errors[] = $homepage_result->get_error_message();
	}

	if (!empty($new_settings['global']['gutenberg_footer'])) {
		$footer_result = corsivo_theme_create_footer_page();
		if (is_wp_error($footer_result)) $side_effect_errors[] = $footer_result->get_error_message();
	}

	corsivo_theme_clear_theme_json_cache();
	if (($old_settings['custom_slugs'] ?? array()) !== ($new_settings['custom_slugs'] ?? array())) {
		corsivo_theme_request_rewrite_flush();
	}

	$changed_sections = corsivo_theme_changed_settings_sections($old_settings, $new_settings);
	corsivo_theme_log_activity(
		'settings_saved',
		empty($side_effect_errors) ? 'success' : 'warning',
		empty($side_effect_errors) ? 'Theme settings saved.' : 'Theme settings saved with follow-up errors.',
		array('sections' => $changed_sections, 'errors' => $side_effect_errors),
		array('before_hash' => $before_hash, 'after_hash' => $after_hash)
	);

	if (!empty($side_effect_errors)) {
		return new WP_Error('corsivo_theme_settings_side_effect_failed', implode(' ', $side_effect_errors), array('settings_saved' => true));
	}

	return true;
}

// Add settings page to the menu
function corsivo_theme_settings_menu() {
    add_options_page('Theme', 'Theme', 'manage_options', 'corsivo-theme-editor', 'corsivo_theme_settings_page');
}
add_action('admin_menu', 'corsivo_theme_settings_menu');

function corsivo_theme_hide_developer_settings_menu() {
	if (current_user_can('manage_corsivo_developer_tools')) {
		return;
	}

	remove_submenu_page('options-general.php', 'corsivo-theme-editor');
}
add_action('admin_menu', 'corsivo_theme_hide_developer_settings_menu', 999);

// Enqueue React configurator on the SH admin page
function corsivo_theme_enqueue_admin($hook) {
    if ($hook !== 'settings_page_corsivo-theme-editor') return;

    wp_enqueue_style('corsivo_theme_admin_style', plugin_dir_url(__FILE__) . 'style.css', array(), filemtime(plugin_dir_path(__FILE__) . 'style.css'), 'all');
    wp_enqueue_script('corsivo_theme_admin_script', plugin_dir_url(__FILE__) . 'script.js', array(), filemtime(plugin_dir_path(__FILE__) . 'script.js'), true);

	$plugin_dir = plugin_dir_path(dirname(__DIR__));
	$build_dir = $plugin_dir . 'configurator/build/';
	$asset_file = $build_dir . 'index.asset.php';
	$required_assets = array(
		$asset_file,
		$build_dir . 'index.js',
		$build_dir . 'index.css',
		$build_dir . 'index-rtl.css',
	);

	foreach ($required_assets as $required_asset) {
		if (file_exists($required_asset)) {
			continue;
		}
		add_action('admin_notices', function () {
			echo '<div class="notice notice-error"><p>The Theme configurator build is missing. Rebuild the configurator assets before editing design settings.</p></div>';
		});
		return;
	}

    $asset = require $asset_file;

    wp_enqueue_script(
        'corsivo-theme-configurator',
        plugin_dir_url(dirname(__DIR__)) . 'configurator/build/index.js',
        $asset['dependencies'],
        $asset['version'],
        true
    );
	wp_set_script_translations('corsivo-theme-configurator', 'corsivo-theme-editor');

    wp_enqueue_style(
        'corsivo-theme-configurator',
        plugin_dir_url(dirname(__DIR__)) . 'configurator/build/index.css',
        array('wp-components'),
        $asset['version']
    );
	wp_style_add_data('corsivo-theme-configurator', 'rtl', 'replace');

	$settings = corsivo_theme_get_settings();
	$configurator = isset($settings['configurator']) ? $settings['configurator'] : array();
	$current_lang = corsivo_theme_current_language();

	$active_languages = corsivo_theme_active_languages();
	$language_options = array();
	foreach ($active_languages as $language_key => $language) {
		$language_code = sanitize_key($language['code'] ?? $language_key);
		if ($language_code === '') continue;
		$language_options[] = array(
			'code' => $language_code,
			'label' => sanitize_text_field($language['native_name'] ?? $language['translated_name'] ?? strtoupper($language_code)),
		);
	}
	if (empty($language_options)) {
		$language_options[] = array('code' => 'default', 'label' => __('Default', 'corsivo-theme-editor'));
	}
	$language_codes = array_column($language_options, 'code');
	if (!in_array($current_lang, $language_codes, true)) {
		$default_lang = corsivo_theme_default_language();
		$current_lang = in_array($default_lang, $language_codes, true) ? $default_lang : $language_codes[0];
	}
    // Filter-defined functional pages
    $filter_pages = apply_filters('corsivo_theme_pages', array());

    // Existing page assignments
    $page_assignments = isset($settings['functional_pages']) ? $settings['functional_pages'] : array();

    // Auto-detect CPT-based contexts
    $cpt_contexts = array();
	$post_types = get_post_types(array('public' => true, '_builtin' => false), 'objects');
    foreach ($post_types as $pt) {
		$cpt_contexts[] = array('value' => 'single-' . $pt->name, 'label' => 'Single ' . $pt->labels->singular_name);
        if ($pt->has_archive) {
            $cpt_contexts[] = array('value' => 'post-type-archive-' . $pt->name, 'label' => $pt->labels->name . ' Archive');
        }
    }

    // Auto-detect registered page templates
    $template_contexts = array();
    $templates = wp_get_theme()->get_page_templates();
    foreach ($templates as $file => $name) {
        $template_contexts[] = array('value' => corsivo_theme_page_template_context_slug($file), 'label' => 'Template: ' . $name);
    }

    // Get registered block styles for the Core Components tab.
    // Read from both WP_Block_Type_Registry (block.json styles) and
    // WP_Block_Styles_Registry (register_block_style() calls by themes/plugins).
    $registered_block_styles = array();
    $cc_block_types = array('core/button', 'core/quote', 'core/code', 'core/separator', 'core/table', 'core/image', 'core/navigation', 'core/search', 'core/pullquote', 'core/list', 'core/group', 'core/columns');
    $our_custom_slugs = array();
    $saved_bs = isset($configurator['core_components']['blockStyles']) ? $configurator['core_components']['blockStyles'] : array();
    foreach ($saved_bs as $sbs) {
        if (empty($sbs['fromCore']) && !empty($sbs['target']) && !empty($sbs['slug'])) {
            $our_custom_slugs[] = $sbs['target'] . '::' . $sbs['slug'];
        }
    }

    $all_core_styles = array(); // keyed by "block::slug"

    // 1. Read from WP_Block_Type_Registry (block.json "styles" field)
    if (class_exists('WP_Block_Type_Registry')) {
        $block_reg = WP_Block_Type_Registry::get_instance();
        foreach ($cc_block_types as $bt) {
            $bt_obj = $block_reg->get_registered($bt);
            if ($bt_obj && !empty($bt_obj->styles) && is_array($bt_obj->styles)) {
                foreach ($bt_obj->styles as $sd) {
                    if (empty($sd['name'])) continue;
                    $key = $bt . '::' . $sd['name'];
                    if (in_array($key, $our_custom_slugs)) continue;
                    $all_core_styles[$key] = array(
                        'block' => $bt,
                        'name'  => $sd['name'],
                        'label' => isset($sd['label']) ? $sd['label'] : $sd['name'],
                        'isDefault' => !empty($sd['isDefault']) || !empty($sd['is_default']),
                    );
                }
            }
        }
    }

    // 2. Read from WP_Block_Styles_Registry (register_block_style() calls)
    if (class_exists('WP_Block_Styles_Registry')) {
        $bs_registry = WP_Block_Styles_Registry::get_instance();
        foreach ($cc_block_types as $bt) {
            $reg_styles = $bs_registry->get_registered_styles_for_block($bt);
            if (!empty($reg_styles)) {
                foreach ($reg_styles as $sn => $sd) {
                    $key = $bt . '::' . $sn;
                    if (in_array($key, $our_custom_slugs)) continue;
                    if (!isset($all_core_styles[$key])) {
                        $all_core_styles[$key] = array(
                            'block' => $bt,
                            'name'  => $sn,
                            'label' => isset($sd['label']) ? $sd['label'] : $sn,
                            'isDefault' => !empty($sd['is_default']),
                        );
                    }
                }
            }
        }
    }

    // Group by block name
    foreach ($all_core_styles as $sd) {
        $bt = $sd['block'];
        if (!isset($registered_block_styles[$bt])) {
            $registered_block_styles[$bt] = array();
        }
        $registered_block_styles[$bt][] = array(
            'name'      => $sd['name'],
            'label'     => $sd['label'],
            'isDefault' => $sd['isDefault'],
        );
    }

    $resolved_theme_json = array();
    $core_theme_json = array();
    if (class_exists('WP_Theme_JSON_Resolver')) {
        $theme_data = WP_Theme_JSON_Resolver::get_merged_data();
        if ($theme_data) {
            $resolved_theme_json = $theme_data->get_data();
        }
        // WP_Theme_JSON::get_data() drops the 'default' origin, so core presets
        // must be exposed via get_settings(), where they stay keyed by origin.
        $core_data = WP_Theme_JSON_Resolver::get_core_data();
        if ($core_data) {
            $core_theme_json = array('settings' => $core_data->get_settings());
        }
    }

	$available_contexts = apply_filters('corsivo_theme_available_contexts', array());

	wp_localize_script('corsivo-theme-configurator', 'corsivoThemeEditor', array(
        'restUrl'            => rest_url('corsivo/v1/theme-config/'),
        'nonce'              => wp_create_nonce('wp_rest'),
        'woff2DecoderUrl'    => add_query_arg(
            'ver',
            $asset['version'],
            plugin_dir_url(dirname(__DIR__)) . 'configurator/build/wawoff2-decompress.js'
        ),
        'registeredBlockStyles' => !empty($registered_block_styles) ? $registered_block_styles : (object) array(),
        'resolvedThemeJson'  => !empty($resolved_theme_json) ? $resolved_theme_json : (object) array(),
        'coreThemeJson'      => !empty($core_theme_json) ? $core_theme_json : (object) array(),
        'configData'         => array(
            'fonts'            => isset($configurator['fonts']) ? $configurator['fonts'] : array(),
            'colors'           => isset($configurator['colors']) ? $configurator['colors'] : array(),
            'breakpoints'      => isset($configurator['breakpoints']) ? $configurator['breakpoints'] : (object) array(),
            'custom_fonts'     => isset($configurator['custom_fonts']) ? $configurator['custom_fonts'] : array(),
            'install_fonts'    => isset($configurator['install_fonts']) ? $configurator['install_fonts'] : array(),
            'functional_pages' => isset($configurator['functional_pages']) ? $configurator['functional_pages'] : array(),
            'global_styles'    => isset($configurator['global_styles']) ? $configurator['global_styles'] : (object) array(),
            'style_templates'  => isset($configurator['style_templates']) ? $configurator['style_templates'] : array(),
            'context_styles'   => isset($configurator['context_styles']) ? $configurator['context_styles'] : array(),
            'core_components'  => isset($configurator['core_components']) ? $configurator['core_components'] : (object) array(),
            'spacing'          => isset($configurator['spacing']) ? $configurator['spacing'] : (object) array(),
        ),
		'activeLanguages'    => $language_options,
        'filterPages'        => !empty($filter_pages) ? $filter_pages : (object) array(),
        'pageAssignments'    => !empty($page_assignments) ? $page_assignments : (object) array(),
		'currentLang'        => $current_lang,
		'configRevision'     => corsivo_theme_settings_revision($settings),
		'cptContexts'        => $cpt_contexts,
		'templateContexts'   => $template_contexts,
		'availableContexts'  => is_array($available_contexts) ? array_values($available_contexts) : array(),
    ));
}
add_action('admin_enqueue_scripts', 'corsivo_theme_enqueue_admin');

// Settings page content
function corsivo_theme_settings_page() {
	if (!current_user_can('manage_options')) {
		wp_die(esc_html__('You are not allowed to manage Theme settings.', 'corsivo-theme-editor'), 403);
	}

	$settings = corsivo_theme_get_settings();
	$current_lang = corsivo_theme_current_language();
	$request_method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper(sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD']))) : 'GET';
	$notice_key = isset($_GET['corsivo-theme-notice']) ? sanitize_key(wp_unslash($_GET['corsivo-theme-notice'])) : '';
	$notices = array(
		'log-settings-saved' => array('success', 'Activity log settings saved.'),
		'log-cleared' => array('success', 'Activity log cleared.'),
		'integrations-saved' => array('success', 'Integration settings saved.'),
		'restore-success' => array('success', 'Theme settings restored from the last valid backup.'),
		'restore-unavailable' => array('error', 'No valid settings backup is available.'),
		'restore-failed' => array('error', 'Theme settings could not be restored.'),
	);
	if (isset($notices[$notice_key])) {
		echo '<div class="notice notice-' . esc_attr($notices[$notice_key][0]) . ' is-dismissible"><p>' . esc_html($notices[$notice_key][1]) . '</p></div>';
	}

	if ($request_method === 'POST') {
		check_admin_referer('corsivo_theme_settings_action', 'corsivo_theme_settings_nonce');

		$settings['global'] = array_merge(
			isset($settings['global']) && is_array($settings['global']) ? $settings['global'] : array(),
			array(
				'tab_functional_pages' => isset($_POST['corsivo-theme-tab-functional-pages']),
				'tab_custom_slugs' => isset($_POST['corsivo-theme-tab-custom-slugs']),
				'core_renders' => isset($_POST['corsivo-theme-core-renders']),
				'gutenberg_footer' => isset($_POST['corsivo-theme-gutenberg-footer']),
				'cpt_menu_parent' => isset($_POST['corsivo-theme-cpt-menu-parent']),
				'force_style_injection' => isset($_POST['corsivo-theme-force-style-injection']),
				'minify_css' => isset($_POST['corsivo-theme-minify-css']),
				'minify_js' => isset($_POST['corsivo-theme-minify-js']),
			)
		);
		$settings['custom_slugs'] = isset($_POST['custom_slugs']) && is_array($_POST['custom_slugs'])
			? wp_unslash($_POST['custom_slugs'])
			: array();
		if (isset($_POST['wordpress']) && is_array($_POST['wordpress'])) {
			$settings['wordpress'] = wp_unslash($_POST['wordpress']);
		}

		$expected_revision = isset($_POST['settings_revision'])
			? sanitize_text_field(wp_unslash($_POST['settings_revision']))
			: '';
		$save_result = corsivo_theme_save_settings($settings, $expected_revision);
		if (is_wp_error($save_result)) {
			$error_data = $save_result->get_error_data('corsivo_theme_settings_side_effect_failed');
			$notice_class = is_array($error_data) && !empty($error_data['settings_saved']) ? 'notice-warning' : 'notice-error';
			echo '<div class="notice ' . esc_attr($notice_class) . ' is-dismissible"><p>' . esc_html($save_result->get_error_message()) . '</p></div>';
		} else {
			echo '<div class="notice notice-success is-dismissible"><p>Settings saved successfully.</p></div>';
		}
	}

    $settings = corsivo_theme_get_settings(true);
    ?>
    <div class="corsivo-theme-wrap">
        <h1>Theme</h1>

        <?php $global = isset($settings['global']) ? $settings['global'] : array(); ?>
		<div class="corsivo-theme-nav" role="tablist" aria-label="Theme sections">
			<button type="button" class="button" role="tab" aria-selected="false" data-section="global-settings">Settings</button>
			<button type="button" class="button" role="tab" aria-selected="false" data-section="wordpress">WordPress</button>
			<?php if (!empty($global['tab_functional_pages'])): ?>
				<button type="button" class="button" role="tab" aria-selected="false" data-section="functional-pages">Functional Pages</button>
			<?php endif; ?>
			<?php if (!empty($global['tab_custom_slugs'])): ?>
				<button type="button" class="button" role="tab" aria-selected="false" data-section="custom-slugs">Custom Slugs</button>
			<?php endif; ?>
			<button type="button" class="button" role="tab" aria-selected="false" data-section="breakpoints">Breakpoints</button>
			<button type="button" class="button" role="tab" aria-selected="false" data-section="spacing">Spacing</button>
			<button type="button" class="button" role="tab" aria-selected="false" data-section="colors">Colors</button>
			<button type="button" class="button" role="tab" aria-selected="false" data-section="fonts">Fonts</button>
			<button type="button" class="button" role="tab" aria-selected="false" data-section="styles">Styles</button>
			<button type="button" class="button" role="tab" aria-selected="false" data-section="templates">Templates</button>
			<button type="button" class="button" role="tab" aria-selected="false" data-section="overview">Overview</button>
			<button type="button" class="button" role="tab" aria-selected="false" data-section="integrations">Integrations</button>
			<button type="button" class="button" role="tab" aria-selected="false" data-section="history">History</button>
        </div>

        <!-- React sections (outside form to prevent submit on button clicks) -->
        <div class="corsivo-theme-section" data-section="overview">
            <div id="corsivo-theme-configurator-overview"></div>
        </div>
        <div class="corsivo-theme-section" data-section="fonts">
            <div id="corsivo-theme-configurator-fonts"></div>
        </div>
        <div class="corsivo-theme-section" data-section="styles">
            <div id="corsivo-theme-configurator-styles"></div>
        </div>
        <div class="corsivo-theme-section" data-section="colors">
            <div id="corsivo-theme-configurator-colors"></div>
        </div>
        <div class="corsivo-theme-section" data-section="breakpoints">
            <div id="corsivo-theme-configurator-breakpoints"></div>
        </div>
        <div class="corsivo-theme-section" data-section="spacing">
            <div id="corsivo-theme-configurator-spacing"></div>
        </div>
        <div class="corsivo-theme-section" data-section="functional-pages">
            <div id="corsivo-theme-configurator-functional-pages"></div>
        </div>
		<div class="corsivo-theme-section" data-section="templates">
			<div id="corsivo-theme-configurator-templates"></div>
		</div>
		<div class="corsivo-theme-section" data-section="integrations">
			<?php corsivo_theme_integrations_render(); ?>
		</div>
		<div class="corsivo-theme-section" data-section="history">
			<?php corsivo_theme_activity_log_render(); ?>
		</div>

        <!-- PHP form sections -->
		<form id="corsivo-theme-settings-form" method="post" action="">
			<?php wp_nonce_field('corsivo_theme_settings_action', 'corsivo_theme_settings_nonce'); ?>
			<input type="hidden" name="settings_revision" value="<?= esc_attr(corsivo_theme_settings_revision($settings)) ?>">
            <div class="corsivo-theme-section" data-section="global-settings">
                <?php corsivo_theme_global_settings_render($settings, $current_lang); ?>
            </div>
            <div class="corsivo-theme-section" data-section="wordpress">
                <?php corsivo_theme_wordpress_render($settings, $current_lang); ?>
            </div>
            <div class="corsivo-theme-section" data-section="custom-slugs">
                <?php corsivo_theme_custom_slugs_render($settings, $current_lang); ?>
            </div>
            <p class="submit" id="corsivo-theme-php-submit">
                <input type="submit" name="submit" id="submit" class="button button-primary" value="Save Changes">
            </p>
        </form>
    </div>
    <?php
}
