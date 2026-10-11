<?php
if (!defined('ABSPATH')) exit;

global $wpdb;

$checks = array();
$checks['post_type_normalization'] = corsivo_theme_normalize_post_type_query_var('product') === 'product'
	&& corsivo_theme_normalize_post_type_query_var(array('product', 'post')) === 'product'
	&& corsivo_theme_normalize_post_type_query_var(array()) === ''
	&& corsivo_theme_normalize_post_type_query_var(null) === '';
$maintenance_result = corsivo_theme_activity_schema_maintenance(true, true);
$checks['activity_storage_maintenance'] = $maintenance_result === true;
$table = corsivo_theme_activity_log_table();
$checks['activity_log_table'] = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
$checks['activity_log_engine'] = $wpdb->get_var($wpdb->prepare(
	'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s',
	$table
)) === 'InnoDB';
$checks['activity_log_schema'] = corsivo_theme_activity_log_schema_is_ready()
	&& corsivo_theme_activity_schema_full_ready()
	&& corsivo_theme_activity_schema_inventory_is_current()
	&& is_array(corsivo_theme_activity_schema_verified_payload())
	&& get_option(CORSIVO_THEME_ACTIVITY_SCHEMA_INTENT_OPTION, null) === null
	&& get_option(CORSIVO_THEME_ACTIVITY_SCHEMA_FAILURE_OPTION, null) === null;
$marker = corsivo_theme_activity_schema_verified_payload();
$checks['activity_storage_plain_marker'] = is_array($marker)
	&& corsivo_theme_activity_schema_has_exact_keys($marker, array(
		'kind',
		'marker_format',
		'namespace',
		'physical_fingerprint',
		'scope',
		'source_fingerprint',
		'verified_at',
	))
	&& $marker['marker_format'] === CORSIVO_THEME_ACTIVITY_SCHEMA_MARKER_FORMAT
	&& $marker['kind'] === 'verified'
	&& $marker['namespace'] === corsivo_theme_activity_schema_namespace()
	&& $marker['scope'] === CORSIVO_THEME_ACTIVITY_SCHEMA_SCOPE;

update_option(CORSIVO_THEME_ACTIVITY_SCHEMA_VERIFIED_OPTION, '0', false);
$invalid_zero_bytes = serialize(get_option(CORSIVO_THEME_ACTIVITY_SCHEMA_VERIFIED_OPTION, null));
$invalid_zero_closed = !corsivo_theme_activity_log_schema_is_ready()
	&& corsivo_theme_activity_schema_verification_due()
	&& serialize(get_option(CORSIVO_THEME_ACTIVITY_SCHEMA_VERIFIED_OPTION, null)) === $invalid_zero_bytes;
$invalid_zero_result = corsivo_theme_activity_schema_maintenance(true, true);
$checks['activity_storage_invalid_zero'] = $invalid_zero_closed
	&& is_wp_error($invalid_zero_result)
	&& $invalid_zero_result->get_error_code() === 'corsivo_theme_activity_schema_fresh_state_conflict'
	&& serialize(get_option(CORSIVO_THEME_ACTIVITY_SCHEMA_VERIFIED_OPTION, null)) === $invalid_zero_bytes;
update_option(CORSIVO_THEME_ACTIVITY_SCHEMA_VERIFIED_OPTION, $marker, false);

$invalid_marker = array('payload' => $marker, 'signature' => str_repeat('a', 64));
update_option(CORSIVO_THEME_ACTIVITY_SCHEMA_VERIFIED_OPTION, $invalid_marker, false);
$invalid_marker_closed = !corsivo_theme_activity_log_schema_is_ready();
$invalid_marker_result = corsivo_theme_activity_schema_maintenance(true, true);
$checks['activity_storage_current_only'] = $invalid_marker_closed
	&& is_wp_error($invalid_marker_result)
	&& $invalid_marker_result->get_error_code() === 'corsivo_theme_activity_schema_fresh_state_conflict'
	&& get_option(CORSIVO_THEME_ACTIVITY_SCHEMA_VERIFIED_OPTION, null) === $invalid_marker;
update_option(CORSIVO_THEME_ACTIVITY_SCHEMA_VERIFIED_OPTION, $marker, false);

$marker['source_fingerprint'] = str_repeat('0', 64);
update_option(CORSIVO_THEME_ACTIVITY_SCHEMA_VERIFIED_OPTION, $marker, false);
$source_gate_closed = !corsivo_theme_activity_log_schema_is_ready();
$source_self_heal = corsivo_theme_activity_schema_maintenance(true, true);
$checks['activity_storage_source_fingerprint'] = $source_gate_closed
	&& $source_self_heal === true
	&& is_array(corsivo_theme_activity_schema_verified_payload());

