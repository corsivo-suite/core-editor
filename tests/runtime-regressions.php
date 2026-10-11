<?php
if (!defined('ABSPATH') || !defined('WP_CLI') || !WP_CLI) exit(1);

require_once dirname(__DIR__) . '/corsivo-theme-editor.php';

function corsivo_theme_runtime_assert($condition, $message) {
	if (!$condition) throw new RuntimeException($message);
}

foreach (['#fff8', '#11223380', 'rgba(12, 34, 56, 0.5)', 'transparent'] as $color) {
	corsivo_theme_runtime_assert(corsivo_theme_sanitize_footer_color($color) === $color, 'Footer preserves transparent color ' . $color);
}
foreach (['rgba(256, 0, 0, 0.5)', 'rgba(0, 0, 0, 1.5)', 'red; color: blue', '</style>', ['#fff']] as $color) {
	corsivo_theme_runtime_assert(corsivo_theme_sanitize_footer_color($color) === '', 'Footer rejects invalid color values.');
}

global $corsivo_theme_config, $wp, $wp_query, $wp_the_query, $wp_rewrite, $post, $paged;
$original_config = $corsivo_theme_config;
$original_query = $wp_query;
$original_main_query = $wp_the_query;
$original_post = $post;
$original_paged = $paged ?? null;
$original_server = $_SERVER;
$original_rules = $wp_rewrite->extra_rules_top;
$post_type = 'corsivo_runtime_book';
$parent_id = 1900000001;
$page_id = 1900000002;
$book_ids = [1900000003, 1900000004];
$fixtures = [
	$parent_id => new WP_Post((object) ['ID' => $parent_id, 'post_type' => 'page', 'post_name' => 'parent', 'post_parent' => 0, 'post_status' => 'publish']),
	$page_id => new WP_Post((object) ['ID' => $page_id, 'post_type' => 'page', 'post_name' => 'library', 'post_parent' => $parent_id, 'post_status' => 'publish', 'post_title' => 'Archive wrapper', 'post_content' => '']),
];
foreach ($book_ids as $index => $book_id) {
	$fixtures[$book_id] = new WP_Post((object) ['ID' => $book_id, 'post_type' => $post_type, 'post_name' => 'book-' . $index, 'post_parent' => 0, 'post_status' => 'publish', 'post_title' => 'Archive book ' . $index, 'post_content' => '']);
}
$flush_requests = 0;
$observe_flush = static function ($value, $previous) use (&$flush_requests) {
	$flush_requests++;
	return $previous;
};
$page_posts = static function ($posts, $query) use ($page_id, $fixtures) {
	if ((int) $query->get('page_id') !== $page_id) return $posts;
	$query->set('no_found_rows', true);
	return [$fixtures[$page_id]];
};
$template = tempnam(sys_get_temp_dir(), 'corsivo-archive-regression-');

