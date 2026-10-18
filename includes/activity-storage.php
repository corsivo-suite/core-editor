<?php

if (!defined('ABSPATH')) {
	exit;
}

const CORSIVO_THEME_ACTIVITY_SCHEMA_INTENT_OPTION = 'corsivo_theme_activity_schema_intent';
const CORSIVO_THEME_ACTIVITY_SCHEMA_VERIFIED_OPTION = 'corsivo_theme_activity_schema_verified';
const CORSIVO_THEME_ACTIVITY_SCHEMA_FAILURE_OPTION = 'corsivo_theme_activity_schema_failure';
const CORSIVO_THEME_ACTIVITY_SCHEMA_MARKER_FORMAT = 1;
const CORSIVO_THEME_ACTIVITY_SCHEMA_INTENT_FORMAT = 1;
const CORSIVO_THEME_ACTIVITY_SCHEMA_FAILURE_FORMAT = 1;
const CORSIVO_THEME_ACTIVITY_SCHEMA_SCOPE = 'corsivo-theme:activity';
const CORSIVO_THEME_ACTIVITY_SCHEMA_VERIFICATION_INTERVAL = 86400;

function corsivo_theme_activity_log_table() {
	global $wpdb;

	return $wpdb->prefix . 'corsivo_theme_activity_log';
}

function corsivo_theme_activity_schema_canonicalize($value) {
	if (!is_array($value)) {
		return $value;
	}
	if (array_is_list($value)) {
		return array_map('corsivo_theme_activity_schema_canonicalize', $value);
	}

	ksort($value, SORT_STRING);
	foreach ($value as $key => $item) {
		$value[$key] = corsivo_theme_activity_schema_canonicalize($item);
	}

	return $value;
}

function corsivo_theme_activity_schema_namespace() {
	global $wpdb;

	return [
		'blog_id' => get_current_blog_id(),
		'prefix'  => $wpdb->prefix,
		'table'   => corsivo_theme_activity_log_table(),
	];
}

function corsivo_theme_activity_schema_inventory() {
	global $wpdb;

	$prefix = $wpdb->prefix . 'corsivo_theme_';
	$wpdb->last_error = '';
	$tables = $wpdb->get_col(
		$wpdb->prepare(
			'SELECT TABLE_NAME FROM information_schema.TABLES
			 WHERE TABLE_SCHEMA = DATABASE()
			 AND LEFT(TABLE_NAME, CHAR_LENGTH(%s)) = %s
			 ORDER BY TABLE_NAME ASC',
			$prefix,
			$prefix
		)
	);
	if (!is_array($tables) || $wpdb->last_error !== '') {
		return new WP_Error('corsivo_theme_activity_schema_inventory_failed', 'Activity storage inventory failed.');
	}

	return array_values(array_map('strval', $tables));
}

function corsivo_theme_activity_schema_inventory_is_current() {
	$inventory = corsivo_theme_activity_schema_inventory();

	return is_array($inventory) && $inventory === [corsivo_theme_activity_log_table()];
}

function corsivo_theme_activity_schema_has_exact_keys($value, $expected_keys) {
	if (!is_array($value)) {
		return false;
	}

	$keys = array_keys($value);
	sort($keys, SORT_STRING);
	sort($expected_keys, SORT_STRING);

	return $keys === $expected_keys;
}

function corsivo_theme_activity_schema_timestamp_is_valid($value) {
	if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/D', $value)) {
		return false;
	}

	$date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value, new DateTimeZone('UTC'));
	return $date instanceof DateTimeImmutable && $date->format('Y-m-d H:i:s') === $value;
}

function corsivo_theme_activity_schema_write_option($name, $value) {
	if (!update_option($name, $value, false) && get_option($name, null) !== $value) {
		return false;
	}

	return get_option($name, null) === $value;
}

function corsivo_theme_activity_schema_delete_option($name) {
	if (!corsivo_theme_activity_schema_option_record($name)['exists']) {
		return true;
	}
	delete_option($name);

	return !corsivo_theme_activity_schema_option_record($name)['exists'];
}

function corsivo_theme_activity_schema_option_record($name) {
	$missing = new stdClass();
	$value = get_option($name, $missing);

	return [
		'exists' => $value !== $missing,
		'value'  => $value !== $missing ? $value : null,
	];
}

function corsivo_theme_activity_schema_quoted_table() {
	$table = corsivo_theme_activity_log_table();
	if (!preg_match('/^[A-Za-z0-9_]+$/D', $table)) {
		return new WP_Error('corsivo_theme_activity_schema_table_invalid', 'Activity storage table name is invalid.');
	}

	return '`' . $table . '`';
}

function corsivo_theme_activity_schema_sql() {
	global $wpdb;

	$table = corsivo_theme_activity_schema_quoted_table();
	if (is_wp_error($table)) {
		return $table;
	}
	$charset_collate = $wpdb->get_charset_collate();

	return "CREATE TABLE {$table} (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		created_at_gmt DATETIME NOT NULL,
		request_id VARCHAR(36) NOT NULL,
		user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
		source VARCHAR(20) NOT NULL,
		event VARCHAR(64) NOT NULL,
		object_type VARCHAR(32) NOT NULL DEFAULT '',
		object_key VARCHAR(191) NOT NULL DEFAULT '',
		status VARCHAR(16) NOT NULL,
		summary TEXT NOT NULL,
		details LONGTEXT NULL,
		before_hash CHAR(64) NOT NULL DEFAULT '',
		after_hash CHAR(64) NOT NULL DEFAULT '',
		PRIMARY KEY (id),
		KEY created_at_gmt (created_at_gmt),
		KEY user_id (user_id),
		KEY event (event),
		KEY status (status)
	) ENGINE=InnoDB {$charset_collate}";
}

