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
		$minified = $minifier->execute($destination_path);
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

function corsivo_theme_order_enqueued_assets($registry) {
	if (!is_object($registry)) return array();
	$ordered = array();
	$visiting = array();
	$visited = array_fill_keys($registry->done, true);
	$visit = function ($handle) use (&$visit, &$ordered, &$visiting, &$visited, $registry) {
		$handle = explode('?', $handle)[0];
		if (isset($visited[$handle])) return true;
		if (isset($visiting[$handle]) || !isset($registry->registered[$handle])) return false;
		$visiting[$handle] = true;
		foreach ($registry->registered[$handle]->deps as $dependency) {
			if (!$visit($dependency)) return false;
		}
		unset($visiting[$handle]);
		$visited[$handle] = true;
		$ordered[] = $handle;
		return true;
	};
	foreach ($registry->queue as $handle) {
		if (!$visit($handle)) return new WP_Error('corsivo_theme_asset_dependencies_invalid', 'Enqueued assets contain missing or circular dependencies.');
	}
	return $ordered;
}

function corsivo_theme_select_bundle_assets($registry, $assets, $ordered_handles) {
	if (empty($assets)) return array();
	do {
		$previous_handles = array_keys($assets);
		do {
			$previous_dependency_handles = array_keys($assets);
			foreach ($registry->registered as $handle => $asset) {
				if (isset($assets[$handle])) continue;
				foreach ($asset->deps as $dependency) unset($assets[$dependency]);
			}
		} while ($previous_dependency_handles !== array_keys($assets));
		$longest = array();
		$run = array();
		foreach ($ordered_handles as $handle) {
			if (!isset($assets[$handle])) {
				$run = array();
				continue;
			}
			$run[] = $handle;
			if (count($run) > count($longest)) $longest = $run;
		}
		$assets = array_intersect_key($assets, array_fill_keys($longest, true));
	} while ($previous_handles !== array_keys($assets));
	return $assets;
}

function corsivo_theme_replace_enqueued_assets($registry, $handles, $bundle_handle) {
	$replace = function ($queue) use ($handles, $bundle_handle) {
		$replaced = array();
		$inserted = false;
		foreach ($queue as $handle) {
			if ($handle === $bundle_handle) continue;
			if (!in_array(explode('?', $handle)[0], $handles, true)) {
				$replaced[] = $handle;
				continue;
			}
			if (!$inserted) $replaced[] = $bundle_handle;
			$inserted = true;
		}
		return $replaced;
	};
	$registry->queue = $replace($registry->queue);
	if (!empty($registry->to_do)) $registry->to_do = $replace($registry->to_do);
	foreach ($handles as $handle) {
		$registry->registered[$handle]->src = false;
		$registry->registered[$handle]->deps = array($bundle_handle);
	}
}

function corsivo_theme_build_css() {
	global $wp_styles;
	$ordered_handles = corsivo_theme_order_enqueued_assets($wp_styles);
	if (is_wp_error($ordered_handles)) return $ordered_handles;

	$sources = apply_filters('corsivo_theme_css_to_minify', array('/style.css'));
	$paths = array();
	foreach ((array) $sources as $source) {
		$path = corsivo_theme_resolve_theme_asset($source, 'css');
		if ($path) $paths[wp_normalize_path($path)] = $path;
	}
	if (empty($paths)) return new WP_Error('corsivo_theme_css_sources_missing', 'No readable theme stylesheets are available for minification.');

	$assets = array();
	foreach ($ordered_handles as $handle) {
		$style = $wp_styles->registered[$handle];
		$path = corsivo_theme_resolve_theme_asset($style->src, 'css');
		if (!$path || !isset($paths[wp_normalize_path($path)])) continue;
		if (!empty($style->extra) || !empty($wp_styles->args[$handle])) continue;
		if (!in_array($style->args, array(null, '', 'all'), true)) continue;
		$assets[$handle] = array('path' => $path, 'dependencies' => $style->deps);
	}
	$assets = corsivo_theme_select_bundle_assets($wp_styles, $assets, $ordered_handles);
	if (empty($assets)) return new WP_Error('corsivo_theme_css_sources_missing', 'No compatible enqueued theme stylesheets are available for minification.');
	$handles = array_keys($assets);
	$dependencies = array();
	foreach ($assets as $asset) $dependencies = array_merge($dependencies, $asset['dependencies']);
	$dependencies = array_values(array_diff(array_unique($dependencies), $handles));

	$fingerprint = corsivo_theme_asset_fingerprint('css', $assets);
	$filename = 'corsivo-theme-' . $fingerprint . '.min.css';
	$location = corsivo_theme_generated_cache_location();
	if (is_wp_error($location)) return $location;
	$destination = trailingslashit($location['path']) . $filename;

	if (!file_exists($destination)) {
		$minifier = new Minify\CSS();
		foreach ($assets as $asset) $minifier->add($asset['path']);
		$result = corsivo_theme_write_minified_file($minifier, $destination);
		if (is_wp_error($result)) return $result;
	}

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
	$ordered_handles = corsivo_theme_order_enqueued_assets($wp_scripts);
	if (is_wp_error($ordered_handles)) return $ordered_handles;

	$assets = array();
	foreach ($ordered_handles as $handle) {
		$script = $wp_scripts->registered[$handle];
		$path = corsivo_theme_resolve_theme_asset($script->src, 'js');
		if (!$path) continue;
		if (empty($script->extra['group'])) continue;
		if (!empty($script->textdomain) || !empty($wp_scripts->args[$handle])) continue;
		if (!empty(array_diff_key($script->extra, array('group' => true)))) continue;
		$assets[$handle] = array('path' => $path, 'dependencies' => $script->deps);
	}
	$assets = corsivo_theme_select_bundle_assets($wp_scripts, $assets, $ordered_handles);
	if (empty($assets)) return new WP_Error('corsivo_theme_js_sources_missing', 'No compatible enqueued theme scripts are available for minification.');

	$handles = corsivo_theme_sort_script_assets($assets);
	if (is_wp_error($handles)) return $handles;
	$ordered_assets = array();
	$dependencies = array();
	foreach ($handles as $handle) {
		$ordered_assets[] = $assets[$handle];
		foreach ($assets[$handle]['dependencies'] as $dependency) {
			if (!isset($assets[$dependency]) && !in_array($dependency, $dependencies, true)) $dependencies[] = $dependency;
		}
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

	return array(
		'url' => trailingslashit($location['url']) . $filename,
		'version' => $fingerprint,
		'handles' => $handles,
		'dependencies' => $dependencies,
	);
}
