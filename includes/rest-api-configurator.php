<?php
if (!defined('ABSPATH')) exit;

add_action('rest_api_init', function () {

	// GET config — read configurator data
	register_rest_route('corsivo/v1/theme-config', '/config', array(
		'methods'  => 'GET',
		'callback' => 'corsivo_theme_get_config',
		'permission_callback' => function () {
			return current_user_can('manage_options');
		},
	));

	// POST config — save configurator data
	register_rest_route('corsivo/v1/theme-config', '/config', array(
		'methods'  => 'POST',
		'callback' => 'corsivo_theme_save_config',
		'permission_callback' => function () {
			return current_user_can('manage_options');
		},
	));

	// POST rebuild — trigger CSS rebuild
	register_rest_route('corsivo/v1/theme-config', '/rebuild', array(
		'methods'  => 'POST',
		'callback' => 'corsivo_theme_rebuild',
		'permission_callback' => function () {
			return current_user_can('manage_options');
		},
	));

	// POST upload-font — upload font file to theme directory
	register_rest_route('corsivo/v1/theme-config', '/upload-font', array(
		'methods'  => 'POST',
		'callback' => 'corsivo_theme_upload_font',
		'permission_callback' => function () {
			return current_user_can('manage_options');
		},
	));

	// GET theme-fonts — list font files in theme/fonts/
	register_rest_route('corsivo/v1/theme-config', '/theme-fonts', array(
		'methods'  => 'GET',
		'callback' => 'corsivo_theme_list_theme_fonts',
		'permission_callback' => function () {
			return current_user_can('manage_options');
		},
	));
});

/**
 * GET /config — returns the configurator section of corsivo-theme.json
 */
function corsivo_theme_get_config() {
	$settings = corsivo_theme_get_settings();
	$configurator = isset($settings['configurator']) ? $settings['configurator'] : array();

	$response = rest_ensure_response(array(
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
	));
	$response->header('ETag', '"' . corsivo_theme_settings_revision($settings) . '"');
	return $response;
}

function corsivo_theme_sanitize_css_declarations($css) {
	$css = str_replace("\0", '', (string) $css);
	$css = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $css);
	$css = str_ireplace('</', '', $css);
	if (strlen($css) > 20000 || preg_match('/[{}]|@import|expression\s*\(|javascript\s*:/i', $css)) {
		return '';
	}

	$declarations = corsivo_theme_split_css_declarations($css);
	$sanitized = array();
	foreach ($declarations as $declaration) {
		$colon = strpos($declaration, ':');
		if ($colon === false) continue;
		$property = trim(substr($declaration, 0, $colon));
		$value = trim(substr($declaration, $colon + 1));
		if (!preg_match('/^(?:--[a-z0-9_-]+|-?[a-z][a-z0-9-]*)$/i', $property) || $value === '') continue;
		$sanitized[] = strtolower($property) . ': ' . $value;
	}

	return implode(";\n", $sanitized) . (!empty($sanitized) ? ';' : '');
}

function corsivo_theme_sanitize_config_value($value, $key = '', $depth = 0) {
	if ($depth > 14) return null;
	if (is_bool($value) || is_int($value) || is_float($value) || $value === null) return $value;

	if (is_array($value)) {
		$result = array();
		$count = 0;
		foreach ($value as $child_key => $child_value) {
			if (++$count > 1000) break;
			if (is_string($child_key) && !preg_match('/^[A-Za-z0-9_:\/-]{1,96}$/', $child_key)) continue;
			if (is_string($child_key) && strpos($child_key, '_') === 0) continue;
			$result[$child_key] = corsivo_theme_sanitize_config_value($child_value, (string) $child_key, $depth + 1);
		}
		return $result;
	}

	if (!is_string($value)) return null;
	if (strlen($value) > 20000) $value = substr($value, 0, 20000);

	if ($key === 'css' || in_array($key, array(':hover', ':focus', ':focus-visible', ':active'), true)) {
		return corsivo_theme_sanitize_css_declarations($value);
	}
	if (in_array($key, array('url', 'src'), true)) {
		return esc_url_raw($value, array('http', 'https'));
	}
	if (in_array($key, array('slug', 'varname'), true)) {
		return sanitize_title($value);
	}
	if (in_array($key, array('context', 'target'), true)) {
		return preg_replace('/[^a-z0-9_:\/-]/', '', strtolower($value));
	}

	return sanitize_textarea_field($value);
}