$marker = corsivo_theme_activity_schema_verified_payload();
$marker['verified_at'] = '2000-01-01 00:00:00';
update_option(CORSIVO_THEME_ACTIVITY_SCHEMA_VERIFIED_OPTION, $marker, false);
$stale_marker_ready = corsivo_theme_activity_log_schema_is_ready()
	&& corsivo_theme_activity_schema_marker_is_due(corsivo_theme_activity_schema_verified_payload());
$stale_self_heal = corsivo_theme_activity_schema_maintenance(true, true);
$checks['activity_storage_stale_availability'] = $stale_marker_ready
	&& $stale_self_heal === true
	&& !corsivo_theme_activity_schema_marker_is_due(corsivo_theme_activity_schema_verified_payload());

$intent = corsivo_theme_activity_schema_fresh_intent();
$intent_written = !is_wp_error($intent)
	&& corsivo_theme_activity_schema_write_option(CORSIVO_THEME_ACTIVITY_SCHEMA_INTENT_OPTION, $intent);
$intent_closes_gate = $intent_written
	&& is_array(corsivo_theme_activity_schema_intent_payload())
	&& !corsivo_theme_activity_log_schema_is_ready();
$intent_self_heal = corsivo_theme_activity_schema_maintenance(true, true);
$checks['activity_storage_plain_intent'] = $intent_closes_gate
	&& $intent_self_heal === true
	&& get_option(CORSIVO_THEME_ACTIVITY_SCHEMA_INTENT_OPTION, null) === null;

$failure_error = new WP_Error('corsivo_theme_runtime_smoke_failure', 'Runtime smoke failure state.');
corsivo_theme_activity_schema_record_failure($failure_error);
$failure_closes_gate = !corsivo_theme_activity_log_schema_is_ready();
$failure_self_heal = corsivo_theme_activity_schema_maintenance(true, true);
$checks['activity_storage_plain_failure'] = $failure_closes_gate
	&& $failure_self_heal === true
	&& get_option(CORSIVO_THEME_ACTIVITY_SCHEMA_FAILURE_OPTION, null) === null;

$descriptor = corsivo_theme_activity_schema_describe(false);
$configured_collation = $wpdb->collate;
$wpdb->collate = '';
$empty_collation_config_valid = corsivo_theme_activity_schema_descriptor_is_current($descriptor);
$wpdb->collate = $configured_collation;
$descriptor_with_foreign_key = $descriptor;
$descriptor_with_foreign_key['create'] = str_replace(
	') engine=innodb',
	', foreign key (`user_id`) references `external` (`id`) ) engine=innodb',
	$descriptor_with_foreign_key['create']
);
$checks['activity_storage_physical_envelope'] = $empty_collation_config_valid
	&& !corsivo_theme_activity_schema_descriptor_is_current($descriptor_with_foreign_key);

$storage_option_names = array(
	CORSIVO_THEME_ACTIVITY_SCHEMA_VERIFIED_OPTION,
	CORSIVO_THEME_ACTIVITY_SCHEMA_INTENT_OPTION,
	CORSIVO_THEME_ACTIVITY_SCHEMA_FAILURE_OPTION,
);
$storage_snapshot = function () use ($storage_option_names) {
	$snapshot = array();
	foreach ($storage_option_names as $option_name) {
		$snapshot[$option_name] = corsivo_theme_activity_schema_option_record($option_name);
	}
	return $snapshot;
};
$restore_storage_snapshot = function ($snapshot) {
	foreach ($snapshot as $option_name => $record) {
		if (!$record['exists']) {
			delete_option($option_name);
		} else {
			update_option($option_name, $record['value'], false);
		}
	}
};
$lock_order = array();
$query_observer = static function ($query) use (&$lock_order) {
	if (str_contains((string) $query, 'SELECT RELEASE_LOCK')) {
		$lock_order[] = 'lock_release';
	}

	return $query;
};
$failure_observer = static function ($option_name) use (&$lock_order) {
	if ($option_name === CORSIVO_THEME_ACTIVITY_SCHEMA_FAILURE_OPTION) {
		$lock_order[] = 'failure_write';
	}
};
$ordering_baseline = $storage_snapshot();
add_filter('query', $query_observer);
add_action('added_option', $failure_observer);
add_action('updated_option', $failure_observer);
try {
	$ordering_result = corsivo_theme_activity_schema_with_lock(static function () {
		return new WP_Error('corsivo_theme_activity_schema_order_probe', 'Storage ordering probe.');
	}, true);
} finally {
	remove_filter('query', $query_observer);
	remove_action('added_option', $failure_observer);
	remove_action('updated_option', $failure_observer);
}
$failure_write_position = array_search('failure_write', $lock_order, true);
$lock_release_position = array_search('lock_release', $lock_order, true);
$restore_storage_snapshot($ordering_baseline);
$checks['activity_storage_lock_ordering'] = is_wp_error($ordering_result)
	&& $failure_write_position !== false
	&& $lock_release_position !== false
	&& $failure_write_position < $lock_release_position;

