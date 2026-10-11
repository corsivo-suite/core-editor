<?php

if (PHP_SAPI !== 'cli') exit(1);

define('ABSPATH', dirname(__DIR__, 4) . '/');
require ABSPATH . 'wp-includes/class-wp-error.php';
require ABSPATH . 'wp-includes/rest-api/class-wp-rest-request.php';

set_error_handler(function ($severity, $message, $file, $line) {
	throw new ErrorException($message, 0, $severity, $file, $line);
});

function add_action(...$arguments) {}
function add_filter(...$arguments) {}
function do_action(...$arguments) {}
function is_wp_error($value) { return $value instanceof WP_Error; }
function sanitize_title($value) { return strtolower(trim(preg_replace('/[^a-zA-Z0-9-]/', '-', $value), '-')); }
function sanitize_key($value) { return strtolower(preg_replace('/[^a-zA-Z0-9_-]/', '', $value)); }
function sanitize_text_field($value) { return trim(strip_tags($value)); }
function sanitize_textarea_field($value) { return trim(strip_tags($value)); }
function esc_url_raw($value, $protocols = array()) { return $value; }
function absint($value) { return abs((int) $value); }
function wp_is_json_media_type($value) { return $value === 'application/json'; }
function rest_ensure_response($value) { return $value; }
function corsivo_theme_get_settings($force = false) { return $GLOBALS['fixture_settings']; }
function corsivo_theme_settings_revision($settings) { return hash('sha256', serialize($settings)); }
function corsivo_theme_write_settings_file($settings, $revision, $after_write = null) {
	$GLOBALS['write_calls']++;
	if ($GLOBALS['fail_write'] || $revision !== corsivo_theme_settings_revision($GLOBALS['fixture_settings'])) {
		return new WP_Error('write_failed', 'Write failed.', array('status' => 409));
	}
	$previous = $GLOBALS['fixture_settings'];
	$GLOBALS['fixture_settings'] = $settings;
	if ($after_write) $after_write($settings, $previous);
	$GLOBALS['last_written_settings'] = $settings;
	if (isset($GLOBALS['after_write_settings'])) $GLOBALS['fixture_settings'] = $GLOBALS['after_write_settings'];
	return true;
}
function corsivo_theme_changed_settings_sections($previous, $current) { return array(); }
function corsivo_theme_clear_generated_cache() { $GLOBALS['generated_cache_purges']++; return true; }
function corsivo_theme_log_activity(...$arguments) { $GLOBALS['activity_events'][] = $arguments; }
function get_metadata_by_mid($type, $meta_id) {
	return isset($GLOBALS['fixture_metadata'][$meta_id]) ? clone $GLOBALS['fixture_metadata'][$meta_id] : false;
}
function update_metadata_by_mid($type, $meta_id, $value) {
	if (!isset($GLOBALS['fixture_metadata'][$meta_id])) return false;
	$GLOBALS['fixture_metadata'][$meta_id]->meta_value = $value;
	return true;
}
function delete_metadata_by_mid($type, $meta_id) {
	if (!isset($GLOBALS['fixture_metadata'][$meta_id])) return false;
	unset($GLOBALS['fixture_metadata'][$meta_id]);
	return true;
}
function get_post($post_id) { return $GLOBALS['fixture_posts'][$post_id] ?? null; }
function post_password_required($post) { return !empty($post->post_password); }
function setup_postdata($post) {
	$GLOBALS['id'] = $post->ID;
	$GLOBALS['authordata'] = (object) array('ID' => 9);
	$GLOBALS['pages'] = array($post->post_content);
	$GLOBALS['more'] = 0;
}
function get_the_title($post) { return $post->post_title; }
function get_the_excerpt($post) {
	if (($GLOBALS['post']->ID ?? 0) !== $post->ID) throw new RuntimeException('Excerpt has the wrong post context.');
	return $post->post_excerpt;
}
function apply_filters($hook, $content) {
	if ($hook !== 'the_content') return $content;
	if (!empty($GLOBALS['fail_render'])) throw new RuntimeException('Rendering failed.');
	return $content . ':' . $GLOBALS['post']->post_title . ':' . $GLOBALS['id'];
}
function mysql_to_rfc3339($date) { return $date; }
function get_permalink($post) { return '/page/' . $post->ID; }

require dirname(__DIR__) . '/includes/functions.php';
require dirname(__DIR__) . '/includes/rest-api-configurator.php';
require dirname(__DIR__) . '/includes/rest-api.php';

