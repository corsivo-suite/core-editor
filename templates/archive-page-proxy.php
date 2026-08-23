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

try {
	$wp_query = $page_query;
	$wp_the_query = $page_query;
	$post = $page_query->post;
	if ($post) setup_postdata($post);
	include $template;
} finally {
	$wp_query = $archive_query;
	$wp_the_query = $archive_main_query;
	$post = $archive_post;
	if ($post) setup_postdata($post);
	unset($GLOBALS['corsivo_theme_archive_page_context']);
}