$future_cases = array(
	array(CORSIVO_THEME_ACTIVITY_SCHEMA_VERIFIED_OPTION, 'marker_format', CORSIVO_THEME_ACTIVITY_SCHEMA_MARKER_FORMAT),
	array(CORSIVO_THEME_ACTIVITY_SCHEMA_INTENT_OPTION, 'intent_format', CORSIVO_THEME_ACTIVITY_SCHEMA_INTENT_FORMAT),
	array(CORSIVO_THEME_ACTIVITY_SCHEMA_FAILURE_OPTION, 'failure_format', CORSIVO_THEME_ACTIVITY_SCHEMA_FAILURE_FORMAT),
);
$future_formats_preserved = true;
foreach ($future_cases as $future_case) {
	$baseline = $storage_snapshot();
	update_option(
		$future_case[0],
		array($future_case[1] => (string) ($future_case[2] + 1), 'sentinel' => wp_generate_uuid4()),
		false
	);
	$future_state = $storage_snapshot();
	$future_result = corsivo_theme_activity_schema_maintenance(true, true);
	$future_formats_preserved = $future_formats_preserved
		&& is_wp_error($future_result)
		&& $future_result->get_error_code() === 'corsivo_theme_activity_schema_future_format'
		&& $storage_snapshot() === $future_state;
	$restore_storage_snapshot($baseline);
}
$checks['activity_storage_future_formats'] = $future_formats_preserved
	&& corsivo_theme_activity_schema_maintenance(true, true) === true;

$null_artifacts_rejected = true;
foreach (array(
	CORSIVO_THEME_ACTIVITY_SCHEMA_INTENT_OPTION,
	CORSIVO_THEME_ACTIVITY_SCHEMA_FAILURE_OPTION,
) as $null_option) {
	$baseline = $storage_snapshot();
	delete_option($null_option);
	add_option($null_option, null, '', false);
	$before = corsivo_theme_activity_schema_option_record($null_option);
	$null_result = corsivo_theme_activity_schema_maintenance(true, true);
	$null_artifacts_rejected = $null_artifacts_rejected
		&& !corsivo_theme_activity_log_schema_is_ready()
		&& is_wp_error($null_result)
		&& $null_result->get_error_code() === 'corsivo_theme_activity_schema_fresh_state_conflict'
		&& corsivo_theme_activity_schema_option_record($null_option) === $before;
	$restore_storage_snapshot($baseline);
}
$checks['activity_storage_persisted_null'] = $null_artifacts_rejected
	&& corsivo_theme_activity_schema_maintenance(true, true) === true;

$corrupt_exact_cases = array(
	CORSIVO_THEME_ACTIVITY_SCHEMA_INTENT_OPTION => array(
		'intent_format' => CORSIVO_THEME_ACTIVITY_SCHEMA_INTENT_FORMAT,
		'corrupt' => true,
	),
	CORSIVO_THEME_ACTIVITY_SCHEMA_FAILURE_OPTION => array(
		'failure_format' => CORSIVO_THEME_ACTIVITY_SCHEMA_FAILURE_FORMAT,
		'corrupt' => true,
	),
	CORSIVO_THEME_ACTIVITY_SCHEMA_VERIFIED_OPTION => array(
		'marker_format' => CORSIVO_THEME_ACTIVITY_SCHEMA_MARKER_FORMAT,
		'corrupt' => true,
	),
);
$corrupt_exact_rejected = true;
foreach ($corrupt_exact_cases as $corrupt_option => $corrupt_artifact) {
	$baseline = $storage_snapshot();
	update_option($corrupt_option, $corrupt_artifact, false);
	$before = corsivo_theme_activity_schema_option_record($corrupt_option);
	$corrupt_result = corsivo_theme_activity_schema_maintenance(true, true);
	$corrupt_exact_rejected = $corrupt_exact_rejected
		&& is_wp_error($corrupt_result)
		&& $corrupt_result->get_error_code() === 'corsivo_theme_activity_schema_fresh_state_conflict'
		&& corsivo_theme_activity_schema_option_record($corrupt_option) === $before;
	$restore_storage_snapshot($baseline);
}
$checks['activity_storage_corrupt_exact'] = $corrupt_exact_rejected
	&& corsivo_theme_activity_schema_maintenance(true, true) === true;