function corsivo_theme_activity_schema_source_fingerprint() {
	$sql = corsivo_theme_activity_schema_sql();
	if (is_wp_error($sql)) {
		return '';
	}

	return hash('sha256', 'corsivo-theme:activity-schema-source:v1:' . $sql);
}

function corsivo_theme_activity_schema_normalize_default($value) {
	if ($value === null) {
		return null;
	}

	return str_replace('current_timestamp()', 'current_timestamp', strtolower(trim((string) $value)));
}

function corsivo_theme_activity_schema_normalize_column_type($value) {
	$value = strtolower(preg_replace('/\s+/', ' ', trim((string) $value)));

	return preg_replace('/\b(bigint|int|smallint|tinyint)\(\d+\)/', '$1', $value);
}

function corsivo_theme_activity_schema_describe($include_rows = true) {
	global $wpdb;

	$table = corsivo_theme_activity_log_table();
	$quoted_table = corsivo_theme_activity_schema_quoted_table();
	if (is_wp_error($quoted_table)) {
		return $quoted_table;
	}

	$wpdb->last_error = '';
	$status = $wpdb->get_row(
		$wpdb->prepare(
			'SELECT ENGINE, TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s',
			$table
		),
		ARRAY_A
	);
	if (!$status) {
		return $wpdb->last_error === ''
			? null
			: new WP_Error('corsivo_theme_activity_schema_inspection_failed', 'Activity storage inspection failed.');
	}

	$columns = $wpdb->get_results("SHOW FULL COLUMNS FROM {$quoted_table}", ARRAY_A);
	$indexes = $wpdb->get_results("SHOW INDEX FROM {$quoted_table}", ARRAY_A);
	$create_row = $wpdb->get_row("SHOW CREATE TABLE {$quoted_table}", ARRAY_N);
	$rows = $include_rows ? $wpdb->get_var("SELECT COUNT(*) FROM {$quoted_table}") : null;
	if (!is_array($columns)
		|| !is_array($indexes)
		|| !is_array($create_row)
		|| ($include_rows && $rows === null)
		|| $wpdb->last_error !== '') {
		return new WP_Error('corsivo_theme_activity_schema_inspection_failed', 'Activity storage inspection failed.');
	}

	$normalized_columns = [];
	foreach ($columns as $column) {
		$normalized_columns[] = [
			'name'      => (string) $column['Field'],
			'type'      => corsivo_theme_activity_schema_normalize_column_type($column['Type']),
			'nullable'  => (string) $column['Null'] === 'YES',
			'default'   => corsivo_theme_activity_schema_normalize_default($column['Default']),
			'collation' => $column['Collation'] === null ? null : strtolower((string) $column['Collation']),
			'extra'     => strtolower(preg_replace('/\s+/', ' ', trim((string) $column['Extra']))),
		];
	}

	$normalized_indexes = [];
	foreach ($indexes as $index) {
		$name = (string) $index['Key_name'];
		$sequence = (int) $index['Seq_in_index'];
		$normalized_indexes[$name]['unique'] = (int) $index['Non_unique'] === 0;
		$normalized_indexes[$name]['type'] = strtoupper((string) $index['Index_type']);
		$normalized_indexes[$name]['columns'][$sequence] = [
			'name'      => (string) ($index['Column_name'] ?? ''),
			'sub_part'  => $index['Sub_part'] === null ? null : (int) $index['Sub_part'],
			'collation' => $index['Collation'] === null ? null : (string) $index['Collation'],
		];
	}
	foreach ($normalized_indexes as &$index) {
		ksort($index['columns'], SORT_NUMERIC);
		$index['columns'] = array_values($index['columns']);
	}
	unset($index);
	ksort($normalized_indexes, SORT_STRING);

	$create = preg_replace('/\s+AUTO_INCREMENT=\d+\b/i', '', trim((string) ($create_row[1] ?? '')));

	$descriptor = [
		'engine'    => strtolower((string) ($status['ENGINE'] ?? '')),
		'collation' => strtolower((string) ($status['TABLE_COLLATION'] ?? '')),
		'columns'   => $normalized_columns,
		'indexes'   => $normalized_indexes,
		'create'    => strtolower(preg_replace('/\s+/', ' ', $create)),
	];
	if ($include_rows) {
		$descriptor['rows'] = (int) $rows;
	}

	return $descriptor;
}

function corsivo_theme_activity_schema_expected_column($name, $type, $nullable, $default, $collation, $extra = '') {
	return [
		'name'      => $name,
		'type'      => $type,
		'nullable'  => $nullable,
		'default'   => $default,
		'collation' => $collation,
		'extra'     => $extra,
	];
}

function corsivo_theme_activity_schema_expected_index($unique, $columns) {
	return [
		'unique'  => $unique,
		'type'    => 'BTREE',
		'columns' => array_map(
			static fn($name) => [
				'name'      => $name,
				'sub_part'  => null,
				'collation' => 'A',
			],
			$columns
		),
	];
}

