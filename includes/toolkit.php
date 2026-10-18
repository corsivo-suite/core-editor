<?php

defined( 'ABSPATH' ) || exit;

function corsivo_core_toolkit_log( $message ) {
	if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		error_log( 'Corsivo Core Editor: ' . $message );
	}
}

function corsivo_core_toolkit_get_module_files() {
	$directory = CORSIVO_CORE_EDITOR_DIR . 'toolkit/';
	$files = glob( $directory . '*.php' ) ?: array();
	$directories = glob( $directory . '*', GLOB_ONLYDIR ) ?: array();

	foreach ( $directories as $module_directory ) {
		foreach ( array( 'index.php', 'snippet.php', basename( $module_directory ) . '.php' ) as $filename ) {
			$file = $module_directory . DIRECTORY_SEPARATOR . $filename;

			if ( is_file( $file ) ) {
				$files[] = $file;
				break;
			}
		}
	}

	sort( $files );
	return $files;
}

function corsivo_core_toolkit_get_modules() {
	static $registered = null;

	if ( null === $registered ) {
		$registered = array();

		foreach ( corsivo_core_toolkit_get_module_files() as $file ) {
			$headers = get_file_data( $file, array( 'name' => 'Name', 'description' => 'Description', 'category' => 'Category' ) );

			if ( empty( $headers['name'] ) ) {
				continue;
			}

			$relative = ltrim( str_replace( wp_normalize_path( CORSIVO_CORE_EDITOR_DIR . 'toolkit/' ), '', wp_normalize_path( $file ) ), '/' );
			$parts = explode( '/', $relative );
			$slug = sanitize_key( count( $parts ) > 1 ? $parts[0] : pathinfo( $parts[0], PATHINFO_FILENAME ) );

			if ( isset( $registered[ $slug ] ) ) {
				corsivo_core_toolkit_log( sprintf( 'Module "%s" already registered, skipping %s.', $slug, $file ) );
				continue;
			}

			$registered[ $slug ] = array_merge( $headers, array( 'slug' => $slug, 'file' => $file ) );
		}

		uasort( $registered, static fn( $first, $second ) => strcasecmp( $first['name'], $second['name'] ) );
	}

	$modules = apply_filters( 'corsivo_toolkit_snippets', $registered );
	$modules = apply_filters( 'corsivo_core_toolkit_modules', $modules );

	if ( ! is_array( $modules ) ) {
		return array();
	}

	foreach ( $modules as $slug => $module ) {
		if ( ! is_string( $slug ) || ! is_array( $module ) || empty( $module['name'] ) || empty( $module['file'] ) ) {
			unset( $modules[ $slug ] );
			continue;
		}

		$modules[ $slug ]['slug'] = $slug;
		$modules[ $slug ]['description'] = $module['description'] ?? '';
		$modules[ $slug ]['category'] = empty( $module['category'] ) ? 'General' : $module['category'];
	}

	return $modules;
}

function corsivo_core_toolkit_sanitize_modules( $requested ) {
	if ( ! is_array( $requested ) ) {
		return array();
	}

	$slugs = array_map( 'sanitize_key', array_filter( $requested, 'is_string' ) );
	$available = array_keys( corsivo_core_toolkit_get_modules() );
	return array_values( array_unique( array_intersect( $available, $slugs ) ) );
}

function corsivo_core_toolkit_get_enabled_modules() {
	$missing = new stdClass();
	$enabled = get_option( 'corsivo_core_enabled_modules', $missing );

	if ( $missing === $enabled ) {
		$legacy = get_option( 'corsivo_toolkit_enabled_snippets', array() );
		add_option( 'corsivo_core_enabled_modules', corsivo_core_toolkit_sanitize_modules( $legacy ), '', false );
		$enabled = get_option( 'corsivo_core_enabled_modules', array() );
	}

	return corsivo_core_toolkit_sanitize_modules( $enabled );
}

function corsivo_core_toolkit_is_module_enabled( $slug ) {
	return is_string( $slug ) && in_array( sanitize_key( $slug ), corsivo_core_toolkit_get_enabled_modules(), true );
}

function corsivo_core_toolkit_suppress_legacy() {
	remove_action( 'plugins_loaded', 'corsivo_toolkit_load_enabled_snippets' );
	remove_action( 'admin_menu', 'corsivo_toolkit_add_settings_page' );
	remove_action( 'admin_menu', 'corsivo_toolkit_hide_settings_menu_for_non_developers', PHP_INT_MAX );
	remove_action( 'admin_enqueue_scripts', 'corsivo_toolkit_enqueue_admin_assets' );
	remove_action( 'admin_init', 'corsivo_toolkit_save_settings' );
}
add_action( 'plugins_loaded', 'corsivo_core_toolkit_suppress_legacy', PHP_INT_MIN );

function corsivo_core_toolkit_load_enabled_modules() {
	$modules = corsivo_core_toolkit_get_modules();

	foreach ( corsivo_core_toolkit_get_enabled_modules() as $slug ) {
		$file = $modules[ $slug ]['file'] ?? '';

		if ( ! is_string( $file ) || ! is_file( $file ) ) {
			corsivo_core_toolkit_log( sprintf( 'Enabled module "%s" has no loadable file.', $slug ) );
			continue;
		}

		require_once $file;
	}
}
add_action( 'plugins_loaded', 'corsivo_core_toolkit_load_enabled_modules' );