$fence_baseline = $storage_snapshot();
$expected_intent = corsivo_theme_activity_schema_fresh_intent();
$other_intent = corsivo_theme_activity_schema_fresh_intent();
delete_option(CORSIVO_THEME_ACTIVITY_SCHEMA_INTENT_OPTION);
add_option(CORSIVO_THEME_ACTIVITY_SCHEMA_INTENT_OPTION, $other_intent, '', false);
$ddl_before_fence = array();
$ddl_observer = static function ($query) use (&$ddl_before_fence) {
	if (preg_match('/^\s*(?:CREATE|ALTER|DROP)\s+TABLE\b/i', (string) $query)) {
		$ddl_before_fence[] = $query;
	}
	return $query;
};
add_filter('query', $ddl_observer);
$create_fence_result = corsivo_theme_activity_schema_create_current($expected_intent);
remove_filter('query', $ddl_observer);
$restore_storage_snapshot($fence_baseline);
delete_option(CORSIVO_THEME_ACTIVITY_SCHEMA_INTENT_OPTION);
add_option(CORSIVO_THEME_ACTIVITY_SCHEMA_INTENT_OPTION, $expected_intent, '', false);
$verified_record = corsivo_theme_activity_schema_option_record(CORSIVO_THEME_ACTIVITY_SCHEMA_VERIFIED_OPTION);
$intent_record = corsivo_theme_activity_schema_option_record(CORSIVO_THEME_ACTIVITY_SCHEMA_INTENT_OPTION);
$failure_record = corsivo_theme_activity_schema_option_record(CORSIVO_THEME_ACTIVITY_SCHEMA_FAILURE_OPTION);
update_option(CORSIVO_THEME_ACTIVITY_SCHEMA_INTENT_OPTION, $other_intent, false);
$finalize_fence_result = corsivo_theme_activity_schema_finalize(
	corsivo_theme_activity_schema_describe(false),
	$verified_record,
	$intent_record,
	$failure_record
);
$winning_intent_preserved = get_option(CORSIVO_THEME_ACTIVITY_SCHEMA_INTENT_OPTION, null) === $other_intent;
$restore_storage_snapshot($fence_baseline);
$checks['activity_storage_intent_fencing'] = is_wp_error($create_fence_result)
	&& $create_fence_result->get_error_code() === 'corsivo_theme_activity_schema_intent_fence_lost'
	&& $ddl_before_fence === array()
	&& is_wp_error($finalize_fence_result)
	&& $finalize_fence_result->get_error_code() === 'corsivo_theme_activity_schema_finalize_fence_lost'
	&& $winning_intent_preserved;

$empty_baseline = $storage_snapshot();
$original_prefix = $wpdb->prefix;
$empty_prefix = $original_prefix . 'corsivo_theme_empty_' . strtolower(wp_generate_password(8, false, false)) . '_';
$wpdb->prefix = $empty_prefix;
$empty_artifacts_rejected = true;
$empty_ddl = array();
$empty_ddl_observer = static function ($query) use (&$empty_ddl) {
	if (preg_match('/^\s*(?:CREATE|ALTER|DROP)\s+TABLE\b/i', (string) $query)) {
		$empty_ddl[] = $query;
	}
	return $query;
};
add_filter('query', $empty_ddl_observer);
foreach ($corrupt_exact_cases as $corrupt_option => $corrupt_artifact) {
	foreach ($storage_option_names as $storage_option_name) {
		delete_option($storage_option_name);
	}
	add_option($corrupt_option, $corrupt_artifact, '', false);
	$before = corsivo_theme_activity_schema_option_record($corrupt_option);
	$empty_result = corsivo_theme_activity_schema_maintenance(true, true);
	$empty_artifacts_rejected = $empty_artifacts_rejected
		&& is_wp_error($empty_result)
		&& $empty_result->get_error_code() === 'corsivo_theme_activity_schema_fresh_state_conflict'
		&& corsivo_theme_activity_schema_option_record($corrupt_option) === $before;
}
remove_filter('query', $empty_ddl_observer);
$wpdb->prefix = $original_prefix;
$restore_storage_snapshot($empty_baseline);
$checks['activity_storage_corrupt_empty'] = $empty_artifacts_rejected
	&& $empty_ddl === array()
	&& corsivo_theme_activity_schema_maintenance(true, true) === true;

