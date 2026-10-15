<?php

if (PHP_SAPI !== 'cli') exit(1);

define('ABSPATH', __DIR__ . '/');

set_error_handler(function ($severity, $message, $file, $line) {
	throw new ErrorException($message, 0, $severity, $file, $line);
});

function add_action(...$arguments) {}
function add_filter(...$arguments) {}
function apply_filters($hook, $value) { return $GLOBALS['fixture_filters'][$hook] ?? $value; }
function corsivo_theme_get_settings() { return $GLOBALS['fixture_settings']; }
function corsivo_theme_current_language() { return 'default'; }
function corsivo_theme_wpml_enabled() { return false; }
function corsivo_theme_custom_slug_post_type_allowed($post_type) { return true; }
function sanitize_html_class($value) { return preg_replace('/[^a-zA-Z0-9_-]/', '', $value); }
function sanitize_title($value) { return sanitize_html_class($value); }
function sanitize_key($value) { return strtolower(sanitize_html_class($value)); }
function wp_register_style(...$arguments) {}
function wp_enqueue_style(...$arguments) {}
function wp_add_inline_style($handle, $css) { $GLOBALS['fixture_styles'][$handle] = $css; }
function register_block_style($block, $style) { $GLOBALS['fixture_registered_styles'][$block][$style['name']] = $style; }
function is_feed() { return $GLOBALS['fixture_feed']; }
function get_query_var($name) { return $GLOBALS['wp_query']->query_vars[$name] ?? ''; }
function wp_unslash($value) { return $value; }
function wp_parse_url($url, $component) { return parse_url($url, $component); }
function home_url($path) { return 'https://example.com' . $path; }
function untrailingslashit($value) { return rtrim($value, '/'); }
function trailingslashit($value) { return untrailingslashit($value) . '/'; }
function wp_safe_redirect($url, $status) { throw new RuntimeException('Unexpected redirect to ' . $url); }
function _wp_array_get($input, $path, $default = null) {
	foreach ($path as $key) {
		if (!is_array($input) || !array_key_exists($key, $input)) return $default;
		$input = $input[$key];
	}
	return $input;
}
function wp_get_typography_font_size_value($preset, $settings) { return $preset['size']; }

require dirname(__DIR__) . '/includes/functions.php';
require dirname(__DIR__) . '/includes/actions.php';

function check($condition, $message) {
	if (!$condition) throw new RuntimeException($message);
	echo 'PASS ' . $message . "\n";
}

function declarations_at_width($css, $selector, $width) {
	$declarations = array();
	preg_match_all('/(?:@media \(max-width: ([0-9.]+)px\)\{([^{}]*\{[^{}]*\})\}|([^{}]*\{[^{}]*\}))/', $css, $rules, PREG_SET_ORDER);
	foreach ($rules as $rule) {
		if ($rule[1] !== '' && $width > (float) $rule[1]) continue;
		$body = $rule[1] !== '' ? $rule[2] : $rule[3];
		$opening = strpos($body, '{');
		$selectors = array_map('trim', explode(',', substr($body, 0, $opening)));
		if (!in_array($selector, $selectors, true)) continue;
		$declarations = array_merge($declarations, corsivo_theme_parse_css_declarations(substr($body, $opening + 1, -1)));
	}
	return $declarations;
}

foreach (array('padding', 'margin') as $property) {
	foreach (array(
		'1rem' => array('1rem', '1rem', '1rem', '1rem'),
		'calc(1rem + 2vw) 2rem' => array('calc(1rem + 2vw)', '2rem', 'calc(1rem + 2vw)', '2rem'),
		'1rem auto 3rem' => array('1rem', 'auto', '3rem', 'auto'),
		'clamp(1rem, calc(2rem + 1vw), 3rem) var(--space, 2rem) 0 4rem' => array('clamp(1rem, calc(2rem + 1vw), 3rem)', 'var(--space, 2rem)', '0', '4rem'),
	) as $value => $expected) {
		$parsed = corsivo_theme_css_to_theme_json($property . ': ' . $value . ';');
		check(array_values($parsed['spacing'][$property]) === $expected, $property . ' shorthand preserves ' . $value);
	}
}
check(corsivo_theme_split_css_value('var(--space, "a b;c") 2rem') === array('var(--space, "a b;c")', '2rem'), 'Whitespace and semicolons in quoted function arguments remain intact');
$quoted = corsivo_theme_css_to_theme_json('margin: var(--space, "a b;c") 2rem; color: red;');
check($quoted['spacing']['margin']['top'] === 'var(--space, "a b;c")' && $quoted['color']['text'] === 'red', 'Theme JSON conversion respects quoted semicolons between declarations');
$ordered = corsivo_theme_css_to_theme_json('padding: 1rem; padding-left: 2rem; padding: 3rem;');
check($ordered['spacing']['padding']['left'] === '3rem', 'Repeated shorthand declarations preserve CSS source order');
check(corsivo_theme_css_to_theme_json('margin: 1px 2px 3px 4px 5px;') === array(), 'Invalid shorthand arity does not emit misleading theme JSON');

