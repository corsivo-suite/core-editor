<?php

$plugin_directory = dirname(__DIR__);
$runtime_files = [
	$plugin_directory . '/corsivo-core-editor.php',
	$plugin_directory . '/includes/activity-storage.php',
	$plugin_directory . '/includes/activity-log.php',
	$plugin_directory . '/uninstall.php',
];
$sources = [];

foreach ($runtime_files as $runtime_file) {
	$source = file_get_contents($runtime_file);
	if (!is_string($source)) {
		fwrite(STDERR, 'Unable to read ' . $runtime_file . PHP_EOL);
		exit(1);
	}
	$sources[$runtime_file] = $source;
}

$combined = implode("\n", $sources);
$storage_source = $sources[$plugin_directory . '/includes/activity-storage.php'];
$forbidden = [
	'/\bdbDelta\s*\(/i',
	'/\bALTER\s+TABLE\b/i',
	'/ACTIVITY_LOG_DB_VERSION/',
	'/corsivo_theme_activity_log_db_version/',
	'/wp-admin\/includes\/upgrade\.php/',
	'/add_filter\s*\(\s*[\'"]option_/i',
];
$failures = [];

foreach ($forbidden as $pattern) {
	if (preg_match($pattern, $combined)) {
		$failures[] = 'Forbidden pattern: ' . $pattern;
	}
}

$storage_forbidden = [
	'/\bhash_hmac\s*\(/i',
	'/\bwp_salt\s*\(/i',
	'/schema_seal/',
	'/schema_unseal/',
	'/\bsignature\b/',
	'/marker_is_opaque/',
	'/\bDROP\s+TABLE\b/i',
];

foreach ($storage_forbidden as $pattern) {
	if (preg_match($pattern, $storage_source)) {
		$failures[] = 'Forbidden storage pattern: ' . $pattern;
	}
}

function corsivo_theme_storage_static_function_source($source, $function_name) {
	$pattern = '/function\s+' . preg_quote($function_name, '/') . '\s*\([^)]*\)\s*\{.*?(?=\nfunction\s+|\z)/s';

	return preg_match($pattern, $source, $matches) ? $matches[0] : '';
}

$required = [
	'corsivo_theme_activity_schema_fresh_intent',
	'corsivo_theme_activity_schema_verified_payload',
	'corsivo_theme_activity_schema_option_record',
	'corsivo_theme_activity_schema_marker_is_reattestable',
	'corsivo_theme_activity_schema_failure_payload',
	'CORSIVO_THEME_ACTIVITY_SCHEMA_MARKER_FORMAT',
	'CORSIVO_THEME_ACTIVITY_SCHEMA_INTENT_FORMAT',
	'CORSIVO_THEME_ACTIVITY_SCHEMA_FAILURE_FORMAT',
	'CORSIVO_THEME_ACTIVITY_SCHEMA_SCOPE',
	"'marker_format'",
	"'intent_format'",
	"'failure_format'",
	"'namespace'",
	"'operation_id'",
	"'token'",
	'corsivo_theme_activity_schema_physical_fingerprint',
	'corsivo_theme_activity_schema_create_is_current',
	'corsivo_theme_activity_schema_source_fingerprint',
	'wp_prime_option_caches',
	"'corsivo_theme_rewrite_flush_needed'",
	'corsivo_theme_activity_schema_inventory_is_current',
	'corsivo_theme_activity_schema_future_format_error',
	'corsivo_theme_activity_schema_maintenance',
	'corsivo_theme_activity_schema_verify_worker',
	'upgrader_process_complete',
	'SELECT GET_LOCK',
	'SELECT RELEASE_LOCK',
	'corsivo_theme_activity_schema_populated_incompatible',
	'corsivo_theme_activity_schema_fresh_state_conflict',
	'corsivo_theme_activity_schema_intent_fence_lost',
	'corsivo_theme_activity_schema_finalize_fence_lost',
	'corsivo_theme_verify_activity_storage',
	'Update URI:        https://www.corsivo.dev/plugins/corsivo-core-editor',
];

foreach ($required as $needle) {
	if (!str_contains($combined, $needle)) {
		$failures[] = 'Missing contract: ' . $needle;
	}
}

$verified_start = strpos($storage_source, 'function corsivo_theme_activity_schema_verified_payload()');
$ready_start = strpos($storage_source, 'function corsivo_theme_activity_log_schema_is_ready()');
if ($verified_start === false || $ready_start === false || $ready_start <= $verified_start) {
	$failures[] = 'Fast marker function boundaries are missing.';
} else {
	$fast_marker_source = substr($storage_source, $verified_start, $ready_start - $verified_start);
	foreach (['information_schema', 'SHOW FULL', 'SHOW INDEX', 'SHOW CREATE', 'schema_describe', 'schema_inventory'] as $needle) {
		if (str_contains($fast_marker_source, $needle)) {
			$failures[] = 'Physical verification leaked into the fast marker: ' . $needle;
		}
	}
}