$baseline = $storage_snapshot();
$missing_prefix = $original_prefix . 'corsivo_theme_missing_' . strtolower(wp_generate_password(8, false, false)) . '_';
$wpdb->prefix = $missing_prefix;
$missing_result = corsivo_theme_activity_schema_maintenance(true, true);
$missing_table = corsivo_theme_activity_log_table();
$missing_not_created = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $missing_table)) !== $missing_table;
$wpdb->prefix = $original_prefix;
$restore_storage_snapshot($baseline);
$checks['activity_storage_missing_attested_table'] = is_wp_error($missing_result)
	&& $missing_result->get_error_code() === 'corsivo_theme_activity_schema_fresh_state_conflict'
	&& $missing_not_created
	&& corsivo_theme_activity_schema_maintenance(true, true) === true;

$update_baseline = $storage_snapshot();
wp_clear_scheduled_hook('corsivo_theme_verify_activity_storage');
$update_started_at = time();
corsivo_theme_activity_schema_after_plugin_update(null, array(
	'action' => 'update',
	'type' => 'plugin',
	'plugin' => plugin_basename(CORSIVO_THEME_EDITOR_FILE),
));
$update_scheduled_at = wp_next_scheduled('corsivo_theme_verify_activity_storage');
$current_update_invalidated = !corsivo_theme_activity_schema_option_record(CORSIVO_THEME_ACTIVITY_SCHEMA_VERIFIED_OPTION)['exists']
	&& !corsivo_theme_activity_log_schema_is_ready()
	&& $update_scheduled_at !== false
	&& $update_scheduled_at <= $update_started_at + 2;
$restore_storage_snapshot($update_baseline);
wp_clear_scheduled_hook('corsivo_theme_verify_activity_storage');
$future_marker = $update_baseline[CORSIVO_THEME_ACTIVITY_SCHEMA_VERIFIED_OPTION]['value'];
$future_marker['marker_format'] = (string) (CORSIVO_THEME_ACTIVITY_SCHEMA_MARKER_FORMAT + 1);
update_option(CORSIVO_THEME_ACTIVITY_SCHEMA_VERIFIED_OPTION, $future_marker, false);
$future_update_before = serialize(corsivo_theme_activity_schema_option_record(CORSIVO_THEME_ACTIVITY_SCHEMA_VERIFIED_OPTION));
corsivo_theme_activity_schema_after_plugin_update(null, array(
	'action' => 'update',
	'type' => 'plugin',
	'plugins' => array(plugin_basename(CORSIVO_THEME_EDITOR_FILE)),
));
$future_update_preserved = serialize(corsivo_theme_activity_schema_option_record(CORSIVO_THEME_ACTIVITY_SCHEMA_VERIFIED_OPTION)) === $future_update_before;
$restore_storage_snapshot($update_baseline);
wp_clear_scheduled_hook('corsivo_theme_verify_activity_storage');
$checks['activity_storage_update_fence'] = $current_update_invalidated
	&& $future_update_preserved
	&& corsivo_theme_activity_schema_maintenance(true, true) === true;

$lock_resource = wp_json_encode(corsivo_theme_activity_schema_namespace(), JSON_UNESCAPED_SLASHES);
$lock_name = substr('corsivo_theme_' . hash('sha256', (string) $lock_resource), 0, 64);
$contender = new wpdb(DB_USER, DB_PASSWORD, DB_NAME, DB_HOST);
$contender_acquired = $contender->get_var($contender->prepare('SELECT GET_LOCK(%s, %d)', $lock_name, 0));
$lock_timeout_filter = function () {
	return 0;
};
add_filter('corsivo_theme_activity_schema_lock_timeout', $lock_timeout_filter);
$before_lock_timeout = $storage_snapshot();
$lock_timeout_result = corsivo_theme_activity_schema_maintenance(true, true);
$after_lock_timeout = $storage_snapshot();
remove_filter('corsivo_theme_activity_schema_lock_timeout', $lock_timeout_filter);
$contender_released = $contender->get_var($contender->prepare('SELECT RELEASE_LOCK(%s)', $lock_name));
$checks['activity_storage_lock_contention'] = (string) $contender_acquired === '1'
	&& is_wp_error($lock_timeout_result)
	&& $lock_timeout_result->get_error_code() === 'corsivo_theme_activity_schema_lock_timeout'
	&& $before_lock_timeout === $after_lock_timeout
	&& (string) $contender_released === '1'
	&& corsivo_theme_activity_schema_maintenance(true, true) === true;