$GLOBALS['wpdb'] = new class {
	public $postmeta = 'fixture_postmeta';
	public $arguments = array();
	public function prepare($query, $arguments) {
		$this->arguments = $arguments;
		return $query;
	}
	public function get_results($query) {
		$rows = array();
		foreach ($GLOBALS['fixture_metadata'] as $row) {
			if ($row->meta_key === $this->arguments[0] && in_array($row->meta_value, array_slice($this->arguments, 1), true)) $rows[] = clone $row;
		}
		if (!empty($GLOBALS['concurrent_meta_edit'])) {
			$GLOBALS['fixture_metadata'][1]->meta_value = $GLOBALS['concurrent_meta_edit'];
		}
		return $rows;
	}
};

function check($condition, $message) {
	if (!$condition) throw new RuntimeException($message);
	echo 'PASS ' . $message . "\n";
}

function make_request($payload) {
	$request = new WP_REST_Request('POST');
	$request->set_header('Content-Type', 'application/json');
	$request->set_body(json_encode($payload));
	return $request;
}

function set_fixture_metadata($slugs) {
	$GLOBALS['fixture_metadata'] = array();
	foreach ($slugs as $index => $slug) {
		$GLOBALS['fixture_metadata'][$index + 1] = (object) array(
			'meta_id' => $index + 1,
			'post_id' => 20 + $index,
			'meta_key' => '_corsivo_theme_style_template',
			'meta_value' => $slug,
		);
	}
}

check(corsivo_theme_sanitize_css_declarations('--BrandGap: 2rem; MARGIN: var(--BrandGap);') === "--BrandGap: 2rem;\nmargin: var(--BrandGap);", 'CSS custom properties retain their case and references');

$valid = array(
	'fonts' => array(array('slug' => 'body', 'css' => 'font-size: 1rem;', 'breakpoints' => array('font' => array('css' => 'font-size: 2rem;')))),
	'colors' => array(array('slug' => 'accent', 'color' => '#f00')),
	'style_templates' => array(array('slug' => 'a', 'body' => array('font' => 'body', 'color' => 'accent'))),
	'page_assignments' => array('color' => array('it' => 12)),
);
check(!is_wp_error(corsivo_theme_sanitize_configurator_request($valid)), 'valid references and dynamic mapping names remain supported');
check(!is_wp_error(corsivo_theme_sanitize_configurator_request(array('page_assignments' => array('legal' => array('it' => ''))))), 'clearing a functional page assignment remains a valid configurator payload');

$variable_fonts = array(array(
	'font-family' => 'Variable Font',
	'font-weight' => array('400 700' => 'file:./fonts/variable.woff2', '200 800' => 'https://example.com/variable-wide.woff2'),
	'font-style' => array('italic' => 'file:./fonts/variable-italic.woff2'),
	'font-style-weight' => array('italic' => '400 700'),
));
$variable_configurator = corsivo_theme_sanitize_configurator_request(array('install_fonts' => $variable_fonts));
check(!is_wp_error($variable_configurator) && $variable_configurator['install_fonts'] === $variable_fonts, 'installed variable font ranges and their file URLs survive saving');
check(corsivo_theme_sanitize_config_value(array('400 700' => 'outside-fonts'), 'spacing') === array(), 'font weight range keys are accepted only in installed font weight maps');
foreach (array('0 700', '800 200', '200 1001', 'very bold') as $weight_range) {
	$validation = corsivo_theme_sanitize_configurator_request(array('install_fonts' => array(array('font-weight' => array($weight_range => 'file:./fonts/variable.woff2')))));
	check(is_wp_error($validation) && $validation->get_error_data()['status'] === 400, 'invalid variable font weight range ' . $weight_range . ' is rejected');
}

$invalid_payloads = array(
	array('fonts' => array(array('slug' => array('nested'), 'css' => 'color: red;'))),
	array('global_styles' => array('body' => array('font' => array('nested')))),
	array('global_styles' => array('heading' => 'invalid')),
	array('global_styles' => array('titleStyle' => array('nested'))),
	array('core_components' => array('blockStyles' => array(array('slug' => 'sample', 'target' => 'core/button', 'inherits' => array('font:body'), 'css' => 'color: red;')))),
	array('context_styles' => array(array('context' => 'page', 'template' => array('a')))),
	array('install_fonts' => array(array('varname' => array('sample')))),
	array('install_fonts' => array(array('font-weight' => array('400' => array('url'))))),
	array('page_assignments' => array('legal' => array('it' => array(12)))),
	array('revision' => array('revision')),
	array('style_templates' => 'invalid'),
);
foreach ($invalid_payloads as $index => $payload) {
	$validation = corsivo_theme_sanitize_configurator_request($payload);
	check(is_wp_error($validation) && $validation->get_error_data()['status'] === 400, 'malformed configurator payload ' . ($index + 1) . ' returns 400 without PHP errors');
}