function corsivo_theme_activity_schema_expected_descriptor($collation) {
	return [
		'columns' => [
			corsivo_theme_activity_schema_expected_column('id', 'bigint unsigned', false, null, null, 'auto_increment'),
			corsivo_theme_activity_schema_expected_column('created_at_gmt', 'datetime', false, null, null),
			corsivo_theme_activity_schema_expected_column('request_id', 'varchar(36)', false, null, $collation),
			corsivo_theme_activity_schema_expected_column('user_id', 'bigint unsigned', false, '0', null),
			corsivo_theme_activity_schema_expected_column('source', 'varchar(20)', false, null, $collation),
			corsivo_theme_activity_schema_expected_column('event', 'varchar(64)', false, null, $collation),
			corsivo_theme_activity_schema_expected_column('object_type', 'varchar(32)', false, '', $collation),
			corsivo_theme_activity_schema_expected_column('object_key', 'varchar(191)', false, '', $collation),
			corsivo_theme_activity_schema_expected_column('status', 'varchar(16)', false, null, $collation),
			corsivo_theme_activity_schema_expected_column('summary', 'text', false, null, $collation),
			corsivo_theme_activity_schema_expected_column('details', 'longtext', true, null, $collation),
			corsivo_theme_activity_schema_expected_column('before_hash', 'char(64)', false, '', $collation),
			corsivo_theme_activity_schema_expected_column('after_hash', 'char(64)', false, '', $collation),
		],
		'indexes' => [
			'PRIMARY'        => corsivo_theme_activity_schema_expected_index(true, ['id']),
			'created_at_gmt' => corsivo_theme_activity_schema_expected_index(false, ['created_at_gmt']),
			'event'          => corsivo_theme_activity_schema_expected_index(false, ['event']),
			'status'         => corsivo_theme_activity_schema_expected_index(false, ['status']),
			'user_id'        => corsivo_theme_activity_schema_expected_index(false, ['user_id']),
		],
	];
}

function corsivo_theme_activity_schema_create_is_current($create, $collation) {
	global $wpdb;

	if (!is_string($create) || $create === '' || $collation === '') {
		return false;
	}

	$charset = strtolower(trim((string) $wpdb->charset));
	if ($charset === '' && str_contains($collation, '_')) {
		$charset = strstr($collation, '_', true);
	}
	if ($charset === '') {
		return false;
	}

	$normalized_create = strtolower(trim($create));
	$expected_start = 'create table `' . strtolower(corsivo_theme_activity_log_table()) . '` ( ';
	$expected_end = ') engine=innodb default charset=' . $charset . ' collate=' . $collation;
	if (!str_starts_with($normalized_create, $expected_start)
		|| !str_ends_with($normalized_create, $expected_end)) {
		return false;
	}

	foreach ([' foreign key ', ' constraint ', ' check (', ' partition by ', ' row_format=', ' tablespace '] as $forbidden) {
		if (str_contains($normalized_create, $forbidden)) {
			return false;
		}
	}

	return true;
}

function corsivo_theme_activity_schema_descriptor_is_current($descriptor) {
	global $wpdb;

	if (!is_array($descriptor) || ($descriptor['engine'] ?? '') !== 'innodb') {
		return false;
	}

	$collation = strtolower((string) ($descriptor['collation'] ?? ''));
	$configured_collation = strtolower(trim((string) $wpdb->collate));
	if ($collation === '' || ($configured_collation !== '' && $collation !== $configured_collation)) {
		return false;
	}

	$expected = corsivo_theme_activity_schema_expected_descriptor($collation);

	return ($descriptor['columns'] ?? null) === $expected['columns']
		&& ($descriptor['indexes'] ?? null) === $expected['indexes']
		&& corsivo_theme_activity_schema_create_is_current($descriptor['create'] ?? null, $collation);
}

function corsivo_theme_activity_schema_physical_fingerprint($descriptor) {
	if (!is_array($descriptor)) {
		return '';
	}

	unset($descriptor['rows']);
	$encoded = wp_json_encode(
		corsivo_theme_activity_schema_canonicalize($descriptor),
		JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
	);

	return is_string($encoded)
		? hash('sha256', 'corsivo-theme:activity-schema-physical:v1:' . $encoded)
		: '';
}

function corsivo_theme_activity_schema_verified_payload() {
	$marker = get_option(CORSIVO_THEME_ACTIVITY_SCHEMA_VERIFIED_OPTION, null);
	if (!corsivo_theme_activity_schema_has_exact_keys($marker, [
		'kind',
		'marker_format',
		'namespace',
		'physical_fingerprint',
		'scope',
		'source_fingerprint',
		'verified_at',
	])) {
		return false;
	}

	$source_fingerprint = corsivo_theme_activity_schema_source_fingerprint();
	if ($marker['marker_format'] !== CORSIVO_THEME_ACTIVITY_SCHEMA_MARKER_FORMAT
		|| $marker['kind'] !== 'verified'
		|| $marker['scope'] !== CORSIVO_THEME_ACTIVITY_SCHEMA_SCOPE
		|| $marker['namespace'] !== corsivo_theme_activity_schema_namespace()
		|| !is_string($marker['source_fingerprint'])
		|| !hash_equals($source_fingerprint, $marker['source_fingerprint'])
		|| !preg_match('/^[a-f0-9]{64}$/D', (string) $marker['physical_fingerprint'])
		|| !corsivo_theme_activity_schema_timestamp_is_valid($marker['verified_at'])) {
		return false;
	}

	return $marker;
}


