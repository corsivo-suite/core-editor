<?php

if (PHP_SAPI !== 'cli') exit(1);
set_error_handler(function ($severity, $message, $file, $line) {
	if (error_reporting() & $severity) throw new ErrorException($message, 0, $severity, $file, $line);
	return false;
});

$wordpress_directory = dirname(__DIR__, 4);
define('ABSPATH', $wordpress_directory . '/');
define('CORSIVO_THEME_EDITOR_VERSION', 'minify-regressions');
define('HOUR_IN_SECONDS', 3600);

function add_action(...$arguments) {}
function do_action(...$arguments) {}
function do_action_ref_array(...$arguments) {}
function _deprecated_argument(...$arguments) {}
function __($text) { return $text; }
function apply_filters($hook, $value, ...$arguments) {
	return $hook === 'corsivo_theme_css_to_minify' ? $GLOBALS['css_sources'] : $value;
}
function is_wp_error($value) { return $value instanceof WP_Error; }
function wp_normalize_path($path) { return str_replace('\\', '/', $path); }
function wp_parse_url($url, $component = -1) { return parse_url($url, $component); }
function trailingslashit($path) { return rtrim($path, '/\\') . '/'; }
function sanitize_key($value) { return $value; }
function get_stylesheet_directory() { return $GLOBALS['theme_directory']; }
function get_template_directory() { return get_stylesheet_directory(); }
function get_stylesheet_directory_uri() { return 'https://example.test/wp-content/themes/fixture'; }
function get_template_directory_uri() { return get_stylesheet_directory_uri(); }
function get_stylesheet() { return 'fixture'; }
function get_current_blog_id() { return 1; }
function corsivo_theme_get_settings() { return array(); }
function get_transient($key) { return false; }
function set_transient(...$arguments) {}
function corsivo_theme_log_activity(...$arguments) {}
function corsivo_theme_replace_file($source, $target) { return rename($source, $target); }
function wp_upload_dir(...$arguments) {
	return array('basedir' => $GLOBALS['upload_directory'], 'baseurl' => 'https://example.test/uploads', 'error' => false);
}
function wp_mkdir_p($path) { return is_dir($path) || mkdir($path, 0777, true); }
function wp_enqueue_style($handle, $source, $dependencies, $version) {
	$GLOBALS['wp_styles']->add($handle, $source, $dependencies, $version, 'all');
	$GLOBALS['wp_styles']->enqueue($handle);
}
function wp_enqueue_script($handle, $source, $dependencies, $version, $in_footer) {
	$GLOBALS['wp_scripts']->add($handle, $source, $dependencies, $version);
	$GLOBALS['wp_scripts']->add_data($handle, 'group', $in_footer ? 1 : 0);
	$GLOBALS['wp_scripts']->enqueue($handle);
}

foreach (array('class-wp-error.php', 'class-wp-dependency.php', 'class-wp-dependencies.php', 'class-wp-scripts.php', 'class-wp-styles.php') as $core_file) {
	require $wordpress_directory . '/wp-includes/' . $core_file;
}
require dirname(__DIR__) . '/includes/settings.php';
require dirname(__DIR__) . '/includes/minify.php';