$rotated_salt = function ($salt, $scheme) {
	return hash('sha256', 'corsivo-theme-runtime-smoke:' . $scheme);
};
add_filter('salt', $rotated_salt, 1000, 2);
$checks['activity_storage_salt_rotation'] = corsivo_theme_activity_log_schema_is_ready();
remove_filter('salt', $rotated_salt, 1000);
$registered_post_meta = get_registered_meta_keys('post');
$registered_page_meta = get_registered_meta_keys('post', 'page');
$checks['current_namespace'] = has_action('wp_ajax_corsivo_theme_download_theme_json', 'corsivo_theme_download_theme_json') !== false
	&& has_action('wp_ajax_corsivo_theme_download_scss', 'corsivo_theme_download_scss') !== false
	&& isset($registered_post_meta['_corsivo_theme_style_template'])
	&& isset($registered_page_meta['_corsivo_theme_footer_bg'])
	&& isset($registered_page_meta['_corsivo_theme_footer_bg_slug'])
	&& isset($registered_page_meta['_corsivo_theme_footer_color'])
	&& isset($registered_page_meta['_corsivo_theme_footer_color_slug']);

$valid_config = corsivo_theme_sanitize_configurator_request(array(
	'fonts' => array(array('slug' => 'Body', 'name' => 'Body', 'css' => 'font-size: 1rem;')),
	'custom_fonts' => array(array(
		'url' => 'https://fonts.googleapis.com/css2?family=Inter',
		'fonts' => array(array('family' => 'Inter', 'weights' => '400', 'italic' => false, 'varname' => 'inter')),
	)),
	'core_components' => array('blockStyles' => array(array(
		'target' => 'core/button',
		'slug' => 'primary',
		'css' => 'color: red;',
	))),
));
$checks['config_sanitizer'] = !is_wp_error($valid_config)
	&& $valid_config['fonts'][0]['slug'] === 'body'
	&& $valid_config['custom_fonts'][0]['fonts'][0]['varname'] === 'inter'
	&& $valid_config['core_components']['blockStyles'][0]['slug'] === 'primary';
$checks['css_guard'] = corsivo_theme_sanitize_css_declarations('color:red;}body{display:none') === '';

$duplicate_config = corsivo_theme_sanitize_configurator_request(array(
	'colors' => array(
		array('slug' => 'primary', 'name' => 'Primary', 'color' => '#000000'),
		array('slug' => 'primary', 'name' => 'Duplicate', 'color' => '#ffffff'),
	),
));
$checks['duplicate_guard'] = is_wp_error($duplicate_config) && $duplicate_config->get_error_code() === 'corsivo_theme_duplicate_slug';
$checks['context_specificity'] = corsivo_theme_context_specificity('page') > corsivo_theme_context_specificity('custom-context');

$field_font_config = corsivo_theme_sanitize_configurator_request(array(
	'fonts' => array(array('slug' => 'body', 'name' => 'Body', 'font-size' => '1rem')),
));
$string_google_font_config = corsivo_theme_sanitize_configurator_request(array(
	'custom_fonts' => array('https://fonts.googleapis.com/css2?family=Inter'),
));
$structured_block_config = corsivo_theme_sanitize_configurator_request(array(
	'core_components' => array('blocks' => array('core/button' => array('color' => array('text' => 'primary')))),
));
$checks['current_config_format_only'] = is_wp_error($field_font_config)
	&& $field_font_config->get_error_code() === 'corsivo_theme_unsupported_config_format'
	&& is_wp_error($string_google_font_config)
	&& $string_google_font_config->get_error_code() === 'corsivo_theme_unsupported_config_format'
	&& is_wp_error($structured_block_config)
	&& $structured_block_config->get_error_code() === 'corsivo_theme_unsupported_config_format';
$field_font_typography = corsivo_theme_resolve_font_typography('body', array(array(
	'slug' => 'body',
	'font-family' => 'Inter',
	'font-size' => '1rem',
)));
$checks['field_font_runtime_absent'] = $field_font_typography === array(
	'fontFamily' => 'var(--wp--preset--font-family--body)',
)
	&& corsivo_theme_resolve_font_family_value(array('font-family' => 'Inter')) === ''
	&& !function_exists('corsivo_theme_build_component_styles');

$large_activity_details = array('sections' => range(1, 100), 'payload' => str_repeat('x', 70000));
$activity_preview = corsivo_theme_activity_details_preview($large_activity_details);
$checks['activity_preview'] = isset($activity_preview['sections']['count'])
	&& $activity_preview['sections']['count'] === 100
	&& isset($activity_preview['payload'])
	&& strlen($activity_preview['payload']) < 600;

