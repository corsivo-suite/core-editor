<?php
if (!defined('ABSPATH')) exit;

$context = $GLOBALS['corsivo_theme_archive_page_context'] ?? array();
$page_id = isset($context['page_id']) ? absint($context['page_id']) : 0;
$template = isset($context['template']) ? $context['template'] : '';

if (!$page_id || !$template || !is_readable($template)) return;

global $wp_query, $wp_the_query, $post;

$archive_query = $wp_query;
$archive_main_query = $wp_the_query;
$archive_post = $post;
$page_query = new WP_Query(array('page_id' => $page_id, 'post_status' => 'publish'));
$page_query->set('paged', $archive_query->get('paged'));
$page_query->max_num_pages = $archive_query->max_num_pages;
$render_archive_query = static function ($pre_render, $parsed_block) use ($archive_query) {
	if ($pre_render !== null || ($parsed_block['blockName'] ?? '') !== 'core/query' || empty($parsed_block['attrs']['query']['inherit'])) return $pre_render;
	global $wp_query, $post;
	if ($wp_query === $archive_query) return $pre_render;
	$page_render_query = $wp_query;
	$page_render_post = $post;
	try {
		$wp_query = $archive_query;
		return render_block($parsed_block);
	} finally {
		$wp_query = $page_render_query;
		$post = $page_render_post;
		if ($post) setup_postdata($post);
	}
};

try {
	$wp_query = $page_query;
	$post = $page_query->post;
	if ($post) setup_postdata($post);
	add_filter('pre_render_block', $render_archive_query, 10, 2);
	include $template;
} finally {
	remove_filter('pre_render_block', $render_archive_query, 10);
	$wp_query = $archive_query;
	$wp_the_query = $archive_main_query;
	$post = $archive_post;
	if ($post) setup_postdata($post);
	unset($GLOBALS['corsivo_theme_archive_page_context']);
}
