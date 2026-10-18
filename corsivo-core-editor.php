<?php
	/**
		* @wordpress-plugin
		* Plugin Name:       Corsivo Core Editor
		* Description:       Configure WordPress features, optional site modules and the theme design system from one administration page.
		* Version:           2.1.1
		* Author:            Salvatore Corsi
		* Author URI:        https://salvatorecorsi.com
		* Plugin URI:        https://github.com/corsivo-suite/core-editor
		* License:           GPL-2.0-or-later
		* License URI:       https://www.gnu.org/licenses/gpl-2.0.html
		* Text Domain:       corsivo-core-editor
		* Requires at least: 6.6
		* Requires PHP:      8.1
		* Update URI:        https://www.corsivo.dev/plugins/corsivo-core-editor
	*/

	if (!defined('ABSPATH')) exit;

	define('CORSIVO_CORE_EDITOR_VERSION', '2.1.1');
	define('CORSIVO_CORE_EDITOR_FILE', __FILE__);
	define('CORSIVO_CORE_EDITOR_DIR', plugin_dir_path(__FILE__));

	require_once __DIR__ . '/includes/wordpress-features.php';
	require_once __DIR__ . '/includes/toolkit.php';
	if (is_admin()) {
		require_once __DIR__ . '/includes/admin/wordpress-features.php';
		require_once __DIR__ . '/includes/admin/toolkit.php';
	}

	require_once __DIR__ . '/includes/activity-storage.php';
	require_once __DIR__ . '/includes/activity-log.php';
	require_once __DIR__ . '/includes/admin/settings.php';
	require_once __DIR__ . '/includes/integrations.php';
	require_once __DIR__ . '/includes/functions.php';
	require_once __DIR__ . '/includes/rest-api.php';
	require_once __DIR__ . '/includes/rest-api-configurator.php';
	require_once __DIR__ . '/includes/admin/pages-status.php';
	require_once __DIR__ . '/includes/settings.php';
	require_once __DIR__ . '/includes/actions.php';

	function corsivo_theme_activate_site() {
		$result = corsivo_theme_install_activity_log();
		if (is_wp_error($result)) return $result;

		$scheduled = corsivo_theme_schedule_activity_log_cleanup();
		if (is_wp_error($scheduled)) return $scheduled;

		return true;
	}

	function corsivo_theme_activate($network_wide = false) {
		if (!$network_wide || !is_multisite()) {
			$result = corsivo_theme_activate_site();
			if (is_wp_error($result)) {
				wp_die(esc_html($result->get_error_message()));
			}
			return;
		}

		$offset = 0;
		do {
			$site_ids = get_sites(array(
				'fields' => 'ids',
				'number' => 100,
				'offset' => $offset,
			));
			foreach ($site_ids as $site_id) {
				switch_to_blog((int) $site_id);
				try {
					$result = corsivo_theme_activate_site();
				} finally {
					restore_current_blog();
				}
				if (is_wp_error($result)) {
					wp_die(esc_html($result->get_error_message()));
				}
			}
			$offset += count($site_ids);
		} while (count($site_ids) === 100);
	}
	register_activation_hook(__FILE__, 'corsivo_theme_activate');

	function corsivo_theme_deactivate($network_wide = false) {
		if (!$network_wide || !is_multisite()) {
			wp_clear_scheduled_hook('corsivo_theme_cleanup_activity_log');
			wp_clear_scheduled_hook('corsivo_theme_verify_activity_storage');
			return;
		}

		$offset = 0;
		do {
			$site_ids = get_sites(array(
				'fields' => 'ids',
				'number' => 100,
				'offset' => $offset,
			));
			foreach ($site_ids as $site_id) {
				switch_to_blog((int) $site_id);
				try {
					wp_clear_scheduled_hook('corsivo_theme_cleanup_activity_log');
					wp_clear_scheduled_hook('corsivo_theme_verify_activity_storage');
				} finally {
					restore_current_blog();
				}
			}
			$offset += count($site_ids);
		} while (count($site_ids) === 100);
	}
	register_deactivation_hook(__FILE__, 'corsivo_theme_deactivate');

	add_action('plugins_loaded', 'corsivo_theme_schedule_activity_log_cleanup', 20);

	// Core block render helpers — loaded only when enabled in Settings
	add_action('after_setup_theme', function() {
		$config = corsivo_theme_get_settings();
		$global = isset($config['global']) ? $config['global'] : array();
		if ( ! empty($global['core_renders']) ) {
			require_once __DIR__ . '/includes/core-renders.php';
		}
	});

	/**
	 * Render the Gutenberg footer content.
	 * Use in footer.php: <?php if (function_exists('corsivo_theme_render_footer')) corsivo_theme_render_footer(); ?>
	 */
	function corsivo_theme_render_footer() {
		$config = corsivo_theme_get_settings();
		$global = isset($config['global']) ? $config['global'] : array();
		if ( empty($global['gutenberg_footer']) ) return;

		$page_id = get_option( 'corsivo_theme_footer_page_id' );
		if ( ! $page_id ) return;

		$page = get_post( $page_id );
		if ( ! $page || $page->post_status !== 'private' ) return;

		echo apply_filters( 'the_content', $page->post_content );
	}

	function corsivo_theme_sanitize_footer_color($color) {
		if (!is_string($color)) return '';
		$color = trim($color);
		if (preg_match('/^#(?:[a-f0-9]{3}|[a-f0-9]{4}|[a-f0-9]{6}|[a-f0-9]{8})$/iD', $color)) return strtolower($color);
		if ($color === 'transparent') return $color;
		if (!preg_match('/^(rgba?)\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})(?:\s*,\s*(\d*\.?\d+))?\s*\)$/iD', $color, $channels)) return '';
		if (max((int) $channels[2], (int) $channels[3], (int) $channels[4]) > 255) return '';
		$has_alpha = isset($channels[5]);
		if ((strtolower($channels[1]) === 'rgba') !== $has_alpha) return '';
		if ($has_alpha && (float) $channels[5] > 1) return '';
		return strtolower($channels[1]) . '(' . implode(', ', array_slice($channels, 2)) . ')';
	}

	// Hide footer page from admin pages list
	add_action( 'pre_get_posts', function ( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() ) return;
		if ( $query->get( 'post_type' ) !== 'page' ) return;
		$footer_id = get_option( 'corsivo_theme_footer_page_id' );
		if ( $footer_id ) {
			$query->set( 'post__not_in', array_merge( $query->get( 'post__not_in' ) ?: array(), array( (int) $footer_id ) ) );
		}
	} );

	// Add "Footer" link under Appearance menu
	add_action( 'admin_menu', function () {
		$config = corsivo_theme_get_settings();
		$global = isset($config['global']) ? $config['global'] : array();
		if ( empty($global['gutenberg_footer']) ) return;

		$footer_id = get_option( 'corsivo_theme_footer_page_id' );
		if ( ! $footer_id ) return;

		$hook = add_theme_page(
			'Footer',
			'Footer',
			'edit_pages',
			'corsivo-theme-footer-edit',
			'__return_empty_string'
		);
		add_action( 'load-' . $hook, function () use ( $footer_id ) {
			wp_safe_redirect( get_edit_post_link( $footer_id, 'raw' ) );
			exit;
		} );
	} );

	// Footer: inject background CSS into global styles
	add_action( 'wp_enqueue_scripts', function () {
		$config = corsivo_theme_get_settings();
		$global = isset($config['global']) ? $config['global'] : array();
		if ( empty($global['gutenberg_footer']) ) return;

		$footer_id = get_option( 'corsivo_theme_footer_page_id' );
		if ( ! $footer_id ) return;

		$bg_slug    = sanitize_title( get_post_meta( $footer_id, '_corsivo_theme_footer_bg_slug', true ) );
		$bg_hex     = corsivo_theme_sanitize_footer_color( get_post_meta( $footer_id, '_corsivo_theme_footer_bg', true ) );
		$color_slug = sanitize_title( get_post_meta( $footer_id, '_corsivo_theme_footer_color_slug', true ) );
		$color_hex  = corsivo_theme_sanitize_footer_color( get_post_meta( $footer_id, '_corsivo_theme_footer_color', true ) );

		if ( ! $bg_slug && ! $bg_hex && ! $color_slug && ! $color_hex ) return;

		$css = '.site-footer {';
		if ( $bg_slug || $bg_hex ) {
			$bg = $bg_slug ? 'var(--wp--preset--color--' . $bg_slug . ')' : $bg_hex;
			$css .= ' background-color: ' . $bg . ';';
		}
		if ( $color_slug || $color_hex ) {
			$color = $color_slug ? 'var(--wp--preset--color--' . $color_slug . ')' : $color_hex;
			$css .= ' color: ' . $color . ';';
		}
		$css .= ' }';

		wp_register_style( 'corsivo-theme-footer-styles', false );
		wp_enqueue_style( 'corsivo-theme-footer-styles' );
		wp_add_inline_style( 'corsivo-theme-footer-styles', $css );
	}, 100 );

	// Footer page: register bg color meta
	add_action( 'init', function () {
		$meta_args = array(
			'show_in_rest'  => true,
			'single'        => true,
			'type'          => 'string',
			'default'       => '',
			'auth_callback' => function ( $allowed, $meta_key, $post_id ) { return current_user_can( 'edit_post', $post_id ); },
		);
		$hex_args = array_merge($meta_args, array('sanitize_callback' => 'corsivo_theme_sanitize_footer_color'));
		$slug_args = array_merge($meta_args, array('sanitize_callback' => 'sanitize_title'));
		register_post_meta( 'page', '_corsivo_theme_footer_bg', $hex_args );
		register_post_meta( 'page', '_corsivo_theme_footer_bg_slug', $slug_args );
		register_post_meta( 'page', '_corsivo_theme_footer_color', $hex_args );
		register_post_meta( 'page', '_corsivo_theme_footer_color_slug', $slug_args );
	} );

	// Footer page: Gutenberg sidebar (color picker + hide title)
	add_action( 'enqueue_block_editor_assets', function () {
		$footer_id = get_option( 'corsivo_theme_footer_page_id' );
		if ( ! $footer_id ) return;
		$screen = get_current_screen();
		if ( ! $screen || $screen->base !== 'post' ) return;
		global $post;
		if ( ! $post || (int) $post->ID !== (int) $footer_id ) return;

		// Get registered colors from Theme settings
		$config = corsivo_theme_get_settings();
		$colors = array();
		if ( ! empty( $config['configurator']['colors'] ) ) {
			foreach ( $config['configurator']['colors'] as $c ) {
				$colors[] = array(
					'name'  => $c['name'] ?? $c['slug'],
					'slug'  => $c['slug'],
					'color' => $c['color'],
				);
			}
		}

		$script = 'window.corsivoThemeFooterColors = ' . wp_json_encode( $colors ) . ';';
		wp_add_inline_script( 'wp-editor', $script, 'before' );
		wp_enqueue_script( 'corsivo-theme-footer-editor', plugin_dir_url( __FILE__ ) . 'assets/footer-editor.js', array( 'wp-plugins', 'wp-editor', 'wp-element', 'wp-components', 'wp-data' ), filemtime( plugin_dir_path( __FILE__ ) . 'assets/footer-editor.js' ), true );
		// Hide title + page panel (featured image, slug, author, template, etc.)
		wp_add_inline_style( 'wp-edit-post', '
			.editor-post-title,
			.edit-post-visual-editor__post-title-wrapper,
			.editor-page-attributes__parent,
			.editor-post-featured-image,
			.editor-post-link,
			.editor-post-slug,
			.editor-post-author,
			.editor-post-template,
			.edit-post-post-visibility,
			.edit-post-post-schedule,
			.editor-post-panel__row,
			.editor-post-trash { display: none !important; }
		' );

		// Footer component-mode: CSS inside iframe via enqueue_block_assets
		add_action( 'enqueue_block_assets', function () {
			if ( ! is_admin() ) return;
			$footer_id = get_option( 'corsivo_theme_footer_page_id' );
			global $post;
			if ( ! $post || (int) $post->ID !== (int) $footer_id ) return;

			$css = '
				.editor-post-title,
				.edit-post-visual-editor__post-title-wrapper,
				h1.wp-block-post-title { display: none !important; }
				.editor-styles-wrapper { padding-top: 0 !important; }
				.is-root-container.has-global-padding { padding-left: 0 !important; padding-right: 0 !important; }
				.is-root-container::before {
					content: "site content";
					display: flex;
					align-items: flex-end;
					justify-content: center;
					height: 60vh;
					padding-bottom: 10px;
					font: 11px/1 monospace;
					color: #999;
					letter-spacing: 0.15em;
					text-transform: uppercase;
					background: #e0e0e0;
					background-image:
						linear-gradient(rgba(0,0,0,.04) 1px, transparent 1px),
						linear-gradient(90deg, rgba(0,0,0,.04) 1px, transparent 1px);
					background-size: 80px 40px;
					border-bottom: 2px dashed #bbb;
					margin-bottom: 0;
				}
			';
			wp_register_style( 'corsivo-theme-footer-editor-canvas', false );
			wp_enqueue_style( 'corsivo-theme-footer-editor-canvas' );
			wp_add_inline_style( 'corsivo-theme-footer-editor-canvas', $css );
		} );
		// Also hide inside the iframe
		add_action( 'admin_footer', function () {
			echo '<script>
				(function tryHide() {
					const iframe = document.querySelector("iframe[name=editor-canvas]");
					if (iframe && iframe.contentDocument) {
						const style = iframe.contentDocument.createElement("style");
						style.textContent = ".editor-post-title, .editor-post-title__input, .edit-post-visual-editor__post-title-wrapper, h1.wp-block-post-title { display:none!important }";
						iframe.contentDocument.head.appendChild(style);
					} else { setTimeout(tryHide, 500); }
				})();
			</script>';
		} );
	} );

	// Allow woff/woff2 font uploads
	add_filter('upload_mimes', function($mimes) {
		if (!current_user_can('manage_options')) return $mimes;
		$mimes['woff']  = 'font/woff';
		$mimes['woff2'] = 'font/woff2';
		$mimes['ttf']   = 'font/ttf';
		$mimes['otf']   = 'font/otf';
		return $mimes;
	});