$assertions = 0;
function check($condition, $message) {
	global $assertions;
	$assertions++;
	if (!$condition) throw new RuntimeException($message);
}
function add_script($handle, $filename, $dependencies = array(), $extra = array()) {
	global $wp_scripts;
	$source = strpos($filename, '://') === false ? get_stylesheet_directory_uri() . '/' . $filename : $filename;
	$wp_scripts->add($handle, $source, $dependencies);
	$wp_scripts->add_data($handle, 'group', 1);
	foreach ($extra as $key => $value) $wp_scripts->add_data($handle, $key, $value);
	$wp_scripts->enqueue($handle);
}
function add_style($handle, $filename, $dependencies = array(), $media = 'all', $extra = array()) {
	global $wp_styles;
	$source = strpos($filename, '://') === false ? get_stylesheet_directory_uri() . '/' . $filename : $filename;
	$wp_styles->add($handle, $source, $dependencies, false, $media);
	foreach ($extra as $key => $value) $wp_styles->add_data($handle, $key, $value);
	$wp_styles->enqueue($handle);
}
function queued_sources($registry) {
	$registry->to_do = array();
	$registry->all_deps($registry->queue);
	$sources = array();
	foreach ($registry->to_do as $handle) {
		if ($registry->registered[$handle]->src) $sources[$handle] = $registry->registered[$handle]->src;
	}
	return $sources;
}
function bundle_path($bundle) {
	$location = corsivo_theme_generated_cache_location();
	return $location['path'] . '/' . basename(parse_url($bundle['url'], PHP_URL_PATH));
}
function remove_fixture_directory($directory) {
	foreach (scandir($directory) as $entry) {
		if ($entry === '.' || $entry === '..') continue;
		$path = $directory . '/' . $entry;
		if (is_dir($path)) remove_fixture_directory($path);
		else unlink($path);
	}
	rmdir($directory);
}

$fixture_directory = sys_get_temp_dir() . '/corsivo-minify-' . bin2hex(random_bytes(6));
$theme_directory = $fixture_directory . '/theme';
$upload_directory = $fixture_directory . '/uploads';
wp_mkdir_p($theme_directory);
wp_mkdir_p($upload_directory);
$css_sources = array('/style.css', '/second.css', '/third.css');
$source_contents = array(
	'first.js' => 'window.assetOrder = ["first"];',
	'second.js' => 'window.assetOrder.push("second");',
	'third.js' => 'window.assetOrder.push("third");',
	'gallery.js' => 'window.assetOrder.push("gallery");',
	'style.css' => '.example { color: red; background-image: url(images/photo.png); }',
	'second.css' => '.example { color: blue; }',
	'third.css' => '.example { color: green; }',
);
foreach ($source_contents as $filename => $contents) file_put_contents($theme_directory . '/' . $filename, $contents);

