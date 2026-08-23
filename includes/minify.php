<?php
if (!defined('ABSPATH')) exit;

$corsivo_theme_library_path = __DIR__ . '/libs/';
require_once $corsivo_theme_library_path . 'minify/src/Minify.php';
require_once $corsivo_theme_library_path . 'minify/src/CSS.php';
require_once $corsivo_theme_library_path . 'minify/src/JS.php';
require_once $corsivo_theme_library_path . 'minify/src/Exception.php';
require_once $corsivo_theme_library_path . 'minify/src/Exceptions/BasicException.php';
require_once $corsivo_theme_library_path . 'minify/src/Exceptions/FileImportException.php';
require_once $corsivo_theme_library_path . 'minify/src/Exceptions/IOException.php';
require_once $corsivo_theme_library_path . 'path-converter/src/ConverterInterface.php';
require_once $corsivo_theme_library_path . 'path-converter/src/Converter.php';

use MatthiasMullie\Minify;

function corsivo_theme_theme_asset_roots() {
	$roots = array(
		array('path' => get_stylesheet_directory(), 'url' => get_stylesheet_directory_uri()),
		array('path' => get_template_directory(), 'url' => get_template_directory_uri()),
	);
	$unique = array();
	foreach ($roots as $root) {
		$normalized = wp_normalize_path($root['path']);
		if (!isset($unique[$normalized])) $unique[$normalized] = $root;
	}
	return array_values($unique);
}

function corsivo_theme_resolve_theme_asset($source, $extension) {
	if (!is_string($source) || $source === '') return null;
	$source = strtok($source, '?#');
	$source_url_path = rawurldecode((string) wp_parse_url($source, PHP_URL_PATH));

	foreach (corsivo_theme_theme_asset_roots() as $root) {
		$root_url_path = rtrim(rawurldecode((string) wp_parse_url($root['url'], PHP_URL_PATH)), '/');
		$relative = '';
		if ($root_url_path !== '' && strpos($source_url_path, $root_url_path . '/') === 0) {
			$relative = substr($source_url_path, strlen($root_url_path) + 1);
		} elseif (strpos($source, '://') === false && strpos($source_url_path, '/wp-content/') !== 0) {
			$relative = ltrim($source_url_path, '/');
		}
		if ($relative === '') continue;

		$root_real = realpath($root['path']);
		$candidate = realpath(trailingslashit($root['path']) . str_replace('/', DIRECTORY_SEPARATOR, $relative));
		if (!$root_real || !$candidate) continue;
		$root_normalized = trailingslashit(wp_normalize_path($root_real));
		$candidate_normalized = wp_normalize_path($candidate);
		if (strpos($candidate_normalized, $root_normalized) !== 0) continue;
		if (strtolower(pathinfo($candidate, PATHINFO_EXTENSION)) !== strtolower($extension)) continue;
		if (!is_file($candidate) || !is_readable($candidate)) continue;
		return $candidate;
	}

	return null;
}

function corsivo_theme_asset_fingerprint($type, $assets) {
	$parts = array(CORSIVO_THEME_EDITOR_VERSION, $type);
	foreach ($assets as $asset) {
		$path = is_array($asset) ? $asset['path'] : $asset;
		$parts[] = wp_normalize_path($path) . ':' . hash_file('sha256', $path);
	}
	return substr(hash('sha256', implode('|', $parts)), 0, 16);
}

function corsivo_theme_write_minified_file($minifier, $destination_path) {
	try {
		$minified = $minifier->minify();
	} catch (Throwable $error) {
		return new WP_Error('corsivo_theme_minify_failed', $error->getMessage());
	}

	$temporary_path = tempnam(dirname($destination_path), '.corsivo-theme-min-');
	if (!$temporary_path) return new WP_Error('corsivo_theme_cache_write_failed', 'Unable to create a temporary generated asset.');
	$written = @file_put_contents($temporary_path, $minified, LOCK_EX);
	if ($written !== strlen($minified) || !corsivo_theme_replace_file($temporary_path, $destination_path)) {
		@unlink($temporary_path);
		return new WP_Error('corsivo_theme_cache_write_failed', 'Unable to write the generated asset cache.');
	}
	return true;
}

function corsivo_theme_prune_asset_cache($type, $current_filename) {
	$location = corsivo_theme_generated_cache_location();
	if (is_wp_error($location)) return;
	$entries = @scandir($location['path']);
	if (!is_array($entries)) return;
	foreach ($entries as $entry) {
		if ($entry === $current_filename) continue;
		if (!preg_match('/^corsivo-theme-[a-f0-9]{16}\.min\.' . preg_quote($type, '/') . '$/', $entry)) continue;
		$path = trailingslashit($location['path']) . $entry;
		if (is_file($path)) @unlink($path);
	}
}

