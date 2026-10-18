<?php

if (PHP_SAPI !== 'cli') { exit(1); }

define('ABSPATH', __DIR__ . '/');
function add_filter(...$arguments) {}
function add_action(...$arguments) {}
function add_shortcode(...$arguments) {}
function __($text) { return $text; }
function corsivo_core_toolkit_log($message) {}
function current_user_can($capability) { return true; }
function apply_filters($name, $value) { return $value; }
function shortcode_atts($defaults, $attributes) { return array_merge($defaults, $attributes); }
function sanitize_key($value) { return preg_replace('/[^a-z0-9_-]/', '', strtolower($value)); }
function is_post_type_viewable($type) { return $type === 'post'; }
require dirname(__DIR__) . '/toolkit/svg-upload.php';
require dirname(__DIR__) . '/toolkit/grid/index.php';

function check($condition, $message) {
	if (!$condition) { throw new RuntimeException($message); }
	echo "PASS $message\n";
}

$safe = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><path d="M0 0h10v10Z"/></svg>';
check(is_string(corsivo_toolkit_svg_sanitize($safe)), 'ordinary SVG remains usable');
check(corsivo_toolkit_svg_sanitize('<!DOCTYPE svg [<!ENTITY payload "javascript:">]>' . $safe) === false, 'DTD is rejected before XML parsing');
check(corsivo_toolkit_svg_sanitize('<html xmlns="http://www.w3.org/1999/xhtml"/>') === false, 'non-SVG documents are rejected');
$vectors = [
	'<script>alert(1)</script><rect onload="alert(1)"/>',
	'<a href="jav&#x61;script:alert(1)"><text>x</text></a>',
	'<use xmlns:xlink="http://www.w3.org/1999/xlink" xlink:Href="https://example.invalid/remote.svg#x"/>',
	'<style>@import "https://example.invalid/style.css";</style><rect fill="url(https://example.invalid/pixel)"/>',
	'<image href="https://example.invalid/pixel.png"/>',
];
foreach ($vectors as $index => $vector) {
	$clean = corsivo_toolkit_svg_sanitize('<svg xmlns="http://www.w3.org/2000/svg">' . $vector . '</svg>');
	check(is_string($clean) && !preg_match('/javascript:|<script|onload=|example\.invalid/i', $clean), 'active SVG vector removed: ' . $index);
}
$path = tempnam(sys_get_temp_dir(), 'corsivo-svg-test-');
try {
	file_put_contents($path, '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script><rect width="10" height="10"/></svg>');
	$result = corsivo_toolkit_svg_validate_upload(['name' => 'test.svg', 'tmp_name' => $path]);
	check(empty($result['error']) && !str_contains(file_get_contents($path), '<script'), 'the sanitized XML replaces the uploaded bytes');
	file_put_contents($path, gzencode(str_repeat('x', 2 * 1024 * 1024 + 1)));
	check(corsivo_toolkit_svg_get_file_contents($path, 'svgz') === '', 'expanded SVGZ has a bounded size');
} finally {
	unlink($path);
}
check(corsivo_toolkit_grid_shortcode(['post_type' => 'corsivo_submission']) === '', 'private post types are rejected before querying the grid');