$old_configurator = array('style_templates' => array(array('slug' => 'a'), array('slug' => 'b')));
$new_configurator = array('style_templates' => array(array('slug' => 'b'), array('slug' => 'c')));
check(corsivo_theme_validate_template_renames(array('a' => 'b', 'b' => 'c'), $old_configurator, $new_configurator) === array('a' => 'b', 'b' => 'c'), 'template renames validate against the persisted and saved collections');
check(corsivo_theme_validate_template_renames(array('a' => 'c-'), $old_configurator, $new_configurator) === array('a' => 'c'), 'rename targets use the same slug sanitization as saved templates');
foreach (array(array('missing' => 'b'), array('a' => 'missing'), array('a' => 'b', 'b' => 'b'), array('a' => array('b'))) as $renames) {
	$result = corsivo_theme_validate_template_renames($renames, $old_configurator, $new_configurator);
	check(is_wp_error($result) && $result->get_error_data()['status'] === 400, 'invalid rename sources, targets and types are rejected');
}

set_fixture_metadata(array('a', 'b', 'a', 'untouched'));
$migration = corsivo_theme_migrate_template_assignments(array('a' => 'b', 'b' => 'a'), array());
check(array_column($GLOBALS['fixture_metadata'], 'meta_value') === array('b', 'a', 'b', 'untouched') && $migration['renamed_counts'] === array('a' => 2, 'b' => 1), 'swapping template slugs migrates every original assignment once');

set_fixture_metadata(array('a', 'b', 'untouched'));
$migration = corsivo_theme_migrate_template_assignments(array('a' => 'b'), array('b'));
check($GLOBALS['fixture_metadata'][1]->meta_value === 'b' && !isset($GLOBALS['fixture_metadata'][2]) && $GLOBALS['fixture_metadata'][3]->meta_value === 'untouched', 'removing a template does not erase another template renamed to its slug');

set_fixture_metadata(array('a'));
$GLOBALS['concurrent_meta_edit'] = 'user-choice';
corsivo_theme_migrate_template_assignments(array('a' => 'b'), array());
check($GLOBALS['fixture_metadata'][1]->meta_value === 'user-choice', 'concurrent page assignment changes survive the migration snapshot');
$GLOBALS['concurrent_meta_edit'] = '';

$GLOBALS['fixture_settings'] = array('configurator' => $old_configurator);
$GLOBALS['activity_events'] = array();
$GLOBALS['write_calls'] = 0;
$GLOBALS['generated_cache_purges'] = 0;
$GLOBALS['fail_write'] = true;
set_fixture_metadata(array('a', 'b'));
$save_payload = array('style_templates' => array(array('slug' => 'c')), 'template_renames' => array('a' => 'c'));
$save_result = corsivo_theme_save_config(make_request($save_payload));
check(is_wp_error($save_result) && array_column($GLOBALS['fixture_metadata'], 'meta_value') === array('a', 'b'), 'failed settings writes leave template assignments intact');
$GLOBALS['fail_write'] = false;
$save_result = corsivo_theme_save_config(make_request($save_payload));
check($save_result['success'] && $GLOBALS['fixture_metadata'][1]->meta_value === 'c' && !isset($GLOBALS['fixture_metadata'][2]) && !isset($GLOBALS['fixture_settings']['template_renames']), 'successful settings writes migrate renames and clear only deleted templates');
check($GLOBALS['generated_cache_purges'] === 0, 'ordinary configurator saves preserve asset bundles referenced by cached HTML');
$write_calls = $GLOBALS['write_calls'];
$save_payload['revision'] = str_repeat('0', 64);
$save_result = corsivo_theme_save_config(make_request($save_payload));
check(is_wp_error($save_result) && $save_result->get_error_data()['status'] === 409 && $GLOBALS['write_calls'] === $write_calls, 'stale configurator revisions cannot start assignment migration');

