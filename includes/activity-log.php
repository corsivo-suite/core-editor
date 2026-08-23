<?php
if (!defined('ABSPATH')) exit;

function corsivo_theme_schedule_activity_log_cleanup() {
	if (!corsivo_theme_activity_log_schema_is_ready()) {
		return new WP_Error('corsivo_theme_activity_schema_not_ready', 'Activity storage is not available.');
	}
	if (wp_next_scheduled('corsivo_theme_cleanup_activity_log')) {
		return true;
	}

	$scheduled = wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', 'corsivo_theme_cleanup_activity_log', array(), true);

	return is_wp_error($scheduled) ? $scheduled : true;
}

function corsivo_theme_activity_log_enabled() {
	return get_option('corsivo_theme_activity_log_enabled', '1') === '1';
}

function corsivo_theme_activity_log_source() {
	if (defined('WP_CLI') && WP_CLI) return 'cli';
	if (defined('REST_REQUEST') && REST_REQUEST) {
		$request_uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '';
		return strpos($request_uri, '/corsivo/v1/') !== false ? 'rest:corsivo' : 'rest';
	}
	if (wp_doing_ajax()) return 'ajax';
	if (is_admin()) return 'admin';
	return 'frontend';
}

function corsivo_theme_activity_request_id() {
	static $request_id = null;
	if ($request_id === null) {
		$request_id = function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : uniqid('corsivo-', true);
	}
	return $request_id;
}

function corsivo_theme_redact_activity_value($value) {
	if (is_array($value)) {
		foreach ($value as $key => $item) $value[$key] = corsivo_theme_redact_activity_value($item);
		return $value;
	}
	if (!is_string($value)) return $value;
	$search = array(wp_normalize_path(ABSPATH), wp_normalize_path(WP_CONTENT_DIR));
	return str_replace($search, array('[site]/', '[content]/'), wp_normalize_path($value));
}

function corsivo_theme_activity_truncate_string($value, $max_bytes) {
	$value = (string) $value;
	if (strlen($value) <= $max_bytes) return $value;
	$truncated = function_exists('mb_strcut')
		? mb_strcut($value, 0, $max_bytes, 'UTF-8')
		: wp_check_invalid_utf8(substr($value, 0, $max_bytes), true);
	return $truncated . sprintf('… [%d bytes]', strlen($value));
}

function corsivo_theme_activity_detail_keys($value, $limit) {
	return array_map(
		function ($key) {
			return corsivo_theme_activity_truncate_string($key, 96);
		},
		array_slice(array_keys($value), 0, $limit)
	);
}

function corsivo_theme_activity_details_preview($value, $depth = 0) {
	if (is_string($value)) {
		return corsivo_theme_activity_truncate_string($value, 512);
	}

	if (!is_array($value)) {
		return $value;
	}

	if ($depth >= 1) {
		return array(
			'type' => array_is_list($value) ? 'list' : 'object',
			'count' => count($value),
			'keys' => corsivo_theme_activity_detail_keys($value, 30),
		);
	}

	$preview = array();
	foreach (array_slice($value, 0, 20, true) as $key => $item) {
		$preview[$key] = corsivo_theme_activity_details_preview($item, $depth + 1);
	}
	if (count($value) > 20) {
		$preview['_omitted_entries'] = count($value) - 20;
	}

	return $preview;
}

