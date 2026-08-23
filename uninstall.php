<?php

if (!defined('WP_UNINSTALL_PLUGIN')) {
	exit;
}

function corsivo_theme_uninstall_activity_storage() {
	global $wpdb;

	wp_clear_scheduled_hook('corsivo_theme_cleanup_activity_log');
	wp_clear_scheduled_hook('corsivo_theme_verify_activity_storage');

	$table = $wpdb->prefix . 'corsivo_theme_activity_log';
	if (preg_match('/^[A-Za-z0-9_]+$/D', $table)) {
		$wpdb->query("DROP TABLE `{$table}`");
	}

	delete_option('corsivo_theme_activity_schema_intent');
	delete_option('corsivo_theme_activity_schema_verified');
	delete_option('corsivo_theme_activity_schema_failure');
	delete_option('corsivo_theme_activity_log_enabled');
	delete_option('corsivo_theme_activity_log_retention_days');
	delete_option('corsivo_theme_activity_log_max_entries');
}

if (!is_multisite()) {
	corsivo_theme_uninstall_activity_storage();
	return;
}

$offset = 0;
do {
	$site_ids = get_sites([
		'fields' => 'ids',
		'number' => 100,
		'offset' => $offset,
	]);
	foreach ($site_ids as $site_id) {
		switch_to_blog((int) $site_id);
		corsivo_theme_uninstall_activity_storage();
		restore_current_blog();
	}
	$offset += count($site_ids);
} while (count($site_ids) === 100);