function corsivo_theme_validate_unique_slugs($items, $section) {
	$seen = array();
	foreach ($items as $item) {
		if (!is_array($item) || empty($item['slug'])) {
			return new WP_Error('corsivo_theme_missing_slug', sprintf('%s contains an item without a slug.', $section), array('status' => 400));
		}
		$slug = $item['slug'];
		if (isset($seen[$slug])) {
			return new WP_Error('corsivo_theme_duplicate_slug', sprintf('%s contains the duplicate slug "%s".', $section, $slug), array('status' => 400));
		}
		$seen[$slug] = true;
	}
	return true;
}

function corsivo_theme_validate_configurator_format($request_data) {
	$font_fields = array('font-family', 'font-size', 'line-height', 'font-weight', 'font-style', 'letter-spacing', 'text-transform', 'text-decoration');
	$fonts = $request_data['fonts'] ?? array();
	if (!is_array($fonts)) {
		return new WP_Error('corsivo_theme_unsupported_config_format', 'Fonts must be a list.', array('status' => 400));
	}
	foreach ($fonts as $font) {
		if (!is_array($font) || !array_key_exists('css', $font) || !is_string($font['css'])) {
			return new WP_Error('corsivo_theme_unsupported_config_format', 'Fonts must use CSS declarations.', array('status' => 400));
		}
		foreach ($font_fields as $field) {
			if (array_key_exists($field, $font)) {
				return new WP_Error('corsivo_theme_unsupported_config_format', 'Fonts contain unsupported field-based declarations.', array('status' => 400));
			}
		}
		if (isset($font['breakpoints'])) {
			if (!is_array($font['breakpoints'])) {
				return new WP_Error('corsivo_theme_unsupported_config_format', 'Font breakpoints must contain CSS declarations.', array('status' => 400));
			}
			foreach ($font['breakpoints'] as $breakpoint) {
				if (!is_array($breakpoint) || !array_key_exists('css', $breakpoint) || !is_string($breakpoint['css'])) {
					return new WP_Error('corsivo_theme_unsupported_config_format', 'Font breakpoints must contain CSS declarations.', array('status' => 400));
				}
			}
		}
	}

	$custom_fonts = $request_data['custom_fonts'] ?? array();
	if (!is_array($custom_fonts)) {
		return new WP_Error('corsivo_theme_unsupported_config_format', 'Google Fonts must be a list.', array('status' => 400));
	}
	foreach ($custom_fonts as $custom_font) {
		if (!is_array($custom_font) || !isset($custom_font['url']) || !is_string($custom_font['url']) || !isset($custom_font['fonts']) || !is_array($custom_font['fonts'])) {
			return new WP_Error('corsivo_theme_unsupported_config_format', 'Google Fonts must use structured entries.', array('status' => 400));
		}
	}

	$core_components = $request_data['core_components'] ?? array();
	if (!is_array($core_components)) {
		return new WP_Error('corsivo_theme_unsupported_config_format', 'Core components must be an object.', array('status' => 400));
	}
	if (array_key_exists('blocks', $core_components)) {
		return new WP_Error('corsivo_theme_unsupported_config_format', 'Core components must use block styles.', array('status' => 400));
	}
	$elements = $core_components['elements'] ?? array();
	if (!is_array($elements)) {
		return new WP_Error('corsivo_theme_unsupported_config_format', 'Core elements must be an object.', array('status' => 400));
	}
	foreach ($elements as $element) {
		if (!is_array($element) || !array_key_exists('css', $element) || !is_string($element['css'])) {
			return new WP_Error('corsivo_theme_unsupported_config_format', 'Core elements must use CSS declarations.', array('status' => 400));
		}
		foreach (array(':hover', ':focus', ':focus-visible', ':active') as $pseudo) {
			if (isset($element[$pseudo]) && !is_string($element[$pseudo])) {
				return new WP_Error('corsivo_theme_unsupported_config_format', 'Core element states must use CSS declarations.', array('status' => 400));
			}
		}
	}
	$block_styles = $core_components['blockStyles'] ?? array();
	if (!is_array($block_styles)) {
		return new WP_Error('corsivo_theme_unsupported_config_format', 'Block styles must be a list.', array('status' => 400));
	}
	foreach ($block_styles as $block_style) {
		if (!is_array($block_style) || !array_key_exists('css', $block_style) || !is_string($block_style['css'])) {
			return new WP_Error('corsivo_theme_unsupported_config_format', 'Block styles must use CSS declarations.', array('status' => 400));
		}
		foreach (array(':hover', ':focus', ':focus-visible', ':active') as $pseudo) {
			if (isset($block_style[$pseudo]) && !is_string($block_style[$pseudo])) {
				return new WP_Error('corsivo_theme_unsupported_config_format', 'Block style states must use CSS declarations.', array('status' => 400));
			}
		}
	}

	return true;
}