function corsivo_theme_activity_schema_marker_is_reattestable($marker) {
	return corsivo_theme_activity_schema_has_exact_keys($marker, [
		'kind',
		'marker_format',
		'namespace',
		'physical_fingerprint',
		'scope',
		'source_fingerprint',
		'verified_at',
	])
		&& $marker['marker_format'] === CORSIVO_THEME_ACTIVITY_SCHEMA_MARKER_FORMAT
		&& $marker['kind'] === 'verified'
		&& $marker['scope'] === CORSIVO_THEME_ACTIVITY_SCHEMA_SCOPE
		&& $marker['namespace'] === corsivo_theme_activity_schema_namespace()
		&& is_string($marker['source_fingerprint'])
		&& preg_match('/^[a-f0-9]{64}$/D', $marker['source_fingerprint'])
		&& preg_match('/^[a-f0-9]{64}$/D', (string) $marker['physical_fingerprint'])
		&& corsivo_theme_activity_schema_timestamp_is_valid($marker['verified_at']);
}

function corsivo_theme_activity_log_schema_is_ready() {
	if (!empty($GLOBALS['corsivo_theme_activity_schema_release_failed'])
		|| corsivo_theme_activity_schema_option_record(CORSIVO_THEME_ACTIVITY_SCHEMA_INTENT_OPTION)['exists']
		|| corsivo_theme_activity_schema_option_record(CORSIVO_THEME_ACTIVITY_SCHEMA_FAILURE_OPTION)['exists']
		|| is_wp_error(corsivo_theme_activity_schema_future_format_error())) {
		return false;
	}
	return is_array(corsivo_theme_activity_schema_verified_payload());
}

function corsivo_theme_activity_schema_full_ready() {
	return corsivo_theme_activity_log_schema_is_ready();
}

function corsivo_theme_activity_schema_record_failure($error) {
	if (is_wp_error(corsivo_theme_activity_schema_future_format_error())) {
		return $error;
	}

	$failure = [
		'code'               => is_wp_error($error) ? $error->get_error_code() : 'corsivo_theme_activity_schema_failed',
		'failure_format'     => CORSIVO_THEME_ACTIVITY_SCHEMA_FAILURE_FORMAT,
		'failed_at'          => gmdate('Y-m-d H:i:s'),
		'kind'               => 'failure',
		'namespace'          => corsivo_theme_activity_schema_namespace(),
		'scope'              => CORSIVO_THEME_ACTIVITY_SCHEMA_SCOPE,
		'source_fingerprint' => corsivo_theme_activity_schema_source_fingerprint(),
	];
	if (!corsivo_theme_activity_schema_write_option(CORSIVO_THEME_ACTIVITY_SCHEMA_FAILURE_OPTION, $failure)) {
		corsivo_theme_activity_schema_delete_option(CORSIVO_THEME_ACTIVITY_SCHEMA_VERIFIED_OPTION);
	}

	return $error;
}

function corsivo_theme_activity_schema_failure_payload() {
	$failure = corsivo_theme_activity_schema_option_record(CORSIVO_THEME_ACTIVITY_SCHEMA_FAILURE_OPTION);
	if (!$failure['exists'] || !corsivo_theme_activity_schema_has_exact_keys($failure['value'], [
		'code',
		'failure_format',
		'failed_at',
		'kind',
		'namespace',
		'scope',
		'source_fingerprint',
	])) {
		return false;
	}
	$failure = $failure['value'];

	return $failure['failure_format'] === CORSIVO_THEME_ACTIVITY_SCHEMA_FAILURE_FORMAT
		&& $failure['kind'] === 'failure'
		&& $failure['scope'] === CORSIVO_THEME_ACTIVITY_SCHEMA_SCOPE
		&& $failure['namespace'] === corsivo_theme_activity_schema_namespace()
		&& is_string($failure['code'])
		&& $failure['code'] !== ''
		&& is_string($failure['source_fingerprint'])
		&& preg_match('/^[a-f0-9]{64}$/D', $failure['source_fingerprint'])
		&& hash_equals(corsivo_theme_activity_schema_source_fingerprint(), $failure['source_fingerprint'])
		&& corsivo_theme_activity_schema_timestamp_is_valid($failure['failed_at'])
		? $failure
		: false;
}

function corsivo_theme_activity_schema_failure_is_recent() {
	$failure = corsivo_theme_activity_schema_failure_payload();
	if (!is_array($failure)) {
		return false;
	}

	$failed_at = strtotime($failure['failed_at'] . ' UTC');
	return $failed_at !== false && $failed_at > time() - 5 * MINUTE_IN_SECONDS;
}

function corsivo_theme_activity_schema_fresh_intent() {
	try {
		$token = bin2hex(random_bytes(32));
	} catch (Throwable $error) {
		return new WP_Error('corsivo_theme_activity_schema_intent_token_failed', 'Fresh installation intent token could not be generated.');
	}

	return [
		'intent_format'      => CORSIVO_THEME_ACTIVITY_SCHEMA_INTENT_FORMAT,
		'kind'               => 'fresh',
		'namespace'          => corsivo_theme_activity_schema_namespace(),
		'operation_id'       => wp_generate_uuid4(),
		'scope'              => CORSIVO_THEME_ACTIVITY_SCHEMA_SCOPE,
		'source_fingerprint' => corsivo_theme_activity_schema_source_fingerprint(),
		'started_at'         => gmdate('Y-m-d H:i:s'),
		'token'              => $token,
	];
}

