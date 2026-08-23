<?php

$plugin_dir = dirname(__DIR__);
$configurator_dir = $plugin_dir . '/configurator';
$assertions = 0;

function corsivo_theme_asset_assert($condition, $message) {
	global $assertions;
	$assertions++;
	if (!$condition) {
		fwrite(STDERR, "FAIL: {$message}\n");
		exit(1);
	}
}

$package = json_decode(file_get_contents($configurator_dir . '/package.json'), true);
$source = file_get_contents($configurator_dir . '/src/index.js');
$settings = file_get_contents($plugin_dir . '/includes/admin/settings.php');
$plugin_header = file_get_contents($plugin_dir . '/corsivo-theme-editor.php');
$asset_path = $configurator_dir . '/build/index.asset.php';
$script_path = $configurator_dir . '/build/index.js';
$style_path = $configurator_dir . '/build/index.css';
$rtl_style_path = $configurator_dir . '/build/index-rtl.css';

corsivo_theme_asset_assert(!empty($package['private']), 'Configurator package is private.');
corsivo_theme_asset_assert(
	($package['dependencies']['@corsivo-suite/wp-admin-ui'] ?? '') === '0.1.0',
	'Configurator uses the local private Corsivo UI package.'
);
corsivo_theme_asset_assert(
	substr_count($source, "@corsivo-suite/wp-admin-ui/styles.css") === 1,
	'Corsivo UI styles are imported exactly once.'
);
corsivo_theme_asset_assert(file_exists($asset_path), 'Configurator asset manifest exists.');
corsivo_theme_asset_assert(file_exists($script_path), 'Configurator script build exists.');
corsivo_theme_asset_assert(file_exists($style_path), 'Configurator stylesheet build exists.');
corsivo_theme_asset_assert(file_exists($rtl_style_path), 'Configurator RTL stylesheet build exists.');
foreach (['index.asset.php', 'index.js', 'index.css', 'index-rtl.css'] as $required_asset) {
	$required_asset_expression = '$build_dir . \'' . $required_asset . '\'';
	corsivo_theme_asset_assert(
		strpos($settings, $required_asset_expression) !== false,
		"Configurator enqueue gate requires {$required_asset}."
	);
}
corsivo_theme_asset_assert(
	strpos($settings, 'foreach ($required_assets as $required_asset)') !== false,
	'Configurator enqueue checks every required build asset before loading the manifest.'
);

$asset = require $asset_path;
corsivo_theme_asset_assert(
	in_array('wp-components', $asset['dependencies'], true),
	'Asset manifest declares WordPress components.'
);
corsivo_theme_asset_assert(
	in_array('wp-i18n', $asset['dependencies'], true),
	'Asset manifest declares WordPress i18n.'
);
corsivo_theme_asset_assert(
	strpos($plugin_header, 'Requires at least: 6.6') !== false,
	'Plugin header requires WordPress 6.6 for react-jsx-runtime.'
);

$built_script = file_get_contents($script_path);
$built_style = file_get_contents($style_path);
corsivo_theme_asset_assert(
	strpos($built_script, '@corsivo-suite/wp-admin-ui') === false,
	'Local package imports are resolved in the script build.'
);
corsivo_theme_asset_assert(
	strpos($built_style, '.corsivo-admin-ui-color-control') !== false &&
	strpos($built_style, '.corsivo-admin-ui-repeater') !== false &&
	strpos($built_style, '.corsivo-admin-ui-sortable-collection') !== false,
	'Built stylesheet contains the migrated Corsivo UI controls.'
);
corsivo_theme_asset_assert(
	substr_count($settings, "'configurator/build/index.css'") === 1,
	'Configurator stylesheet is enqueued once.'
);
corsivo_theme_asset_assert(
	substr_count($settings, "wp_style_add_data('corsivo-theme-configurator', 'rtl', 'replace')") === 1,
	'Configurator stylesheet replaces its LTR build with the generated RTL build on RTL sites.'
);
corsivo_theme_asset_assert(
	substr_count($settings, "wp_set_script_translations('corsivo-theme-configurator', 'corsivo-theme-editor')") === 1,
	'Configurator translations use the plugin text domain.'
);

echo "Configurator assets: {$assertions} assertions passed.\n";