function corsivo_theme_sanitize_configurator_request($request_data) {
	$format_validation = corsivo_theme_validate_configurator_format($request_data);
	if (is_wp_error($format_validation)) return $format_validation;

	$sections = array(
		'fonts' => array(),
		'colors' => array(),
		'breakpoints' => array(),
		'custom_fonts' => array(),
		'install_fonts' => array(),
		'functional_pages' => array(),
		'global_styles' => array(),
		'style_templates' => array(),
		'context_styles' => array(),
		'core_components' => array(),
		'spacing' => array(),
	);

	foreach ($sections as $section => $default) {
		$value = isset($request_data[$section]) && is_array($request_data[$section]) ? $request_data[$section] : $default;
		$sections[$section] = corsivo_theme_sanitize_config_value($value, $section);
	}

	foreach (array('fonts' => 'Fonts', 'colors' => 'Colors', 'functional_pages' => 'Functional pages', 'style_templates' => 'Style templates') as $section => $label) {
		$validation = corsivo_theme_validate_unique_slugs($sections[$section], $label);
		if (is_wp_error($validation)) return $validation;
	}

	$font_slugs = array_fill_keys(array_column($sections['fonts'], 'slug'), true);
	$color_slugs = array_fill_keys(array_column($sections['colors'], 'slug'), true);
	$template_slugs = array_fill_keys(array_column($sections['style_templates'], 'slug'), true);
	$block_styles = $sections['core_components']['blockStyles'] ?? array();
	if (is_array($block_styles)) {
		$seen = array();
		foreach ($block_styles as $block_style) {
			if (!is_array($block_style) || empty($block_style['target']) || empty($block_style['slug'])) continue;
			$key = $block_style['target'] . '::' . $block_style['slug'];
			if (isset($seen[$key])) {
				return new WP_Error('corsivo_theme_duplicate_block_style', 'Core components contain a duplicate block style.', array('status' => 400));
			}
			$seen[$key] = true;
		}
		foreach ($block_styles as $block_style) {
			if (!is_array($block_style) || empty($block_style['inherits'])) continue;
			if (strpos($block_style['inherits'], 'font:') === 0 && !isset($font_slugs[substr($block_style['inherits'], 5)])) {
				return new WP_Error('corsivo_theme_missing_inherited_font', 'A block style inherits from a font that no longer exists.', array('status' => 400));
			}
			if (strpos($block_style['inherits'], 'bs:') === 0 && !isset($seen[substr($block_style['inherits'], 3)])) {
				return new WP_Error('corsivo_theme_missing_inherited_style', 'A block style inherits from a block style that no longer exists.', array('status' => 400));
			}
		}
	}

	$default_templates = 0;
	foreach ($sections['style_templates'] as $template) {
		if (!empty($template['isDefault'])) $default_templates++;
	}
	if ($default_templates > 1) {
		return new WP_Error('corsivo_theme_multiple_default_templates', 'Only one style template can be the default.', array('status' => 400));
	}

	$styles_to_validate = array_merge(array($sections['global_styles']), $sections['style_templates']);
	foreach ($styles_to_validate as $style) {
		if (!is_array($style)) continue;
		$font_references = array(
			$style['body']['font'] ?? '',
			$style['heading']['font'] ?? '',
			$style['titleStyle'] ?? '',
			$style['defaultParagraphStyle'] ?? '',
			$style['defaultHeadingStyle'] ?? '',
		);
		foreach ($font_references as $font_reference) {
			if ($font_reference !== '' && !isset($font_slugs[$font_reference])) {
				return new WP_Error('corsivo_theme_missing_font_reference', 'A style references a font that no longer exists.', array('status' => 400));
			}
		}
		$color_references = array(
			$style['body']['background'] ?? '',
			$style['body']['color'] ?? '',
			$style['heading']['color'] ?? '',
			$style['link']['color'] ?? '',
			$style['link']['hoverColor'] ?? '',
		);
		foreach ($color_references as $color_reference) {
			if ($color_reference !== '' && !isset($color_slugs[$color_reference])) {
				return new WP_Error('corsivo_theme_missing_color_reference', 'A style references a color that no longer exists.', array('status' => 400));
			}
		}
	}

	$assigned_contexts = array();
	foreach ($sections['context_styles'] as $assignment) {
		if (!is_array($assignment) || empty($assignment['context']) || empty($assignment['template'])) continue;
		if (!isset($template_slugs[$assignment['template']])) {
			return new WP_Error('corsivo_theme_missing_template_reference', 'A context references a style template that no longer exists.', array('status' => 400));
		}
		if (isset($assigned_contexts[$assignment['context']])) {
			return new WP_Error('corsivo_theme_duplicate_context', 'A context can be assigned only once.', array('status' => 400));
		}
		$assigned_contexts[$assignment['context']] = true;
	}

	return $sections;
}