$readiness_start = strpos($storage_source, 'function corsivo_theme_activity_log_schema_is_ready()');
$full_ready_start = strpos($storage_source, 'function corsivo_theme_activity_schema_full_ready()');
if ($readiness_start === false || $full_ready_start === false || $full_ready_start <= $readiness_start) {
	$failures[] = 'Runtime readiness function boundaries are missing.';
} else {
	$readiness_source = substr($storage_source, $readiness_start, $full_ready_start - $readiness_start);
	foreach ([
		'CORSIVO_THEME_ACTIVITY_SCHEMA_INTENT_OPTION',
		'CORSIVO_THEME_ACTIVITY_SCHEMA_FAILURE_OPTION',
		'corsivo_theme_activity_schema_verified_payload()',
	] as $needle) {
		if (!str_contains($readiness_source, $needle)) {
			$failures[] = 'Runtime readiness is missing: ' . $needle;
		}
	}
}

$update_start = strpos($storage_source, 'function corsivo_theme_activity_schema_after_plugin_update');
$notice_start = strpos($storage_source, 'function corsivo_theme_activity_schema_notice');
if ($update_start === false || $notice_start === false || $notice_start <= $update_start) {
	$failures[] = 'Plugin update verifier boundaries are missing.';
} else {
	$update_source = substr($storage_source, $update_start, $notice_start - $update_start);
	if (str_contains($update_source, 'schema_maintenance')) {
		$failures[] = 'Plugin update runs physical maintenance in the old process.';
	}
	foreach (["['plugins']", "['plugin']", 'schema_schedule_verification'] as $needle) {
		if (!str_contains($update_source, $needle)) {
			$failures[] = 'Plugin update verifier is missing: ' . $needle;
		}
	}
	foreach ([
		'corsivo_theme_activity_schema_future_format_error()',
		'corsivo_theme_activity_schema_delete_option(CORSIVO_THEME_ACTIVITY_SCHEMA_VERIFIED_OPTION)',
	] as $needle) {
		if (!str_contains($update_source, $needle)) {
			$failures[] = 'Plugin update fencing is missing: ' . $needle;
		}
	}
}

foreach ([' foreign key ', ' constraint ', ' check (', ' partition by ', ' row_format=', ' tablespace '] as $needle) {
	if (!str_contains($storage_source, $needle)) {
		$failures[] = 'CREATE guard is missing: ' . $needle;
	}
}

$lock_source = corsivo_theme_storage_static_function_source(
	$storage_source,
	'corsivo_theme_activity_schema_with_lock'
);
$failure_write_position = strpos($lock_source, 'corsivo_theme_activity_schema_record_failure($result)');
$release_position = strpos($lock_source, 'SELECT RELEASE_LOCK');
if ($failure_write_position === false
	|| $release_position === false
	|| $failure_write_position >= $release_position) {
	$failures[] = 'Storage failure is not persisted before the named lock is released.';
} elseif (str_contains(substr($lock_source, $release_position), 'corsivo_theme_activity_schema_record_failure(')) {
	$failures[] = 'The post-release path can still persist a storage failure.';
}

$maintenance_source = corsivo_theme_storage_static_function_source(
	$storage_source,
	'corsivo_theme_activity_schema_maintenance'
);
foreach ([
	'corsivo_theme_activity_schema_record_failure(',
	'corsivo_theme_activity_schema_fail_closed(',
] as $outer_failure_write) {
	if (str_contains($maintenance_source, $outer_failure_write)) {
		$failures[] = 'Maintenance persists storage state after the lock wrapper returned: ' . $outer_failure_write;
	}
}

$intent_source = corsivo_theme_storage_static_function_source(
	$storage_source,
	'corsivo_theme_activity_schema_ensure_fresh_intent'
);
if (str_contains($intent_source, 'corsivo_theme_activity_schema_delete_option(')) {
	$failures[] = 'Fresh-intent still deletes an unverifiable persisted artifact.';
}

$finalize_source = corsivo_theme_storage_static_function_source(
	$storage_source,
	'corsivo_theme_activity_schema_finalize'
);
foreach ([
	'CORSIVO_THEME_ACTIVITY_SCHEMA_VERIFIED_OPTION',
	'CORSIVO_THEME_ACTIVITY_SCHEMA_INTENT_OPTION',
	'CORSIVO_THEME_ACTIVITY_SCHEMA_FAILURE_OPTION',
] as $fenced_option) {
	if (!str_contains($finalize_source, $fenced_option)) {
		$failures[] = 'Finalization does not fence the expected record: ' . $fenced_option;
	}
}

if ($failures) {
	fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
	exit(1);
}

echo 'Corsivo Core Editor current-only storage static checks passed.' . PHP_EOL;
