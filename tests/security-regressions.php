<?php

if (PHP_SAPI !== 'cli') { exit(1); }

define('ABSPATH', __DIR__ . '/');
function add_action(...$arguments) {}
function add_filter(...$arguments) {}
function sanitize_key($value) { return $value; }
function corsivo_theme_get_settings() { return ['functional_pages' => ['legal' => ['it' => 12]]]; }
function corsivo_theme_current_language() { return 'it'; }
function corsivo_theme_wpml_enabled() { return false; }
function get_post($id) { return $GLOBALS['fixture_page']; }
function is_post_publicly_viewable($post) { return $post->post_status === 'publish'; }
function post_password_required($post) { return !empty($post->post_password); }
function corsivo_theme_replace_file($source, $target) { return rename($source, $target); }
require dirname(__DIR__) . '/includes/functions.php';
require dirname(__DIR__) . '/includes/rest-api-configurator.php';
require dirname(__DIR__) . '/includes/minify.php';

function check($condition, $message) {
	if (!$condition) { throw new RuntimeException($message); }
	echo "PASS $message\n";
}

foreach (['</style><script>alert(1)</script>', '<</' . '/style><img src=x onerror=alert(1)>', 'content: "<";'] as $css) {
	$clean = corsivo_theme_sanitize_css_block($css);
	check(!str_contains($clean, '</') && $clean === corsivo_theme_sanitize_css_block($clean), 'style escaping is idempotent and cannot close the HTML element');
}
check(corsivo_theme_sanitize_css_block('@media (width < 600px) { .item { color: red; } }') === '@media (width < 600px) { .item { color: red; } }', 'CSS media range comparisons survive output escaping');
check(corsivo_theme_sanitize_css_declarations('content: "<</' . '/style>";') === '', 'declaration sanitization rejects HTML delimiters');
check(corsivo_theme_sanitize_css_declarations('color: red;') === 'color: red;', 'ordinary declarations survive');
foreach (['draft', 'private', 'trash'] as $status) {
	$GLOBALS['fixture_page'] = (object) ['post_status' => $status];
	check(corsivo_theme_get_function_page('legal') === null, 'functional page blocks status ' . $status);
}
$GLOBALS['fixture_page'] = (object) ['post_status' => 'publish', 'post_password' => 'secret'];
check(corsivo_theme_get_function_page('legal') === null, 'functional page respects password protection');
$GLOBALS['fixture_page']->post_password = '';
check(corsivo_theme_get_function_page('legal') === $GLOBALS['fixture_page'], 'public functional page remains available');

$directory = sys_get_temp_dir() . '/corsivo-css-' . bin2hex(random_bytes(6));
mkdir($directory);
mkdir($directory . '/source');
mkdir($directory . '/cache');
try {
	file_put_contents($directory . '/source/style.css', '.image { background-image: url(images/photo.png); }');
	$minifier = new MatthiasMullie\Minify\CSS($directory . '/source/style.css');
	$destination = $directory . '/cache/result.css';
	check(corsivo_theme_write_minified_file($minifier, $destination) === true, 'CSS bundle is written atomically');
	check(str_contains(file_get_contents($destination), '../source/images/photo.png'), 'relative CSS URLs resolve from the destination directory');
} finally {
	foreach ([$directory . '/source/style.css', $directory . '/cache/result.css'] as $file) {
		if (is_file($file)) { unlink($file); }
	}
	rmdir($directory . '/source');
	rmdir($directory . '/cache');
	rmdir($directory);
}