try {
	foreach ($fixtures as $fixture_id => $fixture) wp_cache_set($fixture_id, $fixture, 'posts');
	register_post_type($post_type, ['public' => true, 'query_var' => 'corsivo_volume', 'has_archive' => true]);
	$corsivo_theme_config = ['custom_slugs' => [$post_type => ['default' => ['singular' => 'corsivo-book', 'archive_page' => $page_id]]]];
	add_filter('pre_update_option_corsivo_theme_rewrite_flush_needed', $observe_flush, 10, 2);
	$before = clone $fixtures[$page_id];
	$after = clone $before;
	$after->post_parent = 0;
	corsivo_theme_flush_on_archive_page_change($page_id, $after, $before);
	corsivo_theme_runtime_assert($flush_requests === 1, 'Moving an archive page requests a rewrite flush.');
	$before = clone $fixtures[$parent_id];
	$after = clone $before;
	$after->post_name = 'renamed-parent';
	corsivo_theme_flush_on_archive_page_change($parent_id, $after, $before);
	corsivo_theme_runtime_assert($flush_requests === 2, 'Renaming an archive ancestor requests a rewrite flush.');
	corsivo_theme_register_archive_rewrite_rules();
	$singular_pattern = '^corsivo\-book/([^/]+)/?$';
	corsivo_theme_runtime_assert(($wp_rewrite->extra_rules_top[$singular_pattern] ?? '') === 'index.php?corsivo_volume=$matches[1]', 'Singular rewrites use the registered query variable.');
	get_post_type_object($post_type)->query_var = false;
	corsivo_theme_register_archive_rewrite_rules();
	corsivo_theme_runtime_assert(($wp_rewrite->extra_rules_top[$singular_pattern] ?? '') === 'index.php?post_type=' . $post_type . '&name=$matches[1]', 'Singular rewrites support disabled query variables.');

	$archive_query = new WP_Query();
	$archive_query->query_vars = $archive_query->fill_query_vars(['post_type' => $post_type, 'paged' => 2, 'posts_per_page' => 2, 'update_post_term_cache' => false, 'update_post_meta_cache' => false]);
	$archive_query->posts = array_map(static function ($book_id) use ($fixtures) { return $fixtures[$book_id]; }, $book_ids);
	$archive_query->post = $archive_query->posts[0];
	$archive_query->post_count = 2;
	$archive_query->found_posts = 6;
	$archive_query->max_num_pages = 3;
	$archive_query->is_archive = true;
	$archive_query->is_post_type_archive = true;
	$wp_query = $archive_query;
	$wp_the_query = $archive_query;
	$post = $archive_query->post;
	$paged = 2;
	$_SERVER['REQUEST_URI'] = wp_parse_url(home_url('/library/page/2/'), PHP_URL_PATH);
	$block_markup = '<!-- wp:group --><div class="wp-block-group"><!-- wp:query {"query":{"inherit":true}} --><div class="wp-block-query"><!-- wp:post-template --><!-- wp:post-title /--><!-- /wp:post-template --><!-- wp:query-pagination --><!-- wp:query-pagination-next /--><!-- /wp:query-pagination --></div><!-- /wp:query --></div><!-- /wp:group -->';
	file_put_contents($template, '<?php echo "paged=" . get_query_var("paged") . ";"; echo do_blocks(' . var_export($block_markup, true) . '); echo "wrapper=" . get_the_title();');
	$GLOBALS['corsivo_theme_archive_page_context'] = ['page_id' => $page_id, 'post_type' => $post_type, 'template' => $template];
	add_filter('posts_pre_query', $page_posts, 10, 2);
	ob_start();
	include dirname(__DIR__) . '/templates/archive-page-proxy.php';
	$output = ob_get_clean();
	corsivo_theme_runtime_assert(str_contains($output, 'paged=2;'), 'Archive wrapper preserves the current archive page.');
	foreach ([0, 1] as $index) corsivo_theme_runtime_assert(str_contains($output, 'Archive book ' . $index), 'Inherited Query Loop renders archive posts.');
	corsivo_theme_runtime_assert(str_contains($output, 'query-pagination-next'), 'Inherited archive pagination renders the next page.');
	corsivo_theme_runtime_assert(str_contains($output, 'wrapper=Archive wrapper'), 'Rendering the archive Query Loop restores the page content context.');
	corsivo_theme_runtime_assert($wp_query === $archive_query && $wp_the_query === $archive_query && $post === $archive_query->posts[0], 'Archive proxy restores original global query and post.');
} finally {
	remove_filter('pre_update_option_corsivo_theme_rewrite_flush_needed', $observe_flush, 10);
	remove_filter('posts_pre_query', $page_posts, 10);
	unregister_post_type($post_type);
	foreach (array_keys($fixtures) as $fixture_id) wp_cache_delete($fixture_id, 'posts');
	$wp_rewrite->extra_rules_top = $original_rules;
	$corsivo_theme_config = $original_config;
	$wp_query = $original_query;
	$wp_the_query = $original_main_query;
	$post = $original_post;
	$paged = $original_paged;
	$_SERVER = $original_server;
	unset($GLOBALS['corsivo_theme_archive_page_context']);
	if (is_file($template)) unlink($template);
}

$writer_directory = sys_get_temp_dir() . '/corsivo-writer-regression-' . wp_generate_password(12, false, false);
$writer_path = $writer_directory . '/corsivo-theme.json';
$writer_location = static function () use ($writer_path) { return $writer_path; };
$writer_callback_called = false;
$writer_settings = ['configurator' => ['style_templates' => [['slug' => 'night', 'name' => 'Night']]]];
try {
	add_filter('corsivo_theme_settings_file_path', $writer_location);
	$result = corsivo_theme_write_settings_file($writer_settings, corsivo_theme_settings_revision([]), static function ($saved, $previous) use (&$writer_callback_called, $writer_settings, $writer_path) {
		$writer_callback_called = true;
		corsivo_theme_runtime_assert($saved === $writer_settings && $previous === [], 'Writer callback receives the committed and previous settings.');
		corsivo_theme_runtime_assert(json_decode(file_get_contents($writer_path), true) === $saved, 'Writer callback runs after verified persistence.');
		$contender = fopen($writer_path . '.lock', 'c');
		try {
			$acquired = flock($contender, LOCK_EX | LOCK_NB);
			if ($acquired) flock($contender, LOCK_UN);
			corsivo_theme_runtime_assert(!$acquired, 'Assignment migration runs under the configuration write lock.');
		} finally {
			fclose($contender);
		}
	});
	corsivo_theme_runtime_assert($result === true && $writer_callback_called, 'Writer invokes migration after a successful save.');
	$writer_callback_called = false;
	$result = corsivo_theme_write_settings_file($writer_settings, 'stale', static function () use (&$writer_callback_called) { $writer_callback_called = true; });
	corsivo_theme_runtime_assert(is_wp_error($result) && $result->get_error_code() === 'corsivo_theme_revision_conflict' && !$writer_callback_called, 'A conflicted write cannot migrate page assignments.');
	$contender = fopen($writer_path . '.lock', 'c');
	try {
		$acquired = flock($contender, LOCK_EX | LOCK_NB);
		corsivo_theme_runtime_assert($acquired, 'Writer releases the configuration lock.');
		if ($acquired) flock($contender, LOCK_UN);
	} finally {
		fclose($contender);
	}
} finally {
	remove_filter('corsivo_theme_settings_file_path', $writer_location);
	$corsivo_theme_config = $original_config;
	foreach ([$writer_path, $writer_path . '.lock', $writer_path . '.bak'] as $writer_file) {
		if (is_file($writer_file)) unlink($writer_file);
	}
	if (is_dir($writer_directory)) rmdir($writer_directory);
}

WP_CLI::success('Footer, archive rewrites and inherited Query Loop regressions passed.');