$test_fonts = function () {
	return array(array('slug' => 'roboto', 'name' => 'Body', 'css' => "font-family: 'Roboto', sans-serif;"));
};
$test_colors = function () {
	return array(
		array('slug' => 'black', 'name' => 'Black', 'color' => '#000000'),
		array('slug' => 'white', 'name' => 'White', 'color' => '#ffffff'),
	);
};
$test_install_fonts = function () {
	return array(array(
		'font-family' => 'Roboto',
		'varname' => 'roboto',
		'font-weight' => array('400' => 'file:./assets/fonts/roboto.woff2'),
		'font-style' => array('italic' => 'file:./assets/fonts/roboto-italic.woff2'),
		'font-style-weight' => array('italic' => '300 700'),
		'features' => array('liga', 'kern'),
	));
};
add_filter('corsivo_theme_colors', $test_colors, 1000);
add_filter('corsivo_theme_fonts', $test_fonts, 1000);
add_filter('corsivo_theme_install_fonts', $test_install_fonts, 1000);
$theme_json_test = corsivo_theme_inject_theme_json(new WP_Theme_JSON_Data(array('version' => 2), 'theme'))->get_data();
remove_filter('corsivo_theme_colors', $test_colors, 1000);
remove_filter('corsivo_theme_fonts', $test_fonts, 1000);
remove_filter('corsivo_theme_install_fonts', $test_install_fonts, 1000);
$theme_color_settings = $theme_json_test['settings']['color'] ?? array();
$theme_palette = $theme_color_settings['palette']['theme'] ?? ($theme_color_settings['palette'] ?? array());
$theme_palette_slugs = array_column($theme_palette, 'slug');
$checks['color_palette_source_of_truth'] = array_key_exists('defaultPalette', $theme_color_settings)
	&& $theme_color_settings['defaultPalette'] === false
	&& in_array('black', $theme_palette_slugs, true)
	&& in_array('white', $theme_palette_slugs, true);
$theme_font_families = $theme_json_test['settings']['typography']['fontFamilies']['theme']
	?? ($theme_json_test['settings']['typography']['fontFamilies'] ?? array());
$roboto_families = array_values(array_filter(
	$theme_font_families,
	function ($font_family) {
		return ($font_family['slug'] ?? '') === 'roboto';
	}
));
$roboto_faces = $roboto_families[0]['fontFace'] ?? array();
$roboto_sources = array_merge(...array_map(function ($font_face) {
	return $font_face['src'] ?? array();
}, $roboto_faces));
$roboto_italic_faces = array_values(array_filter($roboto_faces, function ($font_face) {
	return ($font_face['fontStyle'] ?? '') === 'italic';
}));
$checks['font_face_merge'] = count($roboto_families) === 1
	&& count($roboto_faces) === 2
	&& count(array_filter($roboto_sources, function ($source) {
		return strpos($source, 'file:./') === 0;
	})) === 2
	&& count($roboto_italic_faces) === 1
	&& ($roboto_italic_faces[0]['fontWeight'] ?? '') === '300 700'
	&& ($roboto_italic_faces[0]['fontFeatureSettings'] ?? '') === '"liga","kern"';

$scope_test_slug = 'font-corsivo-scope-smoke';
corsivo_theme_register_font_style(array('slug' => 'corsivo-scope-smoke', 'scope' => 'heading'), 'Scope Smoke');
$block_style_registry = WP_Block_Styles_Registry::get_instance();
$checks['font_scope'] = $block_style_registry->is_registered('core/heading', $scope_test_slug)
	&& !$block_style_registry->is_registered('core/paragraph', $scope_test_slug);

$administrator_id = get_current_user_id();
$administrator_can_search_pages = corsivo_theme_configurator_permissions_check();
wp_set_current_user(0);
$anonymous_can_search_pages = corsivo_theme_configurator_permissions_check();
wp_set_current_user($administrator_id);
$checks['page_search_capability'] = $administrator_can_search_pages && !$anonymous_can_search_pages;

$page_search_token = 'corsivo-search-' . strtolower(wp_generate_password(8, false, false));
$published_page_ids = array(
	wp_insert_post(array('post_type' => 'page', 'post_status' => 'publish', 'post_title' => $page_search_token . ' Alpha')),
	wp_insert_post(array('post_type' => 'page', 'post_status' => 'publish', 'post_title' => $page_search_token . ' Beta')),
);
$draft_page_id = wp_insert_post(array(
	'post_type' => 'page',
	'post_status' => 'draft',
	'post_title' => $page_search_token . ' Draft',
));
$search_request = new WP_REST_Request('GET');
$search_request->set_param('search', $page_search_token);
$search_request->set_param('page', 1);
$search_request->set_param('per_page', 1);
$search_response = corsivo_theme_search_configurator_pages($search_request);
$search_data = $search_response->get_data();
$checks['page_search_pagination'] = count($search_data['items'] ?? array()) === 1
	&& ($search_data['total'] ?? 0) === 2
	&& ($search_data['totalPages'] ?? 0) === 2;