$GLOBALS['fixture_settings'] = array('configurator' => $old_configurator);
set_fixture_metadata(array('a', 'b'));
$save_result = corsivo_theme_save_config(make_request(array(
	'style_templates' => array(array('slug' => 'b')),
	'template_renames' => array('a' => 'b'),
)));
check($save_result['success'] && $GLOBALS['fixture_metadata'][1]->meta_value === 'b' && !isset($GLOBALS['fixture_metadata'][2]), 'saving a rename to a deleted template slug removes the deleted template assignments');

$GLOBALS['fixture_settings'] = array('configurator' => $old_configurator);
set_fixture_metadata(array('a', 'b'));
$save_result = corsivo_theme_save_config(make_request(array(
	'style_templates' => array(array('slug' => 'a'), array('slug' => 'b')),
	'template_renames' => array('a' => 'b', 'b' => 'a'),
)));
check($save_result['success'] && array_column($GLOBALS['fixture_metadata'], 'meta_value') === array('b', 'a'), 'saving simultaneous template exchanges preserves both template assignments');

$GLOBALS['fixture_settings'] = array('configurator' => array());
set_fixture_metadata(array());
$save_result = corsivo_theme_save_config(make_request(array('page_assignments' => array('legal' => array('it' => '')))));
check($save_result['success'] && $GLOBALS['fixture_settings']['functional_pages']['legal']['it'] === 0, 'cleared functional page assignments are saved as unassigned page IDs');

$GLOBALS['fixture_settings'] = array('configurator' => array());
$GLOBALS['after_write_settings'] = array('configurator' => array('spacing' => array('padding' => '3rem')));
$save_result = corsivo_theme_save_config(make_request(array('spacing' => array('padding' => '1rem'))));
$saved_revision = corsivo_theme_settings_revision($GLOBALS['last_written_settings']);
$last_activity = $GLOBALS['activity_events'][count($GLOBALS['activity_events']) - 1];
check($save_result['success'] && $save_result['revision'] === $saved_revision && $last_activity[4]['after_hash'] === $saved_revision && $save_result['revision'] !== corsivo_theme_settings_revision($GLOBALS['fixture_settings']), 'save responses and audit hashes identify their own write when another save follows');
unset($GLOBALS['after_write_settings']);
$save_result = corsivo_theme_save_config(make_request(array('revision' => $saved_revision, 'spacing' => array('padding' => '2rem'))));
check(is_wp_error($save_result) && $save_result->get_error_data()['status'] === 409 && $GLOBALS['fixture_settings']['configurator']['spacing']['padding'] === '3rem', 'a subsequent save using the first response revision cannot overwrite a later save');

$GLOBALS['fixture_posts'][12] = (object) array(
	'ID' => 12,
	'post_type' => 'page',
	'post_status' => 'publish',
	'post_password' => '',
	'post_title' => 'Legal',
	'post_name' => 'legal',
	'post_excerpt' => 'Legal excerpt',
	'post_content' => '<!-- wp:post-title /-->',
	'post_date' => '2026-10-11 12:00:00',
	'post_modified' => '2026-10-11 12:00:00',
);
$original_post = (object) array('ID' => 99);
$GLOBALS['post'] = $original_post;
$GLOBALS['id'] = 99;
$GLOBALS['pages'] = array('original');
unset($GLOBALS['authordata'], $GLOBALS['more']);
$GLOBALS['fail_render'] = false;
$rendered = corsivo_theme_prepare_functional_page(12);
check($rendered['post_content'] === '<!-- wp:post-title /-->:Legal:12', 'functional page content and excerpts render in their own post context');
check($GLOBALS['post'] === $original_post && $GLOBALS['id'] === 99 && $GLOBALS['pages'] === array('original') && !array_key_exists('authordata', $GLOBALS) && !array_key_exists('more', $GLOBALS), 'functional page rendering restores existing and unset globals');
$GLOBALS['fail_render'] = true;
$render_failed = false;
try {
	corsivo_theme_prepare_functional_page(12);
} catch (RuntimeException $error) {
	$render_failed = true;
}
check($render_failed && $GLOBALS['post'] === $original_post && $GLOBALS['id'] === 99 && $GLOBALS['pages'] === array('original') && !array_key_exists('authordata', $GLOBALS) && !array_key_exists('more', $GLOBALS), 'failed functional page rendering also restores the prior post context');
