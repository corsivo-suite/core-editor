<?php

defined( 'ABSPATH' ) || exit;

function corsivo_core_toolkit_enqueue_admin_assets( $hook ) {
	if ( 'settings_page_corsivo-core-editor' !== $hook ) {
		return;
	}

	wp_enqueue_style( 'corsivo-core-toolkit-admin', plugin_dir_url( CORSIVO_CORE_EDITOR_FILE ) . 'toolkit/assets/admin.css', array(), (string) filemtime( CORSIVO_CORE_EDITOR_DIR . 'toolkit/assets/admin.css' ) );
}
add_action( 'admin_enqueue_scripts', 'corsivo_core_toolkit_enqueue_admin_assets' );

function corsivo_core_toolkit_save() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Non puoi modificare queste impostazioni.', 'corsivo-core-editor' ), '', array( 'response' => 403 ) );
	}

	if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
		wp_die( esc_html__( 'Richiesta non valida.', 'corsivo-core-editor' ), '', array( 'response' => 405 ) );
	}

	check_admin_referer( 'corsivo_core_save_toolkit', 'corsivo_core_toolkit_nonce' );
	$requested = isset( $_POST['corsivo_core_modules'] ) ? wp_unslash( $_POST['corsivo_core_modules'] ) : array();

	if ( ! is_array( $requested ) ) {
		wp_die( esc_html__( 'Selezione degli strumenti non valida.', 'corsivo-core-editor' ), '', array( 'response' => 400 ) );
	}

	update_option( 'corsivo_core_enabled_modules', corsivo_core_toolkit_sanitize_modules( $requested ), false );
	wp_safe_redirect( admin_url( 'options-general.php?page=corsivo-core-editor&toolkit-updated=true#toolkit' ) );
	exit;
}
add_action( 'admin_post_corsivo_core_save_toolkit', 'corsivo_core_toolkit_save' );

function corsivo_core_toolkit_render() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$enabled = corsivo_core_toolkit_get_enabled_modules();
	$groups = array();

	foreach ( corsivo_core_toolkit_get_modules() as $module ) {
		$groups[ $module['category'] ][] = $module;
	}

	ksort( $groups );
	?>
	<div class="corsivo-toolkit-page">
		<?php if ( isset( $_GET['toolkit-updated'] ) && 'true' === $_GET['toolkit-updated'] ) : ?>
			<div class="notice notice-success inline is-dismissible"><p><?php esc_html_e( 'Strumenti salvati.', 'corsivo-core-editor' ); ?></p></div>
		<?php endif; ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="corsivo_core_save_toolkit">
			<?php wp_nonce_field( 'corsivo_core_save_toolkit', 'corsivo_core_toolkit_nonce' ); ?>
			<?php foreach ( $groups as $category => $modules ) : ?>
				<h2><?php echo esc_html( $category ); ?></h2>
				<table class="widefat striped corsivo-toolkit-table">
					<thead><tr><th><?php esc_html_e( 'Attivo', 'corsivo-core-editor' ); ?></th><th><?php esc_html_e( 'Strumento', 'corsivo-core-editor' ); ?></th><th><?php esc_html_e( 'Descrizione', 'corsivo-core-editor' ); ?></th></tr></thead>
					<tbody>
						<?php foreach ( $modules as $module ) : ?>
							<tr>
								<td class="corsivo-toolkit-toggle-cell"><label class="corsivo-toolkit-toggle"><input type="checkbox" name="corsivo_core_modules[]" value="<?php echo esc_attr( $module['slug'] ); ?>" aria-label="<?php echo esc_attr( $module['name'] ); ?>" <?php checked( in_array( $module['slug'], $enabled, true ) ); ?>><span></span></label></td>
								<td><strong><?php echo esc_html( $module['name'] ); ?></strong><code><?php echo esc_html( $module['slug'] ); ?></code></td>
								<td><?php echo esc_html( $module['description'] ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endforeach; ?>
			<?php submit_button( __( 'Salva strumenti', 'corsivo-core-editor' ), 'primary', 'corsivo_core_toolkit_submit' ); ?>
		</form>
	</div>
	<?php
}