/**
 * POST /config — saves configurator data to corsivo-theme.json under the "configurator" key
 */
function corsivo_theme_save_config($request) {
	$request_data = $request->get_json_params();

	if (empty($request_data) || !is_array($request_data)) {
		return new WP_Error('invalid_data', 'Invalid request data', array('status' => 400));
	}

	$configurator = corsivo_theme_sanitize_configurator_request($request_data);
	if (is_wp_error($configurator)) return $configurator;

	$settings = corsivo_theme_get_settings(true);
	$old_configurator = isset($settings['configurator']) && is_array($settings['configurator']) ? $settings['configurator'] : array();
	$before_hash = corsivo_theme_settings_revision($settings);
	$settings['configurator'] = $configurator;

	if (isset($request_data['page_assignments']) && is_array($request_data['page_assignments'])) {
		$assignments = array();
		foreach ($request_data['page_assignments'] as $slug => $languages) {
			$slug = sanitize_title($slug);
			if ($slug === '' || !is_array($languages)) continue;
			foreach ($languages as $language => $page_id) {
				$page_id = absint($page_id);
				$page = $page_id ? get_post($page_id) : null;
				if ($page_id && (!$page || $page->post_type !== 'page')) continue;
				$assignments[$slug][sanitize_key($language)] = $page_id;
			}
		}
		$settings['functional_pages'] = $assignments;
	}

	$expected_revision = isset($request_data['revision']) ? sanitize_text_field($request_data['revision']) : '';
	$attempted_hash = corsivo_theme_settings_revision($settings);
	$save_result = corsivo_theme_write_settings_file($settings, $expected_revision);
	if (is_wp_error($save_result)) {
		corsivo_theme_log_activity('config_save_failed', 'error', $save_result->get_error_message(), array(), array('before_hash' => $before_hash, 'after_hash' => $attempted_hash));
		return $save_result;
	}
	$stored_settings = corsivo_theme_get_settings(true);
	$after_hash = corsivo_theme_settings_revision($stored_settings);

	$cache_warning = '';
	try {
		corsivo_theme_clear_theme_json_cache();
		$cache_result = function_exists('corsivo_theme_clear_generated_cache') ? corsivo_theme_clear_generated_cache() : true;
		if (is_wp_error($cache_result)) $cache_warning = $cache_result->get_error_message();
		corsivo_theme_to_css();
	} catch (Throwable $error) {
		$cache_warning = $error->getMessage();
	}
	$old_template_slugs = array_column($old_configurator['style_templates'] ?? array(), 'slug');
	$new_template_slugs = array_column($configurator['style_templates'] ?? array(), 'slug');
	$removed_template_slugs = array_values(array_diff($old_template_slugs, $new_template_slugs));
	$removed_template_post_counts = array();
	global $wpdb;
	foreach ($removed_template_slugs as $removed_template_slug) {
		$removed_template_post_counts[$removed_template_slug] = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s",
				'_corsivo_theme_style_template',
				$removed_template_slug
			)
		);
		delete_metadata('post', 0, '_corsivo_theme_style_template', $removed_template_slug, true);
	}
	corsivo_theme_log_activity(
		'config_saved',
		$cache_warning === '' ? 'success' : 'warning',
		$cache_warning === '' ? 'Theme configurator saved.' : 'Theme configurator saved, but generated caches could not be cleared.',
		array(
			'sections' => corsivo_theme_changed_settings_sections($old_configurator, $configurator),
			'removed_template_assignments' => $removed_template_slugs,
			'removed_template_post_counts' => $removed_template_post_counts,
			'removed_template_post_total' => array_sum($removed_template_post_counts),
			'cache_warning' => $cache_warning,
		),
		array('before_hash' => $before_hash, 'after_hash' => $after_hash)
	);

	return rest_ensure_response(array(
		'success' => true,
		'revision' => $after_hash,
		'message' => $cache_warning === '' ? 'Saved and caches cleared.' : 'Saved. Cache clear failed: ' . $cache_warning,
		'warning' => $cache_warning !== '',
	));
}