try {
	$wp_scripts = new WP_Scripts();
	add_script('second', 'second.js', array('first'));
	add_script('first', 'first.js');
	$bundle = corsivo_theme_build_js();
	check(!is_wp_error($bundle) && $bundle['handles'] === array('first', 'second'), 'Script sources follow dependency order rather than enqueue order.');
	check(strpos(file_get_contents(bundle_path($bundle)), 'first') < strpos(file_get_contents(bundle_path($bundle)), 'second'), 'The generated JavaScript keeps dependency execution order.');
	$homepage_path = bundle_path($bundle);
	corsivo_theme_enqueue_min_js();
	check(array_keys(queued_sources($wp_scripts)) === array('corsivo-theme-script-min'), 'Each original script is replaced by one bundle.');
	add_script('late-consumer', 'https://example.test/plugins/late.js', array('first'));
	check(array_keys(queued_sources($wp_scripts)) === array('corsivo-theme-script-min', 'late-consumer'), 'A late consumer resolves the original handle alias without printing its source again.');

	$wp_scripts = new WP_Scripts();
	add_script('first', 'first.js');
	add_script('second', 'second.js', array('first'));
	add_script('external', 'https://example.test/plugins/consumer.js', array('second'));
	add_script('third', 'third.js');
	$bundle = corsivo_theme_build_js();
	check(!is_wp_error($bundle) && $bundle['handles'] === array('third'), 'Dependencies of excluded consumers are excluded transitively.');
	corsivo_theme_enqueue_min_js();
	check(array_keys(queued_sources($wp_scripts)) === array('first', 'second', 'external', 'corsivo-theme-script-min'), 'External consumers retain their original dependencies and position.');

	foreach (array('before' => array('window.before = true;'), 'after' => array('window.after = true;'), 'data' => 'window.localized = true;', 'strategy' => 'defer', 'type' => 'module', 'conditional' => 'lt IE 9') as $key => $value) {
		$wp_scripts = new WP_Scripts();
		add_script('special', 'first.js', array(), array($key => $value));
		add_script('plain', 'second.js');
		$bundle = corsivo_theme_build_js();
		check(!is_wp_error($bundle) && $bundle['handles'] === array('plain'), 'JavaScript metadata remains on the original handle: ' . $key . '.');
		corsivo_theme_enqueue_min_js();
		check($wp_scripts->registered['special']->extra[$key] === $value && array_keys(queued_sources($wp_scripts)) === array('special', 'corsivo-theme-script-min'), 'Original metadata and script position survive enqueue: ' . $key . '.');
	}

	$wp_scripts = new WP_Scripts();
	add_script('translated', 'first.js');
	$wp_scripts->registered['translated']->set_translations('fixture-domain', '/fixture/translations');
	add_script('plain', 'second.js');
	$bundle = corsivo_theme_build_js();
	check(!is_wp_error($bundle) && $bundle['handles'] === array('plain'), 'The dependency textdomain property excludes translated scripts.');
	corsivo_theme_enqueue_min_js();
	check($wp_scripts->registered['translated']->textdomain === 'fixture-domain' && $wp_scripts->registered['translated']->src !== false, 'Translated scripts keep their source and catalog registration.');

	$wp_scripts = new WP_Scripts();
	add_script('first', 'first.js');
	add_script('middle', 'https://example.test/plugins/middle.js');
	add_script('second', 'second.js');
	add_script('third', 'third.js');
	corsivo_theme_enqueue_min_js();
	check(array_keys(queued_sources($wp_scripts)) === array('first', 'middle', 'corsivo-theme-script-min'), 'Only a contiguous group is bundled, preserving interleaved script order.');

	$wp_scripts = new WP_Scripts();
	add_script('first', 'first.js');
	$wp_scripts->done[] = 'first';
	add_script('second', 'second.js');
	$bundle = corsivo_theme_build_js();
	check(!is_wp_error($bundle) && $bundle['handles'] === array('second'), 'Already printed scripts are never included again.');

	$wp_styles = new WP_Styles();
	add_style('second-style', 'second.css', array('first-style'));
	add_style('first-style', 'style.css');
	$bundle = corsivo_theme_build_css();
	check(!is_wp_error($bundle) && $bundle['handles'] === array('first-style', 'second-style'), 'Stylesheets follow resolved dependency order.');
	$css = file_get_contents(bundle_path($bundle));
	check(strpos($css, 'red') < strpos($css, 'blue'), 'The generated stylesheet preserves the cascade.');
	check(strpos($css, '/theme/images/photo.png') !== false, 'Relative stylesheet URLs are converted for the generated cache directory.');
	corsivo_theme_enqueue_min_css();
	check(array_keys(queued_sources($wp_styles)) === array('corsivo-theme-style-min'), 'Each original stylesheet is replaced by one bundle.');
	add_style('late-style', 'https://example.test/plugins/late.css', array('first-style'));
	check(array_keys(queued_sources($wp_styles)) === array('corsivo-theme-style-min', 'late-style'), 'A late stylesheet consumer does not print a bundled source again.');

	foreach (array(array('media' => 'print', 'extra' => array()), array('media' => 'all', 'extra' => array('after' => array('.inline { color: purple; }'))), array('media' => 'all', 'extra' => array('rtl' => 'replace')), array('media' => 'all', 'extra' => array('alt' => true, 'title' => 'Alternate'))) as $case) {
		$wp_styles = new WP_Styles();
		add_style('special-style', 'style.css', array(), $case['media'], $case['extra']);
		add_style('plain-style', 'second.css');
		$bundle = corsivo_theme_build_css();
		check(!is_wp_error($bundle) && $bundle['handles'] === array('plain-style'), 'Styles with media, inline CSS, RTL or alternate metadata are excluded.');
		corsivo_theme_enqueue_min_css();
		check($wp_styles->registered['special-style']->extra === $case['extra'] && $wp_styles->registered['special-style']->args === $case['media'], 'Style metadata remains attached to its original handle.');
		check(array_keys(queued_sources($wp_styles)) === array('special-style', 'corsivo-theme-style-min'), 'Excluded styles retain their cascade position.');
	}

	$wp_styles = new WP_Styles();
	add_style('first-style', 'style.css');
	add_style('external-style', 'https://example.test/plugins/consumer.css', array('first-style'));
	add_style('second-style', 'second.css');
	$bundle = corsivo_theme_build_css();
	check(!is_wp_error($bundle) && $bundle['handles'] === array('second-style'), 'A stylesheet used by an excluded consumer stays unbundled.');

	$wp_styles = new WP_Styles();
	add_style('first-style', 'style.css');
	add_style('middle-style', 'https://example.test/plugins/middle.css');
	add_style('second-style', 'second.css');
	add_style('third-style', 'third.css');
	corsivo_theme_enqueue_min_css();
	check(array_keys(queued_sources($wp_styles)) === array('first-style', 'middle-style', 'corsivo-theme-style-min'), 'Interleaved stylesheet order survives bundling.');

	$wp_scripts = new WP_Scripts();
	add_script('first', 'first.js');
	add_script('gallery', 'gallery.js');
	$gallery_bundle = corsivo_theme_build_js();
	check(is_file($homepage_path) && is_file(bundle_path($gallery_bundle)) && $homepage_path !== bundle_path($gallery_bundle), 'Conditional page bundles coexist in cache.');
	$gallery_path = bundle_path($gallery_bundle);
	touch($homepage_path, time() - 90 * 86400);
	clearstatcache();
	check(!is_wp_error(corsivo_theme_build_js()) && is_file($homepage_path), 'Old bundle URLs remain available for previously cached HTML.');
	file_put_contents($theme_directory . '/gallery.js', 'window.assetOrder.push("updated-gallery");');
	$updated_bundle = corsivo_theme_build_js();
	check(!is_wp_error($updated_bundle) && bundle_path($updated_bundle) !== $gallery_path && is_file($gallery_path), 'Changing a source creates a new fingerprint without deleting earlier bundle URLs.');
	$source_contents['gallery.js'] = 'window.assetOrder.push("updated-gallery");';
	foreach ($source_contents as $filename => $contents) check(file_get_contents($theme_directory . '/' . $filename) === $contents, 'Theme source is never overwritten: ' . $filename . '.');
	$location = corsivo_theme_generated_cache_location();
	check(glob($location['path'] . '/.corsivo-theme-min-*') === array(), 'Atomic writes leave no temporary files.');
	file_put_contents($location['path'] . '/unrelated.txt', 'keep');
	check(corsivo_theme_clear_generated_cache() === true && !is_file($homepage_path) && is_file($location['path'] . '/unrelated.txt'), 'Explicit cache clearing removes only generated bundles.');

	$wp_scripts = new WP_Scripts();
	add_script('first', 'first.js', array('second'));
	add_script('second', 'second.js', array('first'));
	check(is_wp_error(corsivo_theme_build_js()), 'Circular dependencies fail safely without modifying the queue.');
	$wp_scripts = new WP_Scripts();
	add_script('first', 'first.js', array('missing'));
	check(is_wp_error(corsivo_theme_build_js()), 'Missing dependencies fail safely without creating an invalid bundle.');
	$wp_scripts = null;
	check(is_wp_error(corsivo_theme_build_js()), 'No initialized script registry is handled safely.');

	echo "Minification regressions: {$assertions} assertions passed.\n";
} finally {
	remove_fixture_directory($fixture_directory);
}