function corsivo_theme_activity_schema_intent_payload() {
	$intent = get_option(CORSIVO_THEME_ACTIVITY_SCHEMA_INTENT_OPTION, null);
	if (!corsivo_theme_activity_schema_has_exact_keys($intent, [
		'intent_format',
		'kind',
		'namespace',
		'operation_id',
		'scope',
		'source_fingerprint',
		'started_at',
		'token',
	])) {
		return false;
	}

	return $intent['intent_format'] === CORSIVO_THEME_ACTIVITY_SCHEMA_INTENT_FORMAT
		&& $intent['kind'] === 'fresh'
		&& $intent['scope'] === CORSIVO_THEME_ACTIVITY_SCHEMA_SCOPE
		&& $intent['namespace'] === corsivo_theme_activity_schema_namespace()
		&& is_string($intent['operation_id'])
		&& wp_is_uuid($intent['operation_id'], 4)
		&& is_string($intent['token'])
		&& preg_match('/^[a-f0-9]{64}$/D', $intent['token'])
		&& is_string($intent['source_fingerprint'])
		&& hash_equals(corsivo_theme_activity_schema_source_fingerprint(), $intent['source_fingerprint'])
		&& corsivo_theme_activity_schema_timestamp_is_valid($intent['started_at'])
		? $intent
		: false;
}

function corsivo_theme_activity_schema_has_valid_fresh_intent() {
	return is_array(corsivo_theme_activity_schema_intent_payload());
}

function corsivo_theme_activity_schema_ensure_fresh_intent() {
	$intent_record = corsivo_theme_activity_schema_option_record(CORSIVO_THEME_ACTIVITY_SCHEMA_INTENT_OPTION);
	if ($intent_record['exists']) {
		$intent = corsivo_theme_activity_schema_intent_payload();
		return is_array($intent)
			? $intent
			: new WP_Error('corsivo_theme_activity_schema_fresh_state_conflict', 'Empty activity storage contains an unverifiable intent.');
	}
	if (corsivo_theme_activity_schema_option_record(CORSIVO_THEME_ACTIVITY_SCHEMA_VERIFIED_OPTION)['exists']
		|| corsivo_theme_activity_schema_option_record(CORSIVO_THEME_ACTIVITY_SCHEMA_FAILURE_OPTION)['exists']) {
		return new WP_Error('corsivo_theme_activity_schema_fresh_state_conflict', 'Empty activity storage contains artifacts that do not authorize a new installation.');
	}

	$intent = corsivo_theme_activity_schema_fresh_intent();
	if (is_wp_error($intent)) {
		return $intent;
	}
	if (!add_option(CORSIVO_THEME_ACTIVITY_SCHEMA_INTENT_OPTION, $intent, '', false)) {
		$winner = corsivo_theme_activity_schema_intent_payload();
		if (is_array($winner)) {
			return $winner;
		}
		return corsivo_theme_activity_schema_option_record(CORSIVO_THEME_ACTIVITY_SCHEMA_INTENT_OPTION)['exists']
			? new WP_Error('corsivo_theme_activity_schema_fresh_state_conflict', 'A concurrent activity storage intent is not verifiable.')
			: new WP_Error('corsivo_theme_activity_schema_intent_write_failed', 'Fresh installation intent could not be stored.');
	}

	if (get_option(CORSIVO_THEME_ACTIVITY_SCHEMA_INTENT_OPTION, null) === $intent) {
		return $intent;
	}
	return corsivo_theme_activity_schema_option_record(CORSIVO_THEME_ACTIVITY_SCHEMA_INTENT_OPTION)['exists']
		? new WP_Error('corsivo_theme_activity_schema_fresh_state_conflict', 'The newly created activity storage intent was replaced.')
		: new WP_Error('corsivo_theme_activity_schema_intent_write_failed', 'Fresh installation intent could not be verified.');
}

function corsivo_theme_activity_schema_marker_is_due($marker) {
	if (!is_array($marker) || !corsivo_theme_activity_schema_timestamp_is_valid($marker['verified_at'] ?? null)) {
		return true;
	}

	$verified_at = strtotime($marker['verified_at'] . ' UTC');
	$current_time = time();

	return $verified_at === false
		|| $verified_at > $current_time + 300
		|| $verified_at <= $current_time - CORSIVO_THEME_ACTIVITY_SCHEMA_VERIFICATION_INTERVAL;
}

function corsivo_theme_activity_schema_future_format_error() {
	$states = [
		[
			'current' => CORSIVO_THEME_ACTIVITY_SCHEMA_MARKER_FORMAT,
			'key'     => 'marker_format',
			'name'    => 'verified marker',
			'option'  => CORSIVO_THEME_ACTIVITY_SCHEMA_VERIFIED_OPTION,
		],
		[
			'current' => CORSIVO_THEME_ACTIVITY_SCHEMA_INTENT_FORMAT,
			'key'     => 'intent_format',
			'name'    => 'installation intent',
			'option'  => CORSIVO_THEME_ACTIVITY_SCHEMA_INTENT_OPTION,
		],
		[
			'current' => CORSIVO_THEME_ACTIVITY_SCHEMA_FAILURE_FORMAT,
			'key'     => 'failure_format',
			'name'    => 'failure state',
			'option'  => CORSIVO_THEME_ACTIVITY_SCHEMA_FAILURE_OPTION,
		],
	];

	foreach ($states as $state) {
		$value = get_option($state['option'], null);
		$format = is_array($value) ? ($value[$state['key']] ?? null) : null;
		$is_numeric_format = is_int($format)
			|| (is_string($format) && ctype_digit($format));
		if ($is_numeric_format && (int) $format > $state['current']) {
			return new WP_Error(
				'corsivo_theme_activity_schema_future_format',
				'Activity storage contains a newer ' . $state['name'] . ' format.'
			);
		}
	}

	return null;
}

