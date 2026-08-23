<?php
if (!defined('ABSPATH')) exit;

$breakpoint_filter = static function () {
	return array('desktop' => 1440, 'tablet' => 960, 'mobile' => 640);
};
add_filter('corsivo_theme_breakpoints', $breakpoint_filter, 1000);
$breakpoint_css = corsivo_theme_generate_font_breakpoint_css(array(
	'slug' => 'body',
	'breakpoints' => array(
		'tablet' => array('css' => 'font-size: 1rem; line-height: 1.5;'),
		'mobile' => array('css' => 'font-size: 0.875rem;'),
	),
));
remove_filter('corsivo_theme_breakpoints', $breakpoint_filter, 1000);

$templates = array(
	array(
		'slug' => 'night',
		'name' => 'Night',
		'body' => array('background' => 'black', 'color' => 'white'),
		'heading' => array('color' => 'white'),
		'link' => array('color' => 'accent'),
		'isDefault' => true,
	),
	array(
		'slug' => 'light',
		'name' => 'Light',
		'body' => array('background' => 'white', 'color' => 'black'),
		'heading' => array('color' => 'black'),
		'link' => array('color' => 'accent'),
	),
);
$context_styles = array(
	array('context' => 'single-post', 'template' => 'night'),
	array('context' => 'home', 'template' => 'light'),
);
$template_filter = static function () use ($templates) {
	return $templates;
};
$context_filter = static function () use ($context_styles) {
	return $context_styles;
};
add_filter('corsivo_theme_style_templates', $template_filter, 1000);
add_filter('corsivo_theme_context_styles', $context_filter, 1000);
$context_css = corsivo_theme_generate_context_css();
remove_filter('corsivo_theme_style_templates', $template_filter, 1000);
remove_filter('corsivo_theme_context_styles', $context_filter, 1000);

$expected_hashes = array(
	'breakpoints' => 'fd186e4dac7cf2392303be7b041c6baf2ffb2a9262f4eeb7850d4809b99719b3',
	'contexts' => 'ad6b0614ea378b1ff6871dd2d989ecfecb853bf486113badccb5ae456ab8674d',
);
$actual_hashes = array(
	'breakpoints' => hash('sha256', $breakpoint_css),
	'contexts' => hash('sha256', $context_css),
);

foreach ($expected_hashes as $output => $expected_hash) {
	if ($actual_hashes[$output] !== $expected_hash) {
		WP_CLI::error(sprintf('%s output changed: expected %s, got %s', $output, $expected_hash, $actual_hashes[$output]));
	}
}

WP_CLI::success('Configurator generated output matches the expected snapshots.');