function corsivo_theme_log_activity($event, $status, $summary, $details = array(), $context = array()) {
	$storage = corsivo_theme_activity_require_storage();
	if (!corsivo_theme_activity_log_enabled() || is_wp_error($storage)) {
		return false;
	}

	global $wpdb;

	$table = corsivo_theme_activity_log_table();
	$event = sanitize_key($event);
	$status = in_array($status, array('success', 'warning', 'error'), true) ? $status : 'error';
	$summary = sanitize_text_field(corsivo_theme_redact_activity_value($summary));
	$details = corsivo_theme_redact_activity_value($details);
	$details_json = '';

	if (!empty($details)) {
		$details_json = wp_json_encode($details, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		if (!is_string($details_json)) {
			$details_json = '';
		} elseif (strlen($details_json) > 65535) {
			$original_details_json = $details_json;
			$details_json = wp_json_encode(
				array(
					'truncated' => true,
					'original_bytes' => strlen($original_details_json),
					'original_hash' => hash('sha256', $original_details_json),
					'keys' => array_is_list($details) ? array() : corsivo_theme_activity_detail_keys($details, 50),
					'preview' => corsivo_theme_activity_details_preview($details),
				),
				JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
			);
			if (!is_string($details_json) || strlen($details_json) > 65535) {
				$details_json = wp_json_encode(array(
					'truncated' => true,
					'original_bytes' => strlen($original_details_json),
					'original_hash' => hash('sha256', $original_details_json),
					'keys' => array_is_list($details) ? array() : corsivo_theme_activity_detail_keys($details, 20),
				));
			}
		}
	}

	$result = $wpdb->insert(
		$table,
		array(
			'created_at_gmt' => current_time('mysql', true),
			'request_id'     => corsivo_theme_activity_request_id(),
			'user_id'        => get_current_user_id(),
			'source'         => corsivo_theme_activity_log_source(),
			'event'          => $event,
			'object_type'    => isset($context['object_type']) ? sanitize_key($context['object_type']) : '',
			'object_key'     => isset($context['object_key']) ? sanitize_text_field($context['object_key']) : '',
			'status'         => $status,
			'summary'        => $summary,
			'details'        => $details_json,
			'before_hash'    => isset($context['before_hash']) ? preg_replace('/[^a-f0-9]/', '', strtolower($context['before_hash'])) : '',
			'after_hash'     => isset($context['after_hash']) ? preg_replace('/[^a-f0-9]/', '', strtolower($context['after_hash'])) : '',
		),
		array('%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
	);

	if ($result !== false) {
		try {
			do_action('corsivo_theme_activity_logged', (int) $wpdb->insert_id, $event, $status, $details, $context);
		} catch (Throwable $error) {
			return true;
		}
	}

	return $result !== false;
}

function corsivo_theme_cleanup_activity_log() {
	$storage = corsivo_theme_activity_require_storage();
	if (is_wp_error($storage)) {
		return false;
	}

	global $wpdb;

	$table = corsivo_theme_activity_log_table();
	$retention_days = max(1, min(3650, (int) get_option('corsivo_theme_activity_log_retention_days', 90)));
	$max_entries = max(100, min(100000, (int) get_option('corsivo_theme_activity_log_max_entries', 2000)));
	$cutoff = gmdate('Y-m-d H:i:s', time() - ($retention_days * DAY_IN_SECONDS));

	$threshold = $wpdb->get_var(
		$wpdb->prepare("SELECT id FROM {$table} ORDER BY id DESC LIMIT 1 OFFSET %d", $max_entries - 1)
	);
	if ($wpdb->last_error !== '') {
		return false;
	}
	if ($threshold) {
		return $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE created_at_gmt < %s OR id < %d",
				$cutoff,
				(int) $threshold
			)
		);
	}

	return $wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE created_at_gmt < %s", $cutoff));
}
add_action('corsivo_theme_cleanup_activity_log', 'corsivo_theme_cleanup_activity_log');

function corsivo_theme_activity_log_deleted_user($user_id) {
	$storage = corsivo_theme_activity_require_storage();
	if (is_wp_error($storage)) {
		return false;
	}

	global $wpdb;

	return $wpdb->update(
		corsivo_theme_activity_log_table(),
		array('user_id' => 0),
		array('user_id' => (int) $user_id),
		array('%d'),
		array('%d')
	);
}
add_action('deleted_user', 'corsivo_theme_activity_log_deleted_user');

function corsivo_theme_activity_log_redirect($args = array()) {
	$url = add_query_arg($args, admin_url('options-general.php?page=corsivo-theme-editor'));
	wp_safe_redirect($url . '#history');
	exit;
}