function corsivo_theme_activity_schema_verification_due() {
	$marker = corsivo_theme_activity_schema_verified_payload();

	return corsivo_theme_activity_schema_option_record(CORSIVO_THEME_ACTIVITY_SCHEMA_INTENT_OPTION)['exists']
		|| corsivo_theme_activity_schema_option_record(CORSIVO_THEME_ACTIVITY_SCHEMA_FAILURE_OPTION)['exists']
		|| !is_array($marker)
		|| corsivo_theme_activity_schema_marker_is_due($marker);
}

function corsivo_theme_activity_schema_create_current($expected_intent) {
	global $wpdb;

	if (corsivo_theme_activity_schema_intent_payload() !== $expected_intent) {
		return new WP_Error('corsivo_theme_activity_schema_intent_fence_lost', 'The installation intent is no longer the expected one.');
	}
	$sql = corsivo_theme_activity_schema_sql();
	if (is_wp_error($sql)) {
		return $sql;
	}
	if ($wpdb->query($sql) === false) {
		return new WP_Error(
			'corsivo_theme_activity_schema_create_failed',
			'Activity storage creation failed.',
			['db_error' => $wpdb->last_error]
		);
	}
	if (corsivo_theme_activity_schema_intent_payload() !== $expected_intent) {
		return new WP_Error('corsivo_theme_activity_schema_intent_fence_lost', 'The installation intent changed during storage creation.');
	}

	return true;
}

function corsivo_theme_activity_schema_finalize($descriptor, $expected_verified_record, $expected_intent_record, $expected_failure_record) {
	$future_format = corsivo_theme_activity_schema_future_format_error();
	if (is_wp_error($future_format)) {
		return $future_format;
	}
	if (corsivo_theme_activity_schema_option_record(CORSIVO_THEME_ACTIVITY_SCHEMA_VERIFIED_OPTION) !== $expected_verified_record
		|| corsivo_theme_activity_schema_option_record(CORSIVO_THEME_ACTIVITY_SCHEMA_INTENT_OPTION) !== $expected_intent_record
		|| corsivo_theme_activity_schema_option_record(CORSIVO_THEME_ACTIVITY_SCHEMA_FAILURE_OPTION) !== $expected_failure_record) {
		return new WP_Error('corsivo_theme_activity_schema_finalize_fence_lost', 'Activity storage artifacts changed before finalization.');
	}

	$descriptor = is_array($descriptor) ? $descriptor : corsivo_theme_activity_schema_describe();
	if (is_wp_error($descriptor)) {
		return $descriptor;
	}
	if (!corsivo_theme_activity_schema_inventory_is_current()
		|| !corsivo_theme_activity_schema_descriptor_is_current($descriptor)) {
		return new WP_Error('corsivo_theme_activity_schema_target_invalid', 'Current activity storage cannot be verified.');
	}

	$verified = [
		'kind'                 => 'verified',
		'marker_format'        => CORSIVO_THEME_ACTIVITY_SCHEMA_MARKER_FORMAT,
		'namespace'            => corsivo_theme_activity_schema_namespace(),
		'physical_fingerprint' => corsivo_theme_activity_schema_physical_fingerprint($descriptor),
		'scope'                => CORSIVO_THEME_ACTIVITY_SCHEMA_SCOPE,
		'source_fingerprint'   => corsivo_theme_activity_schema_source_fingerprint(),
		'verified_at'          => gmdate('Y-m-d H:i:s'),
	];
	if ($verified['physical_fingerprint'] === ''
		|| !corsivo_theme_activity_schema_write_option(CORSIVO_THEME_ACTIVITY_SCHEMA_VERIFIED_OPTION, $verified)
		|| !corsivo_theme_activity_schema_delete_option(CORSIVO_THEME_ACTIVITY_SCHEMA_FAILURE_OPTION)
		|| !corsivo_theme_activity_schema_delete_option(CORSIVO_THEME_ACTIVITY_SCHEMA_INTENT_OPTION)) {
		return new WP_Error('corsivo_theme_activity_schema_attestation_failed', 'Activity storage attestation failed.');
	}

	return true;
}

function corsivo_theme_activity_schema_fail_closed($error) {
	$future_format = corsivo_theme_activity_schema_future_format_error();
	if (is_wp_error($future_format)) {
		return $future_format;
	}

	corsivo_theme_activity_schema_record_failure($error);
	if (!corsivo_theme_activity_schema_delete_option(CORSIVO_THEME_ACTIVITY_SCHEMA_VERIFIED_OPTION)) {
		corsivo_theme_activity_schema_write_option(
			CORSIVO_THEME_ACTIVITY_SCHEMA_VERIFIED_OPTION,
			[
				'kind'          => 'invalid',
				'marker_format' => CORSIVO_THEME_ACTIVITY_SCHEMA_MARKER_FORMAT,
			]
		);
	}
	$GLOBALS['corsivo_theme_activity_schema_error'] = $error;

	return $error;
}

