<?php
if (!defined('ABSPATH')) exit;

function corsivo_theme_prepare_functional_page($page_id) {
	$page = get_post(absint($page_id));
	if (!$page || $page->post_type !== 'page' || $page->post_status !== 'publish' || post_password_required($page)) {
		return null;
	}

	return array(
		'ID' => (int) $page->ID,
		'post_title' => get_the_title($page),
		'post_name' => $page->post_name,
		'post_excerpt' => get_the_excerpt($page),
		'post_content' => apply_filters('the_content', $page->post_content),
		'post_date' => mysql_to_rfc3339($page->post_date),
		'post_modified' => mysql_to_rfc3339($page->post_modified),
		'permalink' => get_permalink($page),
	);
}

function corsivo_theme_functional_pages_api() {
	$settings = corsivo_theme_get_settings();
	$current_language = corsivo_theme_current_language();
	$pages = array();

	if (empty($settings['functional_pages']) || !is_array($settings['functional_pages'])) {
		return rest_ensure_response((object) $pages);
	}

	foreach ($settings['functional_pages'] as $slug => $languages) {
		if (!is_array($languages) || empty($languages[$current_language])) continue;
		$page = corsivo_theme_prepare_functional_page($languages[$current_language]);
		if ($page) $pages[sanitize_key($slug)] = $page;
	}

	return rest_ensure_response((object) $pages);
}

function corsivo_theme_functional_page_api($request) {
	$settings = corsivo_theme_get_settings();
	$slug = sanitize_key($request['slug']);
	$current_language = corsivo_theme_current_language();
	$page_id = $settings['functional_pages'][$slug][$current_language] ?? 0;
	$page = $page_id ? corsivo_theme_prepare_functional_page($page_id) : null;

	if (!$page) {
		return new WP_Error('not_found', 'Page not found.', array('status' => 404));
	}

	return rest_ensure_response($page);
}

function corsivo_theme_settings_api() {
	register_rest_route('corsivo-theme-data', '/pages', array(
		'methods' => WP_REST_Server::READABLE,
		'callback' => 'corsivo_theme_functional_pages_api',
		'permission_callback' => '__return_true',
	));

	register_rest_route('corsivo-theme-data', '/page/(?P<slug>[a-zA-Z0-9_-]+)', array(
		'methods' => WP_REST_Server::READABLE,
		'callback' => 'corsivo_theme_functional_page_api',
		'permission_callback' => '__return_true',
		'args' => array(
			'slug' => array(
				'required' => true,
				'sanitize_callback' => 'sanitize_key',
				'validate_callback' => function ($value) {
					return is_string($value) && $value !== '' && strlen($value) <= 80;
				},
			),
		),
	));
}
add_action('rest_api_init', 'corsivo_theme_settings_api');