function corsivo_theme_save_activity_log_settings() {
	if (!current_user_can('manage_options')) {
		wp_die(esc_html__('You are not allowed to manage these settings.', 'corsivo-theme-editor'), 403);
	}

	check_admin_referer('corsivo_theme_activity_log_settings');

	$enabled = isset($_POST['activity_log_enabled']) ? '1' : '0';
	$retention_days = isset($_POST['retention_days']) ? absint(wp_unslash($_POST['retention_days'])) : 90;
	$max_entries = isset($_POST['max_entries']) ? absint(wp_unslash($_POST['max_entries'])) : 2000;

	update_option('corsivo_theme_activity_log_enabled', $enabled, false);
	update_option('corsivo_theme_activity_log_retention_days', max(1, min(3650, $retention_days)), false);
	update_option('corsivo_theme_activity_log_max_entries', max(100, min(100000, $max_entries)), false);

	if ($enabled === '1') {
		corsivo_theme_log_activity('log_settings_saved', 'success', 'Activity log settings updated.');
	}

	corsivo_theme_cleanup_activity_log();
	corsivo_theme_activity_log_redirect(array('corsivo-theme-notice' => 'log-settings-saved'));
}
add_action('admin_post_corsivo_theme_save_activity_log_settings', 'corsivo_theme_save_activity_log_settings');

function corsivo_theme_clear_activity_log() {
	if (!current_user_can('manage_options')) {
		wp_die(esc_html__('You are not allowed to clear the activity log.', 'corsivo-theme-editor'), 403);
	}

	check_admin_referer('corsivo_theme_clear_activity_log');
	$storage = corsivo_theme_activity_require_storage();
	if (is_wp_error($storage)) {
		corsivo_theme_activity_log_redirect(array('corsivo-theme-notice' => 'activity-storage-unavailable'));
	}

	global $wpdb;
	$table = corsivo_theme_activity_log_table();
	$deleted = $wpdb->query("DELETE FROM {$table}");
	if ($deleted === false) {
		corsivo_theme_activity_log_redirect(array('corsivo-theme-notice' => 'activity-storage-unavailable'));
	}
	corsivo_theme_log_activity('log_cleared', 'success', 'Activity log cleared.');
	corsivo_theme_activity_log_redirect(array('corsivo-theme-notice' => 'log-cleared'));
}
add_action('admin_post_corsivo_theme_clear_activity_log', 'corsivo_theme_clear_activity_log');