/**
 * POST /rebuild — delete cached CSS files and regenerate
 */
function corsivo_theme_rebuild() {
	global $corsivo_theme_config;
	$corsivo_theme_config = null;

	try {
		corsivo_theme_clear_theme_json_cache();
		$cleared = function_exists('corsivo_theme_clear_generated_cache') ? corsivo_theme_clear_generated_cache() : true;
		if (!is_wp_error($cleared)) corsivo_theme_to_css();
	} catch (Throwable $error) {
		$cleared = new WP_Error('corsivo_theme_rebuild_failed', $error->getMessage(), array('status' => 500));
	}
	if (is_wp_error($cleared)) {
		corsivo_theme_log_activity('cache_rebuild_failed', 'error', $cleared->get_error_message());
		return $cleared;
	}
	corsivo_theme_log_activity('cache_rebuilt', 'success', 'Generated Theme caches cleared.');

	return rest_ensure_response(array(
		'success' => true,
		'message' => 'Caches cleared',
	));
}

function corsivo_theme_normalize_font_url($url) {
	$scheme = is_ssl() ? 'https' : strtolower((string) wp_parse_url(home_url('/'), PHP_URL_SCHEME));
	if (!in_array($scheme, array('http', 'https'), true)) return (string) $url;

	return set_url_scheme((string) $url, $scheme);
}

/**
 * Where uploaded fonts are written. Prefers WP core's managed, filterable font
 * directory (wp-content/fonts, WP 6.5+) so files survive a theme switch and are
 * visible to the Site Editor Font Library; falls back to the active theme.
 */