function corsivo_theme_activity_schema_with_lock($callback, $fail_closed = false) {
	global $wpdb;

	$resource = wp_json_encode(corsivo_theme_activity_schema_namespace(), JSON_UNESCAPED_SLASHES);
	$lock_name = substr('corsivo_theme_' . hash('sha256', (string) $resource), 0, 64);
	$lock_timeout = max(0, (int) apply_filters('corsivo_theme_activity_schema_lock_timeout', 10));
	$acquired = $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, %d)', $lock_name, $lock_timeout));
	if ((string) $acquired !== '1') {
		return new WP_Error('corsivo_theme_activity_schema_lock_timeout', 'Activity storage verification is already running.');
	}

	$result = null;
	$released = null;
	try {
		try {
			$result = call_user_func($callback);
		} catch (Throwable $error) {
			$result = new WP_Error(
				'corsivo_theme_activity_schema_exception',
				'Activity storage installation was interrupted.',
				['exception' => get_class($error)]
			);
		}
		if (is_wp_error($result)
			&& !in_array($result->get_error_code(), [
				'corsivo_theme_activity_schema_fresh_state_conflict',
				'corsivo_theme_activity_schema_intent_fence_lost',
				'corsivo_theme_activity_schema_finalize_fence_lost',
				'corsivo_theme_activity_schema_future_format',
				'corsivo_theme_activity_schema_lock_release_failed',
				'corsivo_theme_activity_schema_lock_timeout',
			], true)) {
			$result = $fail_closed
				? corsivo_theme_activity_schema_fail_closed($result)
				: corsivo_theme_activity_schema_record_failure($result);
		}
	} finally {
		$released = $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock_name));
	}

	if ((string) $released !== '1') {
		$GLOBALS['corsivo_theme_activity_schema_release_failed'] = true;
		corsivo_theme_activity_schema_schedule_verification(60);
		return new WP_Error('corsivo_theme_activity_schema_lock_release_failed', 'Activity storage lock release failed.');
	}
	$GLOBALS['corsivo_theme_activity_schema_release_failed'] = false;

	return $result;
}

function corsivo_theme_install_activity_log($fail_closed = false) {
	return corsivo_theme_activity_schema_with_lock(static function() {
		$future_format = corsivo_theme_activity_schema_future_format_error();
		if (is_wp_error($future_format)) {
			return $future_format;
		}

		$inventory = corsivo_theme_activity_schema_inventory();
		if (is_wp_error($inventory)) {
			return $inventory;
		}
		if (!in_array($inventory, [[], [corsivo_theme_activity_log_table()]], true)) {
			return new WP_Error(
				'corsivo_theme_activity_schema_namespace_untrusted',
				'Activity storage namespace contains unexpected tables.'
			);
		}

		$descriptor = corsivo_theme_activity_schema_describe();
		if (is_wp_error($descriptor)) {
			return $descriptor;
		}
		$intent_record = corsivo_theme_activity_schema_option_record(CORSIVO_THEME_ACTIVITY_SCHEMA_INTENT_OPTION);
		$intent = $intent_record['exists'] ? corsivo_theme_activity_schema_intent_payload() : null;
		if ($intent_record['exists'] && !is_array($intent)) {
			return new WP_Error('corsivo_theme_activity_schema_fresh_state_conflict', 'The activity storage intent is not verifiable.');
		}
		$failure_record = corsivo_theme_activity_schema_option_record(CORSIVO_THEME_ACTIVITY_SCHEMA_FAILURE_OPTION);
		if ($failure_record['exists'] && !is_array(corsivo_theme_activity_schema_failure_payload())) {
			return new WP_Error('corsivo_theme_activity_schema_fresh_state_conflict', 'The activity storage failure state is not verifiable.');
		}
		$verified_record = corsivo_theme_activity_schema_option_record(CORSIVO_THEME_ACTIVITY_SCHEMA_VERIFIED_OPTION);
		if ($verified_record['exists']
			&& !corsivo_theme_activity_schema_marker_is_reattestable($verified_record['value'])) {
			return new WP_Error('corsivo_theme_activity_schema_fresh_state_conflict', 'The activity storage marker is not verifiable.');
		}

		if (is_array($descriptor) && corsivo_theme_activity_schema_descriptor_is_current($descriptor)) {
			return corsivo_theme_activity_schema_finalize($descriptor, $verified_record, $intent_record, $failure_record);
		}

		if ($descriptor === null) {
			if ($inventory !== []) {
				return new WP_Error('corsivo_theme_activity_schema_inventory_inconsistent', 'Activity storage inventory is inconsistent.');
			}
			if ($verified_record['exists']) {
				return new WP_Error('corsivo_theme_activity_schema_fresh_state_conflict', 'A pre-existing marker does not authorize a new activity storage installation.');
			}

			$intent = corsivo_theme_activity_schema_ensure_fresh_intent();
			if (is_wp_error($intent)) {
				return $intent;
			}
			$intent_record = corsivo_theme_activity_schema_option_record(CORSIVO_THEME_ACTIVITY_SCHEMA_INTENT_OPTION);

			$created = corsivo_theme_activity_schema_create_current($intent);
			if (is_wp_error($created)) {
				return $created;
			}
			$created_descriptor = corsivo_theme_activity_schema_describe();
			return is_wp_error($created_descriptor)
				? $created_descriptor
				: corsivo_theme_activity_schema_finalize($created_descriptor, $verified_record, $intent_record, $failure_record);
		}

		$error_code = (int) ($descriptor['rows'] ?? 0) > 0
			? 'corsivo_theme_activity_schema_populated_incompatible'
			: 'corsivo_theme_activity_schema_empty_incompatible';

		return new WP_Error($error_code, 'Existing activity storage is incompatible with the current blueprint.');
	}, $fail_closed);
}