$font = array(
	'slug' => 'display',
	'css' => 'font-family: Example; font-size: 5rem; line-height: 1.1; font-weight: 500;',
	'breakpoints' => array(
		'mobile' => array('css' => 'font-size: 2rem;'),
		'tablet' => array('css' => 'font-size: 3rem; line-height: 1.4; font-weight: 600;'),
	),
);
$GLOBALS['fixture_filters'] = array(
	'corsivo_theme_fonts' => array($font),
	'corsivo_theme_breakpoints' => array('mobile' => 640, 'tablet' => 960),
);
$settings = array('body' => array('font' => 'display'), 'heading' => array('font' => 'display', 'fontWeight' => '700'), 'titleStyle' => 'display', 'defaultParagraphStyle' => 'display', 'defaultHeadingStyle' => 'display');
$GLOBALS['fixture_filters']['corsivo_theme_global_styles'] = $settings;
$explicit = corsivo_theme_generate_font_breakpoint_css($font);
check(strpos($explicit, 'max-width: 960px') < strpos($explicit, 'max-width: 640px'), 'Font media queries are emitted from widest to narrowest');
check(declarations_at_width($explicit, '.is-style-font-display', 600) === array('font-size' => '2rem', 'line-height' => '1.4', 'font-weight' => '600'), 'Mobile fonts retain tablet properties not overridden at mobile');

foreach (array(false, true) as $is_editor) {
	$label = $is_editor ? 'Editor' : 'Frontend';
	$defaults = corsivo_theme_generate_default_block_css($settings, 'body', $is_editor);
	foreach (array('paragraph', 'heading') as $type) {
		$selectors = $is_editor ? corsivo_theme_get_editor_default_block_selectors($type) : corsivo_theme_get_frontend_default_block_selectors('body', $type);
		$wide = declarations_at_width($defaults, $selectors[0], 1200);
		$mobile = declarations_at_width($defaults, $selectors[0], 600);
		check($wide['font-size'] === '5rem' && $mobile['font-size'] === '2rem' && $mobile['line-height'] === '1.4', $label . ' default ' . $type . ' receives responsive typography');
		check(str_contains($selectors[0], ':not([class*="is-style-font-"])'), $label . ' default ' . $type . ' preserves explicitly styled blocks');
	}
	$titles = $is_editor ? corsivo_theme_generate_editor_global_page_title_css() : corsivo_theme_generate_global_page_title_css();
	$title_selectors = $is_editor ? corsivo_theme_get_editor_page_title_selectors() : corsivo_theme_get_frontend_page_title_selectors('body');
	check(declarations_at_width($titles, $title_selectors[0], 600)['font-size'] === '2rem', $label . ' global title receives responsive typography');
	$global = corsivo_theme_generate_global_font_breakpoint_css($is_editor);
	$body_selector = $is_editor ? '.editor-styles-wrapper' : 'body';
	$heading_selector = ($is_editor ? '' : ':root ') . $body_selector . ' :where(h2:not([class*="is-style-font-"]))';
	check(declarations_at_width($global, $body_selector, 600)['font-size'] === '2rem', $label . ' global body receives responsive typography');
	$heading = declarations_at_width($global, $heading_selector, 600);
	check($heading['font-size'] === '2rem' && $heading['font-weight'] === '700', $label . ' global heading retains its explicit weight at breakpoints');
}

$template = array('body' => $settings['body'], 'heading' => $settings['heading'], 'titleStyle' => 'display');
$frontend = corsivo_theme_generate_template_css_for_selector('body.special', $template);
$editor = corsivo_theme_generate_editor_template_css($template);
foreach (array(
	array($frontend, 'body.special', 'Frontend template body'),
	array($frontend, ':root body.special :where(h2:not(.entry-title):not(.page-title):not(.wp-block-post-title):not(.archive-title):not([class*="is-style-font-"]))', 'Frontend template heading'),
	array($frontend, 'body.special .page-title', 'Frontend template title'),
	array($editor, '.editor-styles-wrapper', 'Editor template body'),
	array($editor, '.editor-styles-wrapper h2', 'Editor template heading'),
	array($editor, '.editor-post-title__input', 'Editor template title'),
) as $target) {
	$mobile = declarations_at_width($target[0], $target[1], 600);
	check($mobile['font-size'] === '2rem' && $mobile['line-height'] === '1.4', $target[2] . ' receives responsive typography');
	if (str_contains($target[2], 'heading')) check($mobile['font-weight'] === '700', $target[2] . ' retains its explicit font weight');
}
$static_template = array('titleStyle' => 'static');
$GLOBALS['fixture_filters']['corsivo_theme_fonts'][] = array('slug' => 'static', 'css' => 'font-size: 4rem;');
$cascade = corsivo_theme_generate_editor_global_page_title_css() . corsivo_theme_generate_editor_template_css($static_template);
check(declarations_at_width($cascade, '.editor-post-title__input', 600)['font-size'] === '4rem', 'A template title overrides global responsive typography in source order');