function corsivo_theme_build_css() {
	global $wp_styles;

	$sources = apply_filters('corsivo_theme_css_to_minify', array('/style.css'));
	$paths = array();
	foreach ((array) $sources as $source) {
		$path = corsivo_theme_resolve_theme_asset($source, 'css');
		if ($path) $paths[wp_normalize_path($path)] = $path;
	}
	if (empty($paths)) return new WP_Error('corsivo_theme_css_sources_missing', 'No readable theme stylesheets are available for minification.');

	$handles = array();
	$dependencies = array();
	foreach ((array) $wp_styles->queue as $handle) {
		if (empty($wp_styles->registered[$handle])) continue;
		$style = $wp_styles->registered[$handle];
		$path = corsivo_theme_resolve_theme_asset($style->src, 'css');
		if (!$path || !isset($paths[wp_normalize_path($path)])) continue;
		$handles[] = $handle;
		foreach ($style->deps as $dependency) {
			if (!in_array($dependency, $handles, true) && !in_array($dependency, $dependencies, true)) $dependencies[] = $dependency;
		}
	}
	$dependencies = array_values(array_diff($dependencies, $handles));

	$assets = array_values($paths);
	$fingerprint = corsivo_theme_asset_fingerprint('css', $assets);
	$filename = 'corsivo-theme-' . $fingerprint . '.min.css';
	$location = corsivo_theme_generated_cache_location();
	if (is_wp_error($location)) return $location;
	$destination = trailingslashit($location['path']) . $filename;

	if (!file_exists($destination)) {
		$minifier = new Minify\CSS();
		foreach ($assets as $path) $minifier->add($path);
		$result = corsivo_theme_write_minified_file($minifier, $destination);
		if (is_wp_error($result)) return $result;
	}
	corsivo_theme_prune_asset_cache('css', $filename);

	return array(
		'url' => trailingslashit($location['url']) . $filename,
		'version' => $fingerprint,
		'handles' => $handles,
		'dependencies' => $dependencies,
	);
}

function corsivo_theme_sort_script_assets($assets) {
	$sorted = array();
	$visiting = array();
	$visited = array();
	$visit = function ($handle) use (&$visit, &$sorted, &$visiting, &$visited, $assets) {
		if (isset($visited[$handle])) return true;
		if (isset($visiting[$handle])) return false;
		$visiting[$handle] = true;
		foreach ($assets[$handle]['dependencies'] as $dependency) {
			if (isset($assets[$dependency]) && !$visit($dependency)) return false;
		}
		unset($visiting[$handle]);
		$visited[$handle] = true;
		$sorted[] = $handle;
		return true;
	};
	foreach (array_keys($assets) as $handle) {
		if (!$visit($handle)) return new WP_Error('corsivo_theme_script_dependency_cycle', 'Theme scripts contain a circular dependency.');
	}
	return $sorted;
}

function corsivo_theme_build_js() {
	global $wp_scripts;

	$assets = array();
	foreach ((array) $wp_scripts->queue as $handle) {
		if (empty($wp_scripts->registered[$handle])) continue;
		$script = $wp_scripts->registered[$handle];
		$path = corsivo_theme_resolve_theme_asset($script->src, 'js');
		if (!$path) continue;
		if (empty($script->extra['group'])) continue;
		if (!empty($script->extra['conditional']) || !empty($script->extra['strategy']) || !empty($script->extra['textdomain'])) continue;
		if (($script->extra['type'] ?? '') === 'module') continue;
		$assets[$handle] = array('path' => $path, 'dependencies' => $script->deps);
	}
	if (empty($assets)) return new WP_Error('corsivo_theme_js_sources_missing', 'No compatible enqueued theme scripts are available for minification.');

	$handles = corsivo_theme_sort_script_assets($assets);
	if (is_wp_error($handles)) return $handles;
	$ordered_assets = array();
	$dependencies = array();
	$localized_data = array();
	$inline_before = array();
	$inline_after = array();
	foreach ($handles as $handle) {
		$ordered_assets[] = $assets[$handle];
		foreach ($assets[$handle]['dependencies'] as $dependency) {
			if (!isset($assets[$dependency]) && !in_array($dependency, $dependencies, true)) $dependencies[] = $dependency;
		}
		$localized = $wp_scripts->get_data($handle, 'data');
		if (is_string($localized) && $localized !== '') $localized_data[] = $localized;
		$before = $wp_scripts->get_data($handle, 'before');
		if (is_array($before)) $inline_before = array_merge($inline_before, array_filter($before, 'is_string'));
		$after = $wp_scripts->get_data($handle, 'after');
		if (is_array($after)) $inline_after = array_merge($inline_after, array_filter($after, 'is_string'));
	}

	$fingerprint = corsivo_theme_asset_fingerprint('js', $ordered_assets);
	$filename = 'corsivo-theme-' . $fingerprint . '.min.js';
	$location = corsivo_theme_generated_cache_location();
	if (is_wp_error($location)) return $location;
	$destination = trailingslashit($location['path']) . $filename;

	if (!file_exists($destination)) {
		$minifier = new Minify\JS();
		foreach ($ordered_assets as $asset) $minifier->add(file_get_contents($asset['path']) . ';');
		$result = corsivo_theme_write_minified_file($minifier, $destination);
		if (is_wp_error($result)) return $result;
	}
	corsivo_theme_prune_asset_cache('js', $filename);

	return array(
		'url' => trailingslashit($location['url']) . $filename,
		'version' => $fingerprint,
		'handles' => $handles,
		'dependencies' => $dependencies,
		'localized_data' => $localized_data,
		'inline_before' => $inline_before,
		'inline_after' => $inline_after,
	);
}
