<?php

if ( PHP_SAPI !== 'cli' ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'CORSIVO_CORE_EDITOR_DIR', dirname( __DIR__ ) . '/' );
define( 'CORSIVO_CORE_EDITOR_FILE', CORSIVO_CORE_EDITOR_DIR . 'corsivo-core-editor.php' );

$actions = array();
$options = array();
$option_autoload = array();
$styles = array();
$shortcodes = array();
$can_manage = true;
$nonce_valid = true;
$legacy_loaded = false;
$redirect = '';
$assertions = 0;

function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	$GLOBALS['actions'][ $hook ][ $priority ][ $callback ] = $accepted_args;
}

function add_filter( ...$arguments ) {
	add_action( ...$arguments );
}

function remove_action( $hook, $callback, $priority = 10 ) {
	unset( $GLOBALS['actions'][ $hook ][ $priority ][ $callback ] );
}

function do_action( $hook, ...$arguments ) {
	ksort( $GLOBALS['actions'][ $hook ] );

	foreach ( array_keys( $GLOBALS['actions'][ $hook ] ) as $priority ) {
		foreach ( $GLOBALS['actions'][ $hook ][ $priority ] ?? array() as $callback => $accepted_args ) {
			$callback( ...array_slice( $arguments, 0, $accepted_args ) );
		}
	}
}

function apply_filters( $hook, $value ) {
	return $value;
}

function add_shortcode( $name, $callback ) {
	$GLOBALS['shortcodes'][ $name ] = $callback;
}

function get_option( $name, $default = false ) {
	return array_key_exists( $name, $GLOBALS['options'] ) ? $GLOBALS['options'][ $name ] : $default;
}

function add_option( $name, $value, $deprecated = '', $autoload = null ) {
	if ( array_key_exists( $name, $GLOBALS['options'] ) ) {
		return false;
	}

	$GLOBALS['options'][ $name ] = $value;
	$GLOBALS['option_autoload'][ $name ] = $autoload;
	return true;
}

function update_option( $name, $value, $autoload = null ) {
	$GLOBALS['options'][ $name ] = $value;
	$GLOBALS['option_autoload'][ $name ] = $autoload;
	return true;
}

function get_file_data( $file, $headers ) {
	$contents = file_get_contents( $file );

	foreach ( $headers as $key => $header ) {
		preg_match( '/^[\s\/*#@]*' . preg_quote( $header, '/' ) . ':(.*)$/mi', $contents, $match );
		$headers[ $key ] = trim( $match[1] ?? '' );
	}

	return $headers;
}

function wp_normalize_path( $path ) {
	return str_replace( '\\', '/', $path );
}

function sanitize_key( $value ) {
	return preg_replace( '/[^a-z0-9_-]/', '', strtolower( $value ) );
}

function current_user_can( $capability ) {
	return $GLOBALS['can_manage'];
}

function __( $text, $domain = '' ) {
	return $text;
}

function esc_html__( $text, $domain = '' ) {
	return htmlspecialchars( $text, ENT_QUOTES );
}

function wp_die( $message, $title = '', $arguments = array() ) {
	throw new RuntimeException( $message, $arguments['response'] ?? 403 );
}

function check_admin_referer( $action, $name ) {
	if ( ! $GLOBALS['nonce_valid'] ) {
		throw new RuntimeException( 'Invalid nonce', 403 );
	}
}

function wp_unslash( $value ) {
	return is_array( $value ) ? array_map( 'wp_unslash', $value ) : ( is_string( $value ) ? stripslashes( $value ) : $value );
}

function admin_url( $path ) {
	return 'https://example.test/wp-admin/' . $path;
}

function plugin_dir_url( $file ) {
	return 'https://example.test/wp-content/plugins/corsivo-core-editor/';
}

function wp_enqueue_style( $handle, $source, $dependencies = array(), $version = null ) {
	$GLOBALS['styles'][ $handle ] = $source;
}

function wp_safe_redirect( $destination ) {
	$GLOBALS['redirect'] = $destination;
	throw new RuntimeException( 'Redirect', 302 );
}

