<?php
/**
 * Name: WooCommerce Notices
 * Description: Mostra i messaggi WooCommerce in overlay.
 * Category: WooCommerce
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'corsivo_toolkit_woocommerce_notices_is_available' ) ) {

	function corsivo_toolkit_woocommerce_notices_is_available() {
		return function_exists( 'wc_get_notices' ) && function_exists( 'wc_clear_notices' );
	}

	function corsivo_toolkit_woocommerce_notices_enqueue_assets() {
		if ( ! corsivo_toolkit_woocommerce_notices_is_available() ) {
			return;
		}

		wp_enqueue_style(
			'corsivo-toolkit-woocommerce-notices',
			plugin_dir_url( CORSIVO_CORE_EDITOR_FILE ) . 'toolkit/woocommerce-notices/style.css',
			array(),
			(string) filemtime( __DIR__ . '/style.css' )
		);
	}
	add_action( 'wp_enqueue_scripts', 'corsivo_toolkit_woocommerce_notices_enqueue_assets' );

	function corsivo_toolkit_woocommerce_notices_capture() {
		if ( corsivo_toolkit_woocommerce_notices_is_available() ) {
			$GLOBALS['corsivo_toolkit_woocommerce_notices'] = wc_get_notices();
		}
	}
	add_action( 'template_redirect', 'corsivo_toolkit_woocommerce_notices_capture' );

	function corsivo_toolkit_woocommerce_notices_format_message( $message ) {
		if ( function_exists( 'wc_kses_notice' ) ) {
			return wc_kses_notice( $message );
		}

		return wp_kses_post( $message );
	}

	function corsivo_toolkit_woocommerce_notices_render() {
		if ( ! corsivo_toolkit_woocommerce_notices_is_available() ) {
			return;
		}

		if ( isset( $_GET['corsivo-clear-notices'] ) ) {
			wc_clear_notices();
			return;
		}

		$notices = wc_get_notices();

		if ( empty( $notices ) && ! empty( $GLOBALS['corsivo_toolkit_woocommerce_notices'] ) ) {
			$notices = $GLOBALS['corsivo_toolkit_woocommerce_notices'];
			corsivo_core_toolkit_log( 'WooCommerce Notices: rendering from the template_redirect snapshot, notices added during template render may be missing.' );
		}

		if ( empty( $notices ) ) {
			return;
		}

		$type_classes = array(
			'success' => 'woocommerce-message',
			'error'   => 'woocommerce-error',
			'notice'  => 'woocommerce-info',
		);
		$clear_url = esc_url( add_query_arg( 'corsivo-clear-notices', 'true' ) );
		$cart_url = function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/' );
		?>
		<div class="corsivo-toolkit-woocommerce-notices">
			<div class="corsivo-toolkit-woocommerce-notices__content">
				<?php foreach ( $notices as $type => $notice_group ) : ?>
					<?php foreach ( $notice_group as $notice ) : ?>
						<?php
						$message = is_array( $notice ) && isset( $notice['notice'] ) ? $notice['notice'] : $notice;
						$class = $type_classes[ $type ] ?? 'woocommerce-info';
						?>
						<div class="<?php echo esc_attr( $class ); ?>" role="alert">
							<?php echo corsivo_toolkit_woocommerce_notices_format_message( $message ); ?>
						</div>
					<?php endforeach; ?>
				<?php endforeach; ?>
			</div>
			<div class="corsivo-toolkit-woocommerce-notices__actions">
				<a href="<?php echo esc_url( $cart_url ); ?>" class="button"><?php esc_html_e( 'Vai al carrello', 'corsivo-core-editor' ); ?></a>
				<a href="<?php echo $clear_url; ?>" class="button"><?php esc_html_e( 'Continua gli acquisti', 'corsivo-core-editor' ); ?></a>
			</div>
		</div>
		<?php
		wc_clear_notices();
	}
	add_action( 'wp_footer', 'corsivo_toolkit_woocommerce_notices_render', 20 );
}
