<?php

define('ABSPATH', dirname(__DIR__) . DIRECTORY_SEPARATOR);

$actions = array();
$registered_pages = array();
$visible_submenus = array();
$capabilities = array();
$assertions = 0;

function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {
	global $actions;
	$actions[$hook][] = array($callback, $priority, $accepted_args);
	return true;
}

function add_options_page($page_title, $menu_title, $capability, $menu_slug, $callback) {
	global $registered_pages, $visible_submenus;
	$registered_pages[$menu_slug] = compact('page_title', 'menu_title', 'capability', 'callback');
	$visible_submenus['options-general.php'][$menu_slug] = true;
	return 'settings_page_' . $menu_slug;
}

function remove_submenu_page($parent_slug, $menu_slug) {
	global $visible_submenus;
	unset($visible_submenus[$parent_slug][$menu_slug]);
	return array();
}

function current_user_can($capability) {
	global $capabilities;
	return !empty($capabilities[$capability]);
}

function corsivo_theme_navigation_assert($condition, $message) {
	global $assertions;
	$assertions++;
	if (!$condition) {
		fwrite(STDERR, 'FAIL: ' . $message . PHP_EOL);
		exit(1);
	}
}

require ABSPATH . 'includes/admin/settings.php';

$registered_admin_callbacks = array_column($actions['admin_menu'] ?? array(), 0);
$visibility_actions = array_values(array_filter(
	$actions['admin_menu'] ?? array(),
	static fn($action) => $action[0] === 'corsivo_theme_hide_developer_settings_menu'
));

corsivo_theme_navigation_assert(in_array('corsivo_theme_settings_menu', $registered_admin_callbacks, true), 'Theme settings registration is missing.');
corsivo_theme_navigation_assert(count($visibility_actions) === 1 && $visibility_actions[0][1] === 999, 'Theme visibility filter does not run after menu registration.');

$capabilities = array('manage_options' => true);
corsivo_theme_settings_menu();
corsivo_theme_hide_developer_settings_menu();

corsivo_theme_navigation_assert(empty($visible_submenus['options-general.php']['corsivo-theme-editor']), 'Theme menu remains visible to a non-developer.');
corsivo_theme_navigation_assert(isset($registered_pages['corsivo-theme-editor']), 'Theme page registration was removed with its navigation entry.');
corsivo_theme_navigation_assert($registered_pages['corsivo-theme-editor']['capability'] === 'manage_options', 'Theme page capability changed.');
corsivo_theme_navigation_assert($registered_pages['corsivo-theme-editor']['callback'] === 'corsivo_theme_settings_page' && function_exists('corsivo_theme_settings_page'), 'Theme direct page callback is unavailable.');

$registered_pages = array();
$visible_submenus = array();
$capabilities = array('manage_options' => true, 'manage_corsivo_developer_tools' => true);
corsivo_theme_settings_menu();
corsivo_theme_hide_developer_settings_menu();

corsivo_theme_navigation_assert(!empty($visible_submenus['options-general.php']['corsivo-theme-editor']), 'Theme menu is hidden from a developer.');

echo 'Corsivo Theme admin navigation checks passed (' . $assertions . ' assertions).' . PHP_EOL;