function corsivo_core_toolkit_assert( $condition, $message ) {
	$GLOBALS['assertions']++;

	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

function corsivo_core_toolkit_expect_error( $callback, $code, $message ) {
	try {
		$callback();
	} catch ( RuntimeException $error ) {
		corsivo_core_toolkit_assert( $code === $error->getCode(), $message );
		return;
	}

	throw new RuntimeException( $message );
}

function corsivo_core_toolkit_legacy_fixture() {
	function corsivo_toolkit_get_snippets() {
		return array( 'legacy-registry' );
	}

	function corsivo_toolkit_load_enabled_snippets() {
		$GLOBALS['legacy_loaded'] = true;
	}

	function corsivo_toolkit_add_settings_page() {}
	function corsivo_toolkit_hide_settings_menu_for_non_developers() {}
	function corsivo_toolkit_enqueue_admin_assets() {}
	function corsivo_toolkit_save_settings() {}
	add_action( 'plugins_loaded', 'corsivo_toolkit_load_enabled_snippets' );
	add_action( 'admin_menu', 'corsivo_toolkit_add_settings_page' );
	add_action( 'admin_menu', 'corsivo_toolkit_hide_settings_menu_for_non_developers', PHP_INT_MAX );
	add_action( 'admin_enqueue_scripts', 'corsivo_toolkit_enqueue_admin_assets' );
	add_action( 'admin_init', 'corsivo_toolkit_save_settings' );
}

$scenario = $argv[1] ?? '';

if ( '' === $scenario ) {
	foreach ( array( 'migration', 'disabled', 'legacy-first', 'core-first', 'collisions', 'save' ) as $scenario_name ) {
		$process = proc_open( array( PHP_BINARY, __FILE__, $scenario_name ), array( 0 => STDIN, 1 => STDOUT, 2 => STDERR ), $pipes );

		if ( ! is_resource( $process ) || 0 !== proc_close( $process ) ) {
			exit( 1 );
		}
	}

	exit;
}

if ( 'legacy-first' === $scenario ) {
	corsivo_core_toolkit_legacy_fixture();
}

require CORSIVO_CORE_EDITOR_DIR . 'includes/toolkit.php';
require CORSIVO_CORE_EDITOR_DIR . 'includes/admin/toolkit.php';

if ( 'core-first' === $scenario ) {
	corsivo_core_toolkit_legacy_fixture();
}

$all_modules = array( 'disable-comments', 'grid', 'svg-upload', 'woocommerce-gutenberg', 'woocommerce-notices' );
$registered = array_keys( corsivo_core_toolkit_get_modules() );
sort( $registered );
corsivo_core_toolkit_assert( $registered === $all_modules, 'All five local modules are registered.' );

if ( 'migration' === $scenario ) {
	$options['corsivo_toolkit_enabled_snippets'] = array( 'grid', 'svg-upload', 'grid', 'missing', array( 'disable-comments' ), null, 5 );
	corsivo_core_toolkit_assert( array( 'grid', 'svg-upload' ) === corsivo_core_toolkit_get_enabled_modules(), 'Migration sanitizes and imports only known module slugs.' );
	corsivo_core_toolkit_assert( false === $option_autoload['corsivo_core_enabled_modules'], 'The per-site option is not autoloaded.' );
	$options['corsivo_toolkit_enabled_snippets'] = $all_modules;
	corsivo_core_toolkit_assert( array( 'grid', 'svg-upload' ) === corsivo_core_toolkit_get_enabled_modules(), 'Changing legacy selection never overwrites a migrated option.' );
	$options['corsivo_core_enabled_modules'] = array();
	corsivo_core_toolkit_assert( array() === corsivo_core_toolkit_get_enabled_modules(), 'An explicit empty selection remains empty.' );
	$options['corsivo_core_enabled_modules'] = false;
	corsivo_core_toolkit_assert( array() === corsivo_core_toolkit_get_enabled_modules(), 'A malformed existing option never triggers legacy migration.' );
	corsivo_core_toolkit_assert( array() === corsivo_core_toolkit_sanitize_modules( 'grid' ), 'Scalar selection is rejected.' );
}

if ( 'disabled' === $scenario ) {
	$options['corsivo_core_enabled_modules'] = array();
	$options['corsivo_toolkit_enabled_snippets'] = $all_modules;
	do_action( 'plugins_loaded' );
	corsivo_core_toolkit_assert( ! function_exists( 'corsivo_toolkit_render_grid' ), 'Disabled Grid is not included.' );
	corsivo_core_toolkit_assert( ! function_exists( 'corsivo_toolkit_svg_validate_upload' ), 'Disabled SVG Upload is not included.' );
	corsivo_core_toolkit_assert( ! function_exists( 'corsivo_toolkit_disable_comments_post_type_support' ), 'Disabled Comments is not included.' );
	corsivo_core_toolkit_assert( ! function_exists( 'corsivo_toolkit_woocommerce_gutenberg_use_block_editor' ), 'Disabled WooCommerce Gutenberg is not included.' );
	corsivo_core_toolkit_assert( ! function_exists( 'corsivo_toolkit_woocommerce_notices_render' ), 'Disabled WooCommerce Notices is not included.' );
	corsivo_core_toolkit_assert( array() === $shortcodes, 'Disabled modules register no shortcode.' );
}

if ( in_array( $scenario, array( 'core-first', 'legacy-first' ), true ) ) {
	$options['corsivo_toolkit_enabled_snippets'] = $all_modules;
	do_action( 'plugins_loaded' );
	corsivo_core_toolkit_assert( ! $legacy_loaded, 'The original legacy loader is suppressed regardless of plugin order.' );
	corsivo_core_toolkit_assert( array( 'legacy-registry' ) === corsivo_toolkit_get_snippets(), 'The pre-existing public legacy registry is not redefined.' );
	corsivo_core_toolkit_assert( $all_modules === array_values( array_intersect( $all_modules, corsivo_core_toolkit_get_enabled_modules() ) ), 'Every legacy module is migrated.' );
	corsivo_core_toolkit_assert( function_exists( 'corsivo_toolkit_render_grid' ) && function_exists( 'corsivo_toolkit_get_grid' ), 'Both public Grid APIs remain available.' );
	corsivo_core_toolkit_assert( 'corsivo_toolkit_grid_shortcode' === $shortcodes['corsivo_grid'], 'The existing shortcode is preserved.' );

	foreach ( array( 'admin_menu' => array( 'corsivo_toolkit_add_settings_page', 'corsivo_toolkit_hide_settings_menu_for_non_developers' ), 'admin_enqueue_scripts' => array( 'corsivo_toolkit_enqueue_admin_assets' ), 'admin_init' => array( 'corsivo_toolkit_save_settings' ) ) as $hook => $callbacks ) {
		foreach ( $actions[ $hook ] ?? array() as $registered_callbacks ) {
			corsivo_core_toolkit_assert( array() === array_intersect( $callbacks, array_keys( $registered_callbacks ) ), 'Legacy UI and save callbacks are removed.' );
		}
	}

	corsivo_toolkit_grid_enqueue_styles();
	corsivo_core_toolkit_assert( str_contains( $styles['corsivo-toolkit-grid'], '/corsivo-core-editor/toolkit/grid/' ), 'Frontend assets use the Core plugin URL.' );
}

if ( 'collisions' === $scenario ) {
	function corsivo_toolkit_svg_sanitize( $contents ) { return 'legacy'; }
	$options['corsivo_core_enabled_modules'] = $all_modules;
	do_action( 'plugins_loaded' );
	foreach ( corsivo_core_toolkit_get_modules() as $module ) {
		include $module['file'];
	}
	corsivo_core_toolkit_assert( 'legacy' === corsivo_toolkit_svg_sanitize( '' ), 'A pre-existing sanitizer API is not redefined.' );
	corsivo_core_toolkit_assert( false === corsivo_core_svg_sanitize( '' ), 'Core uploads use the bundled sanitizer even if a legacy API exists.' );
	corsivo_core_toolkit_assert( function_exists( 'corsivo_toolkit_render_grid' ), 'Module includes remain safe when their public functions already exist.' );
}

if ( 'save' === $scenario ) {
	$options['corsivo_core_enabled_modules'] = array( 'grid' );
	$_SERVER['REQUEST_METHOD'] = 'POST';
	$_POST['corsivo_core_modules'] = array( 'svg-upload' );
	$can_manage = false;
	corsivo_core_toolkit_expect_error( 'corsivo_core_toolkit_save', 403, 'The save endpoint requires manage_options.' );
	$can_manage = true;
	$nonce_valid = false;
	corsivo_core_toolkit_expect_error( 'corsivo_core_toolkit_save', 403, 'The save endpoint validates its nonce.' );
	$nonce_valid = true;
	$_SERVER['REQUEST_METHOD'] = 'GET';
	corsivo_core_toolkit_expect_error( 'corsivo_core_toolkit_save', 405, 'GET cannot mutate module options.' );
	$_SERVER['REQUEST_METHOD'] = 'POST';
	$_POST['corsivo_core_modules'] = 'svg-upload';
	corsivo_core_toolkit_expect_error( 'corsivo_core_toolkit_save', 400, 'Malformed scalar input is rejected.' );
	corsivo_core_toolkit_assert( array( 'grid' ) === $options['corsivo_core_enabled_modules'], 'Rejected saves leave the current selection intact.' );
	$_POST['corsivo_core_modules'] = array( 'svg-upload', array( 'grid' ), 42, 'missing', 'svg-upload' );
	corsivo_core_toolkit_expect_error( 'corsivo_core_toolkit_save', 302, 'Valid POST saves and redirects.' );
	corsivo_core_toolkit_assert( array( 'svg-upload' ) === $options['corsivo_core_enabled_modules'], 'Only valid unique module identifiers are persisted.' );
	corsivo_core_toolkit_assert( false === $option_autoload['corsivo_core_enabled_modules'], 'Saves preserve the non-autoload option.' );
	corsivo_core_toolkit_assert( str_ends_with( $redirect, 'page=corsivo-core-editor&toolkit-updated=true#toolkit' ), 'Saves return to the Core Toolkit tab.' );
	unset( $_POST['corsivo_core_modules'] );
	corsivo_core_toolkit_expect_error( 'corsivo_core_toolkit_save', 302, 'An omitted checkbox selection saves successfully.' );
	corsivo_core_toolkit_assert( array() === $options['corsivo_core_enabled_modules'], 'All modules can be disabled explicitly.' );
}

echo 'PASS Toolkit ' . $scenario . ' (' . $assertions . ' assertions)' . PHP_EOL;