function corsivo_theme_activity_require_storage() {
	return corsivo_theme_activity_log_schema_is_ready()
		? true
		: new WP_Error(
			'corsivo_theme_activity_schema_not_ready',
			'Activity storage is not available.',
			['status' => 503]
		);
}

function corsivo_theme_activity_verify_boot() {
	if (function_exists('wp_prime_option_caches')) {
		wp_prime_option_caches([
			CORSIVO_THEME_ACTIVITY_SCHEMA_VERIFIED_OPTION,
			CORSIVO_THEME_ACTIVITY_SCHEMA_INTENT_OPTION,
			CORSIVO_THEME_ACTIVITY_SCHEMA_FAILURE_OPTION,
			'corsivo_theme_rewrite_flush_needed',
		]);
	}

	$result = corsivo_theme_activity_require_storage();
	$GLOBALS['corsivo_theme_activity_schema_error'] = is_wp_error($result) ? $result : null;
	if (!wp_doing_cron() && corsivo_theme_activity_schema_verification_due()) {
		corsivo_theme_activity_schema_schedule_verification();
	}

	return $result;
}

function corsivo_theme_activity_schema_schedule_verification($delay = MINUTE_IN_SECONDS) {
	$scheduled_for = time() + max(1, (int) $delay);
	$existing = wp_next_scheduled('corsivo_theme_verify_activity_storage');
	if ($existing !== false && $existing <= $scheduled_for) {
		return true;
	}
	if ($existing !== false) {
		$unscheduled = wp_unschedule_event($existing, 'corsivo_theme_verify_activity_storage', [], true);
		if (is_wp_error($unscheduled) || $unscheduled === false) {
			return false;
		}
	}

	$scheduled = wp_schedule_single_event(
		$scheduled_for,
		'corsivo_theme_verify_activity_storage',
		[],
		true
	);

	return is_wp_error($scheduled) ? $scheduled : (bool) $scheduled;
}

function corsivo_theme_activity_schema_maintenance($force = false, $fail_closed = false) {
	if (!$force && !corsivo_theme_activity_schema_verification_due()) {
		return true;
	}
	if (!$force && corsivo_theme_activity_schema_failure_is_recent()) {
		return new WP_Error('corsivo_theme_activity_schema_retry_throttled', 'Activity storage verification retry is throttled.');
	}

	$result = corsivo_theme_install_activity_log($fail_closed);
	if (!is_wp_error($result)) {
		wp_clear_scheduled_hook('corsivo_theme_verify_activity_storage');
	}
	$GLOBALS['corsivo_theme_activity_schema_error'] = is_wp_error($result) ? $result : null;

	return $result;
}

function corsivo_theme_activity_schema_verify_admin() {
	if (wp_doing_ajax() || !current_user_can('manage_options')) {
		return;
	}

	corsivo_theme_activity_schema_maintenance();
}

function corsivo_theme_activity_schema_verify_worker() {
	return corsivo_theme_activity_schema_maintenance(true, true);
}

function corsivo_theme_activity_schema_after_plugin_update($upgrader, $options) {
	if (($options['action'] ?? '') !== 'update' || ($options['type'] ?? '') !== 'plugin') {
		return;
	}

	$plugins = array_values(array_filter(array_merge(
		array_map('strval', (array) ($options['plugins'] ?? [])),
		isset($options['plugin']) ? [(string) $options['plugin']] : []
	)));
	if (!in_array(plugin_basename(CORSIVO_CORE_EDITOR_FILE), $plugins, true)) {
		return;
	}

	if (!is_wp_error(corsivo_theme_activity_schema_future_format_error())) {
		corsivo_theme_activity_schema_delete_option(CORSIVO_THEME_ACTIVITY_SCHEMA_VERIFIED_OPTION);
	}
	corsivo_theme_activity_schema_schedule_verification(1);
}

function corsivo_theme_activity_schema_notice() {
	if (!current_user_can('manage_options') || empty($GLOBALS['corsivo_theme_activity_schema_error'])) {
		return;
	}

	echo '<div class="notice notice-error"><p>' . esc_html($GLOBALS['corsivo_theme_activity_schema_error']->get_error_message()) . '</p></div>';
}

add_action('plugins_loaded', 'corsivo_theme_activity_verify_boot', 5);
add_action('admin_init', 'corsivo_theme_activity_schema_verify_admin', 1);
add_action('admin_notices', 'corsivo_theme_activity_schema_notice');
add_action('corsivo_theme_verify_activity_storage', 'corsivo_theme_activity_schema_verify_worker');
add_action('corsivo_theme_cleanup_activity_log', 'corsivo_theme_activity_schema_verify_worker', 1);
add_action('upgrader_process_complete', 'corsivo_theme_activity_schema_after_plugin_update', 20, 2);
