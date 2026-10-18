<?php
/**
 * Name: WooCommerce Gutenberg
 * Description: Abilita il block editor sui prodotti WooCommerce.
 * Category: WooCommerce
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'corsivo_toolkit_woocommerce_gutenberg_enable_product_editor' ) ) {

	function corsivo_toolkit_woocommerce_gutenberg_enable_product_editor() {
		if ( post_type_exists( 'product' ) ) {
			add_post_type_support( 'product', 'editor' );
			add_post_type_support( 'product', 'excerpt' );
		}
	}
	add_action( 'init', 'corsivo_toolkit_woocommerce_gutenberg_enable_product_editor', 20 );

	function corsivo_toolkit_woocommerce_gutenberg_use_block_editor( $can_edit, $post_type ) {
		if ( 'product' === $post_type ) {
			return true;
		}

		return $can_edit;
	}
	add_filter( 'use_block_editor_for_post_type', 'corsivo_toolkit_woocommerce_gutenberg_use_block_editor', 20, 2 );

	function corsivo_toolkit_woocommerce_gutenberg_post_type_args( $args ) {
		$args['show_in_rest'] = true;

		if ( empty( $args['supports'] ) || ! is_array( $args['supports'] ) ) {
			$args['supports'] = array();
		}

		$args['supports'] = array_values( array_unique( array_merge( $args['supports'], array( 'editor', 'excerpt' ) ) ) );

		return $args;
	}
	add_filter( 'woocommerce_register_post_type_product', 'corsivo_toolkit_woocommerce_gutenberg_post_type_args', 20 );

	function corsivo_toolkit_woocommerce_gutenberg_taxonomy_args( $args ) {
		$args['show_in_rest'] = true;

		return $args;
	}
	add_filter( 'woocommerce_taxonomy_args_product_cat', 'corsivo_toolkit_woocommerce_gutenberg_taxonomy_args', 20 );
	add_filter( 'woocommerce_taxonomy_args_product_tag', 'corsivo_toolkit_woocommerce_gutenberg_taxonomy_args', 20 );

	function corsivo_toolkit_woocommerce_gutenberg_admin_assets() {
		$screen = get_current_screen();

		if ( ! $screen || 'product' !== $screen->post_type ) {
			return;
		}

		wp_enqueue_style(
			'corsivo-toolkit-woocommerce-gutenberg',
			plugin_dir_url( CORSIVO_CORE_EDITOR_FILE ) . 'toolkit/assets/woocommerce-gutenberg.css',
			array(),
			(string) filemtime( __DIR__ . '/assets/woocommerce-gutenberg.css' )
		);
	}
	add_action( 'admin_enqueue_scripts', 'corsivo_toolkit_woocommerce_gutenberg_admin_assets' );
}