$hydrate_request = new WP_REST_Request('GET');
$hydrate_request->set_param('include', implode(',', array_merge($published_page_ids, array($draft_page_id))));
$hydrate_response = corsivo_theme_search_configurator_pages($hydrate_request);
$hydrate_ids = array_column($hydrate_response->get_data()['items'] ?? array(), 'id');
sort($hydrate_ids);
$expected_hydrate_ids = $published_page_ids;
sort($expected_hydrate_ids);
$checks['page_search_hydration'] = $hydrate_ids === $expected_hydrate_ids;

foreach (array_merge($published_page_ids, array($draft_page_id)) as $page_id) {
	wp_delete_post($page_id, true);
}

$test_directory = trailingslashit(sys_get_temp_dir()) . 'corsivo-theme-' . wp_generate_password(12, false, false);
$test_settings_path = trailingslashit($test_directory) . 'corsivo-theme.json';
wp_mkdir_p($test_directory);
add_filter('corsivo_theme_settings_file_path', function () use ($test_settings_path) {
	return $test_settings_path;
});

$lock_handle = fopen($test_settings_path . '.lock', 'c');
$lock_acquired = $lock_handle && flock($lock_handle, LOCK_EX | LOCK_NB);
$lock_timeout_filter = function () {
	return 0.1;
};
add_filter('corsivo_theme_settings_lock_timeout', $lock_timeout_filter);
$locked_write = corsivo_theme_write_settings_file(array('locked' => true));
remove_filter('corsivo_theme_settings_lock_timeout', $lock_timeout_filter);
if ($lock_acquired) flock($lock_handle, LOCK_UN);
if ($lock_handle) fclose($lock_handle);
$checks['settings_lock_timeout'] = $lock_acquired
	&& is_wp_error($locked_write)
	&& $locked_write->get_error_code() === 'corsivo_theme_settings_lock_failed';

$first_settings = array('global' => array('core_renders' => false));
$second_settings = array(
	'global' => array('core_renders' => true),
	'functional_pages' => array(
		'kept' => array('default' => 100),
		'orphan' => array('default' => 102),
	),
);
$first_write = corsivo_theme_write_settings_file($first_settings);
$first_revision = corsivo_theme_settings_revision($first_settings);
$second_write = corsivo_theme_write_settings_file($second_settings, $first_revision);
$conflict = corsivo_theme_write_settings_file($first_settings, $first_revision);
$backup = corsivo_theme_decode_settings_json(@file_get_contents($test_settings_path . '.bak'));
$stored_settings = corsivo_theme_decode_settings_json(@file_get_contents($test_settings_path));

$checks['settings_write'] = $first_write === true && $second_write === true;
$checks['revision_conflict'] = is_wp_error($conflict) && $conflict->get_error_code() === 'corsivo_theme_revision_conflict';
$checks['settings_backup'] = $backup === $first_settings;
$checks['stored_revision'] = corsivo_theme_settings_revision($stored_settings) === corsivo_theme_settings_revision($second_settings);

add_filter('pre_option_corsivo_theme_activity_log_enabled', '__return_zero');
$replacement_result = corsivo_theme_save_settings(
	array('functional_pages' => array('kept' => array('default' => 101))),
	corsivo_theme_settings_revision($second_settings)
);
remove_filter('pre_option_corsivo_theme_activity_log_enabled', '__return_zero');
$replacement_settings = corsivo_theme_decode_settings_json(@file_get_contents($test_settings_path));
$checks['functional_pages_replace'] = $replacement_result === true
	&& ($replacement_settings['functional_pages'] ?? array()) === array('kept' => array('default' => 101));

foreach (array($test_settings_path, $test_settings_path . '.bak', $test_settings_path . '.lock') as $test_file) {
	if (file_exists($test_file)) unlink($test_file);
}
if (is_dir($test_directory)) rmdir($test_directory);

$failed = array_keys(array_filter($checks, function ($passed) {
	return !$passed;
}));

if (!empty($failed)) {
	WP_CLI::error('Failed: ' . implode(', ', $failed));
}

WP_CLI::success('Corsivo Theme runtime smoke tests passed.');