function corsivo_theme_activity_log_render() {
	if (!current_user_can('manage_options')) {
		return;
	}
	if (!corsivo_theme_activity_log_schema_is_ready()) {
		echo '<div class="notice notice-error"><p>' . esc_html__('Activity storage is not available.', 'corsivo-theme-editor') . '</p></div>';
		return;
	}

	global $wpdb;

	$table = corsivo_theme_activity_log_table();
	$current_page = isset($_GET['log_page']) ? max(1, absint(wp_unslash($_GET['log_page']))) : 1;
	$status = isset($_GET['log_status']) ? sanitize_key(wp_unslash($_GET['log_status'])) : '';
	$event = isset($_GET['log_event']) ? sanitize_key(wp_unslash($_GET['log_event'])) : '';
	$per_page = 50;
	$where = array('1=1');
	$params = array();

	if (in_array($status, array('success', 'warning', 'error'), true)) {
		$where[] = 'status = %s';
		$params[] = $status;
	}
	if ($event !== '') {
		$where[] = 'event = %s';
		$params[] = $event;
	}

	$where_sql = implode(' AND ', $where);
	$count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
	if (!empty($params)) {
		$count_sql = $wpdb->prepare($count_sql, $params);
	}
	$total = (int) $wpdb->get_var($count_sql);

	$query_params = $params;
	$query_params[] = $per_page;
	$query_params[] = ($current_page - 1) * $per_page;
	$rows_sql = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY id DESC LIMIT %d OFFSET %d";
	$rows = $wpdb->get_results($wpdb->prepare($rows_sql, $query_params));
	$events = $wpdb->get_col("SELECT DISTINCT event FROM {$table} ORDER BY event ASC");
	$total_pages = max(1, (int) ceil($total / $per_page));
	?>
	<div class="wrap corsivo-theme-component">
		<h2>Activity</h2>
		<form method="get" class="corsivo-theme-log-filters">
			<input type="hidden" name="page" value="corsivo-theme-editor">
			<input type="hidden" name="section" value="history">
			<label for="corsivo-theme-log-event">Action</label>
			<select id="corsivo-theme-log-event" name="log_event">
				<option value="">All</option>
				<?php foreach ($events as $event_name): ?>
					<option value="<?= esc_attr($event_name) ?>" <?php selected($event, $event_name); ?>><?= esc_html($event_name) ?></option>
				<?php endforeach; ?>
			</select>
			<label for="corsivo-theme-log-status">Status</label>
			<select id="corsivo-theme-log-status" name="log_status">
				<option value="">All</option>
				<option value="success" <?php selected($status, 'success'); ?>>Success</option>
				<option value="warning" <?php selected($status, 'warning'); ?>>Warning</option>
				<option value="error" <?php selected($status, 'error'); ?>>Error</option>
			</select>
			<button type="submit" class="button">Filter</button>
		</form>

		<div class="corsivo-theme-table-scroll">
			<table class="widefat striped corsivo-theme-log-table">
				<thead><tr><th>Date</th><th>User</th><th>Action</th><th>Status</th><th>Summary</th></tr></thead>
				<tbody>
				<?php if (empty($rows)): ?>
					<tr><td colspan="5">No activity recorded.</td></tr>
				<?php else: ?>
					<?php foreach ($rows as $row): ?>
						<?php $user = $row->user_id ? get_userdata((int) $row->user_id) : false; ?>
						<tr>
							<td><?= esc_html(get_date_from_gmt($row->created_at_gmt, 'Y-m-d H:i:s')) ?></td>
							<td><?= esc_html($user ? $user->display_name : 'System') ?></td>
							<td><code><?= esc_html($row->event) ?></code></td>
							<td><span class="corsivo-theme-log-status corsivo-theme-log-status--<?= esc_attr($row->status) ?>"><?= esc_html($row->status) ?></span></td>
							<td>
								<?= esc_html($row->summary) ?>
								<?php if ($row->details): ?>
									<details><summary>Details</summary><pre><?= esc_html(wp_json_encode(json_decode($row->details, true), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?></pre></details>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
				</tbody>
			</table>
		</div>

		<?php if ($total_pages > 1): ?>
			<div class="tablenav"><div class="tablenav-pages">
				<?= wp_kses_post(paginate_links(array(
					'base' => add_query_arg(array('page' => 'corsivo-theme-editor', 'log_event' => $event, 'log_status' => $status, 'log_page' => '%#%'), admin_url('options-general.php')) . '#history',
					'format' => '',
					'current' => $current_page,
					'total' => $total_pages,
				))) ?>
			</div></div>
		<?php endif; ?>

		<hr>
		<h3>Retention</h3>
		<form method="post" action="<?= esc_url(admin_url('admin-post.php')) ?>">
			<input type="hidden" name="action" value="corsivo_theme_save_activity_log_settings">
			<?php wp_nonce_field('corsivo_theme_activity_log_settings'); ?>
			<label><input type="checkbox" name="activity_log_enabled" value="1" <?php checked(corsivo_theme_activity_log_enabled()); ?>> Enable activity log</label>
			<label>Days <input type="number" min="1" max="3650" name="retention_days" value="<?= esc_attr((int) get_option('corsivo_theme_activity_log_retention_days', 90)) ?>"></label>
			<label>Maximum entries <input type="number" min="100" max="100000" name="max_entries" value="<?= esc_attr((int) get_option('corsivo_theme_activity_log_max_entries', 2000)) ?>"></label>
			<button type="submit" class="button button-primary">Save log settings</button>
		</form>
		<form method="post" action="<?= esc_url(admin_url('admin-post.php')) ?>" class="corsivo-theme-log-clear" onsubmit="return window.confirm('Clear the complete activity log?');">
			<input type="hidden" name="action" value="corsivo_theme_clear_activity_log">
			<?php wp_nonce_field('corsivo_theme_clear_activity_log'); ?>
			<button type="submit" class="button">Clear log</button>
		</form>
	</div>
	<?php
}