require dirname(__DIR__, 4) . '/wp-includes/class-wp-theme-json.php';
$native_reflection = new ReflectionClass('WP_Theme_JSON');
$native_theme = $native_reflection->newInstanceWithoutConstructor();
$native_styles = array('typography' => array('fontSize' => '5rem'), 'blocks' => array('core/heading' => array('typography' => array('fontSize' => '5rem')), 'core/paragraph' => array('typography' => array('fontSize' => '5rem'))));
$native_reflection->getProperty('theme_json')->setValue($native_theme, array('settings' => array(), 'styles' => $native_styles));
$native_body = $native_theme->get_styles_for_block(array('selector' => 'body', 'path' => array('styles')));
$native_heading = $native_theme->get_styles_for_block(array('selector' => '.wp-block-heading', 'path' => array('styles', 'blocks', 'core/heading')));
$native_paragraph = $native_theme->get_styles_for_block(array('selector' => 'p', 'path' => array('styles', 'blocks', 'core/paragraph')));
check(str_starts_with($native_body, 'body{') && str_starts_with($native_heading, ':root :where(') && str_starts_with($native_paragraph, ':root :where('), 'Native WordPress emits body and block font rules at their expected specificity');
foreach (array('paragraph', 'heading') as $type) {
	$selectors = corsivo_theme_get_frontend_default_block_selectors('body', $type);
	check(str_starts_with($selectors[0], ':root body :where(') && str_contains($selectors[0], ':not([class*="is-style-font-"])'), 'Frontend default ' . $type . ' outranks native block typography while excluding explicit fonts');
}
check(str_contains(corsivo_theme_generate_global_font_breakpoint_css(), ':root body :where(h2:not([class*="is-style-font-"]))'), 'Global heading breakpoints outrank native block typography');
check(str_contains($frontend, ':root body.special :where(h2') && !str_contains($frontend, ':root body.special {'), 'Template headings preserve their scope while body rules retain native body specificity');

$hidden_style = array('target' => 'core/button', 'slug' => 'legacy', 'hideInEditor' => true, 'css' => 'color: red; padding: calc(1rem + 2vw) 2rem;', ':hover' => 'color: blue;', 'parts' => array(array('selector' => '.icon', 'css' => 'margin: 1px 2px 3px;')));
$GLOBALS['fixture_settings'] = array('configurator' => array('core_components' => array('blockStyles' => array($hidden_style))));
$GLOBALS['fixture_filters']['corsivo_theme_core_components'] = $GLOBALS['fixture_settings']['configurator']['core_components'];
corsivo_theme_register_cc_block_styles();
check(isset($GLOBALS['fixture_registered_styles']['core/button']['legacy']), 'Hidden custom styles remain registered on the server for theme JSON');
corsivo_theme_enqueue_cc_block_styles_css();
$hidden_css = $GLOBALS['fixture_styles']['corsivo-theme-block-styles'];
check(str_contains($hidden_css, '.is-style-legacy') && str_contains($hidden_css, 'color: red;') && str_contains($hidden_css, ':hover{color: blue;}') && str_contains($hidden_css, '.icon{margin: 1px 2px 3px;}'), 'Hidden styles retain base, hover and nested-part CSS for existing content');
$theme_json = new class {
	public function update_with($settings) { return $settings; }
};
$injected = corsivo_theme_inject_theme_json($theme_json);
$variation = $injected['styles']['blocks']['core/button']['variations']['legacy'];
check($variation['color']['text'] === 'red' && $variation[':hover']['color']['text'] === 'blue' && $variation['spacing']['padding']['top'] === 'calc(1rem + 2vw)', 'Hidden styles retain their theme JSON variation and functional shorthand values');

$GLOBALS['fixture_settings'] = array('custom_slugs' => array('product' => array('default' => array('plural' => 'catalog'))));
$GLOBALS['wp_query'] = (object) array('is_archive' => true, 'query' => array('post_type' => 'product'), 'query_vars' => array('paged' => 0));
$GLOBALS['fixture_feed'] = true;
foreach (array('/product/feed/', '/product/feed/rss2/', '/?post_type=product&feed=atom') as $request_uri) {
	$_SERVER['REQUEST_URI'] = $request_uri;
	corsivo_theme_handle_missing_cpt_posts();
	check(true, 'Archive feed retains its endpoint: ' . $request_uri);
}
$GLOBALS['fixture_feed'] = false;
$_SERVER['REQUEST_URI'] = '/catalog/';
corsivo_theme_handle_missing_cpt_posts();
check(true, 'The canonical HTML archive still avoids unnecessary redirects');
$_SERVER['REQUEST_URI'] = '/product/';
try {
	corsivo_theme_handle_missing_cpt_posts();
	throw new RuntimeException('The HTML archive did not redirect.');
} catch (RuntimeException $error) {
	check($error->getMessage() === 'Unexpected redirect to https://example.com/catalog/', 'The native HTML archive still redirects to its configured replacement');
}