function corsivo_theme_font_upload_target() {
	if (function_exists('wp_get_font_dir')) {
		$font_dir = wp_get_font_dir();
		if (!empty($font_dir['path']) && !empty($font_dir['url'])) {
			return array(
				'path' => $font_dir['path'],
				'url' => corsivo_theme_normalize_font_url($font_dir['url']),
			);
		}
	}
	return array(
		'path' => get_stylesheet_directory() . '/fonts',
		'url'  => corsivo_theme_normalize_font_url(get_stylesheet_directory_uri() . '/fonts'),
	);
}

/**
 * Directories scanned for selectable font files: the core font dir plus the
 * child and parent theme /fonts folders. Deduplicated by path.
 */
function corsivo_theme_font_scan_roots() {
	$roots = array();

	if (function_exists('wp_get_font_dir')) {
		$font_dir = wp_get_font_dir();
		if (!empty($font_dir['path']) && !empty($font_dir['url'])) {
			$roots[] = array(
				'path' => $font_dir['path'],
				'url' => corsivo_theme_normalize_font_url($font_dir['url']),
			);
		}
	}
	$roots[] = array(
		'path' => get_stylesheet_directory() . '/fonts',
		'url' => corsivo_theme_normalize_font_url(get_stylesheet_directory_uri() . '/fonts'),
	);
	$roots[] = array(
		'path' => get_template_directory() . '/fonts',
		'url' => corsivo_theme_normalize_font_url(get_template_directory_uri() . '/fonts'),
	);

	$unique = array();
	$seen = array();
	foreach ($roots as $root) {
		$key = rtrim(str_replace('\\', '/', $root['path']), '/');
		if (isset($seen[$key])) continue;
		$seen[$key] = true;
		$unique[] = $root;
	}

	return $unique;
}

/**
 * Validate the magic bytes of an uploaded file to confirm it is really a font,
 * independent of its extension.
 */
function corsivo_theme_is_font_file($tmp_path) {
	$handle = @fopen($tmp_path, 'rb');
	if (!$handle) return false;
	$sig = fread($handle, 4);
	fclose($handle);
	if (strlen($sig) < 4) return false;

	$known = array('wOFF', 'wOF2', 'OTTO', 'true', 'ttcf');
	if (in_array($sig, $known, true)) return true;
	return $sig === "\x00\x01\x00\x00"; // TrueType outlines
}

function corsivo_theme_font_upload_error($code, $message, $status) {
	corsivo_theme_log_activity('font_upload_failed', 'error', $message);
	return new WP_Error($code, $message, array('status' => $status));
}

/**
 * POST /upload-font — upload a font file to the core/theme font directory
 */
function corsivo_theme_upload_font($request) {
	$files = $request->get_file_params();

	if (empty($files['font_file'])) {
		return corsivo_theme_font_upload_error('no_file', 'No font file was uploaded.', 400);
	}

	$file = $files['font_file'];
	if (!isset($file['error']) || (int) $file['error'] !== UPLOAD_ERR_OK) {
		return corsivo_theme_font_upload_error('upload_error', 'The font upload did not complete.', 400);
	}
	if (empty($file['size']) || (int) $file['size'] > 10 * MB_IN_BYTES) {
		return corsivo_theme_font_upload_error('invalid_size', 'Font files must be smaller than 10 MB.', 400);
	}

	$allowed_types = array('woff', 'woff2', 'ttf', 'otf');
	$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

	if (!in_array($ext, $allowed_types, true)) {
		return corsivo_theme_font_upload_error('invalid_type', 'Only .woff, .woff2, .ttf and .otf files are allowed.', 400);
	}

	if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name']) || !corsivo_theme_is_font_file($file['tmp_name'])) {
		return corsivo_theme_font_upload_error('invalid_font', 'The uploaded file is not a valid font.', 400);
	}

	$target = corsivo_theme_font_upload_target();
	$upload_dir = $target['path'];
	if ((!is_dir($upload_dir) && !wp_mkdir_p($upload_dir)) || !is_writable($upload_dir)) {
		return corsivo_theme_font_upload_error('upload_directory_unavailable', 'The font directory is not writable.', 500);
	}

	// Sanitize filename — basename() strips path components, sanitize_file_name() removes special chars
	$filename = basename(sanitize_file_name($file['name']));
	if (empty($filename)) {
		return corsivo_theme_font_upload_error('invalid_filename', 'The font filename is invalid.', 400);
	}
	$filename = wp_unique_filename($upload_dir, $filename);
	$target_path = trailingslashit($upload_dir) . $filename;

	// Move file
	if (!move_uploaded_file($file['tmp_name'], $target_path)) {
		return corsivo_theme_font_upload_error('upload_failed', 'The uploaded font could not be stored.', 500);
	}
	$permissions = fileperms($upload_dir);
	if ($permissions) @chmod($target_path, $permissions & 0000666);

	$url = trailingslashit($target['url']) . rawurlencode($filename);
	corsivo_theme_log_activity(
		'font_uploaded',
		'success',
		'Font uploaded.',
		array('filename' => $filename, 'size' => (int) $file['size']),
		array('object_type' => 'font', 'object_key' => $filename)
	);

	return rest_ensure_response(array(
		'success'  => true,
		'url'      => $url,
		'filename' => $filename,
	));
}

