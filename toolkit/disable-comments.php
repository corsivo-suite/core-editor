<?php
/**
 * Name: Disable Comments
 * Description: Disattiva commenti e trackback in admin e frontend.
 * Category: Core
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'corsivo_toolkit_disable_comments_post_type_support' ) ) {

	function corsivo_toolkit_disable_comments_post_type_support() {
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			return;
		}

		foreach ( get_post_types() as $post_type ) {
			remove_post_type_support( $post_type, 'comments' );
			remove_post_type_support( $post_type, 'trackbacks' );
		}

		global $pagenow;

		if ( in_array( $pagenow, array( 'comment.php', 'edit-comments.php', 'options-discussion.php' ), true ) ) {
			wp_die( esc_html__( 'Comments are closed.', 'corsivo-core-editor' ), '', array( 'response' => 403 ) );
		}
	}
	add_action( 'admin_init', 'corsivo_toolkit_disable_comments_post_type_support' );

	function corsivo_toolkit_disable_comments_remove_admin_ui() {
		remove_menu_page( 'edit-comments.php' );
		remove_submenu_page( 'options-general.php', 'options-discussion.php' );
		remove_meta_box( 'dashboard_recent_comments', 'dashboard', 'normal' );
	}
	add_action( 'admin_menu', 'corsivo_toolkit_disable_comments_remove_admin_ui', 999 );
	add_action( 'wp_dashboard_setup', 'corsivo_toolkit_disable_comments_remove_admin_ui', 999 );

	function corsivo_toolkit_disable_comments_admin_bar() {
		global $wp_admin_bar;

		if ( is_object( $wp_admin_bar ) ) {
			$wp_admin_bar->remove_node( 'comments' );
		}
	}
	add_action( 'wp_before_admin_bar_render', 'corsivo_toolkit_disable_comments_admin_bar' );

	add_filter( 'comments_open', '__return_false', 20 );
	add_filter( 'pings_open', '__return_false', 20 );
	add_filter( 'comments_array', '__return_empty_array', 20 );
	add_filter( 'feed_links_show_comments_feed', '__return_false' );
}