/**
 * GET /theme-fonts — list font files in the core font dir + theme /fonts (recursive)
 */
function corsivo_theme_list_theme_fonts() {
	$extensions = array('woff', 'woff2', 'ttf', 'otf');
	$result = array();

	foreach (corsivo_theme_font_scan_roots() as $root) {
		if (!is_dir($root['path'])) continue;
		corsivo_theme_scan_fonts_dir($root['path'], $root['path'], $root['url'], $extensions, $result);
	}

	// Deduplicate by URL (overlapping roots)
	$seen = array();
	$deduped = array();
	foreach ($result as $item) {
		if (isset($seen[$item['url']])) continue;
		$seen[$item['url']] = true;
		$deduped[] = $item;
	}

	// Sort: root files first, then by folder, then by filename
	usort($deduped, function ($a, $b) {
		$fa = $a['folder'];
		$fb = $b['folder'];
		if ($fa === '' && $fb !== '') return -1;
		if ($fa !== '' && $fb === '') return 1;
		if ($fa !== $fb) return strcmp($fa, $fb);
		return strcmp($a['filename'], $b['filename']);
	});

	return rest_ensure_response($deduped);
}

/**
 * Recursively scan a directory for font files, building URLs from $base_url.
 */
function corsivo_theme_scan_fonts_dir($base_dir, $current_dir, $base_url, $extensions, &$result, $depth = 0) {
	if ($depth > 8 || count($result) >= 1000 || is_link($current_dir)) return;
	$base_real = realpath($base_dir);
	$current_real = realpath($current_dir);
	if (!$base_real || !$current_real) return;
	$base_normalized = trailingslashit(wp_normalize_path($base_real));
	if (strpos(wp_normalize_path($current_real) . '/', $base_normalized) !== 0) return;

	$entries = scandir($current_real);
	if ($entries === false) return;

	foreach ($entries as $entry) {
		if ($entry === '.' || $entry === '..') continue;

		$full_path = $current_real . DIRECTORY_SEPARATOR . $entry;
		if (is_link($full_path)) continue;

		if (is_dir($full_path)) {
			corsivo_theme_scan_fonts_dir($base_real, $full_path, $base_url, $extensions, $result, $depth + 1);
			continue;
		}

		$ext = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
		if (!in_array($ext, $extensions, true)) continue;

		// Compute relative path from base fonts dir
		$relative = substr($full_path, strlen($base_real) + 1);
		$folder = dirname($relative);
		if ($folder === '.') $folder = '';

		$url_path = implode('/', array_map('rawurlencode', explode('/', str_replace('\\', '/', $relative))));
		$result[] = array(
			'filename' => $entry,
			'relative' => str_replace('\\', '/', $relative),
			'folder'   => str_replace('\\', '/', $folder),
			'url'      => corsivo_theme_normalize_font_url(rtrim($base_url, '/') . '/' . $url_path),
			'size'     => filesize($full_path),
			'mtime'    => filemtime($full_path),
		);
	}
}
