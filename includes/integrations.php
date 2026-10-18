<?php
if (!defined('ABSPATH')) exit;

function corsivo_theme_integration_available($integration) {
	switch ($integration) {
		case 'wpml':
			return defined('ICL_SITEPRESS_VERSION') || class_exists('SitePress') || function_exists('icl_object_id');
		case 'woocommerce':
			return defined('WC_VERSION') || class_exists('WooCommerce');
		default:
			return false;
	}
}

function corsivo_theme_get_integrations() {
	$integrations = get_option('corsivo_theme_integrations', array());
	return is_array($integrations) ? $integrations : array();
}

function corsivo_theme_integration_enabled($integration) {
	if (!corsivo_theme_integration_available($integration)) {
		return false;
	}

	$integrations = corsivo_theme_get_integrations();
	if (array_key_exists($integration, $integrations)) {
		return !empty($integrations[$integration]);
	}

	return $integration === 'wpml';
}

function corsivo_theme_wpml_enabled() {
	return corsivo_theme_integration_enabled('wpml');
}

function corsivo_theme_current_language() {
	if (!corsivo_theme_wpml_enabled()) {
		return 'default';
	}

	$language = apply_filters('wpml_current_language', null);
	return is_string($language) && $language !== '' ? sanitize_key($language) : 'default';
}

function corsivo_theme_default_language() {
	if (!corsivo_theme_wpml_enabled()) {
		return 'default';
	}

	$language = apply_filters('wpml_default_language', null);
	return is_string($language) && $language !== '' ? sanitize_key($language) : 'default';
}

function corsivo_theme_active_languages() {
	if (!corsivo_theme_wpml_enabled()) {
		return array('default' => array('code' => 'default', 'native_name' => 'Default'));
	}

	$languages = apply_filters('wpml_active_languages', null, array('skip_missing' => 0, 'orderby' => 'id', 'order' => 'asc'));
	if (is_array($languages) && !empty($languages)) return $languages;
	$language = corsivo_theme_default_language();
	return array($language => array('code' => $language, 'native_name' => strtoupper($language)));
}

function corsivo_theme_translate_object_id($object_id, $object_type = 'page', $return_original = true, $language = null) {
	$object_id = absint($object_id);
	if (!$object_id || !corsivo_theme_wpml_enabled()) {
		return $object_id;
	}

	$translated = apply_filters('wpml_object_id', $object_id, $object_type, $return_original, $language);
	return $translated ? absint($translated) : 0;
}

function corsivo_theme_custom_slug_post_type_allowed($post_type) {
	if ($post_type === 'product' && corsivo_theme_integration_available('woocommerce')) {
		return false;
	}
	return (bool) apply_filters('corsivo_theme_custom_slug_post_type_allowed', true, $post_type);
}

function corsivo_theme_woocommerce_contexts($contexts) {
	if (!corsivo_theme_integration_enabled('woocommerce')) {
		return $contexts;
	}

	$contexts[] = array('value' => 'woocommerce-shop', 'label' => 'WooCommerce Shop');
	$contexts[] = array('value' => 'woocommerce-cart', 'label' => 'WooCommerce Cart');
	$contexts[] = array('value' => 'woocommerce-checkout', 'label' => 'WooCommerce Checkout');
	$contexts[] = array('value' => 'woocommerce-account', 'label' => 'WooCommerce Account');
	return $contexts;
}
add_filter('corsivo_theme_available_contexts', 'corsivo_theme_woocommerce_contexts');

function corsivo_theme_save_integrations() {
	if (!current_user_can('manage_options')) {
		wp_die(esc_html__('You are not allowed to manage integrations.', 'corsivo-core-editor'), 403);
	}

	check_admin_referer('corsivo_theme_save_integrations');

	$old_integrations = corsivo_theme_get_integrations();
	$new_integrations = array();
	$posted_integrations = isset($_POST['integrations']) && is_array($_POST['integrations']) ? wp_unslash($_POST['integrations']) : array();
	foreach (array('wpml', 'woocommerce') as $integration) {
		if (corsivo_theme_integration_available($integration)) {
			$new_integrations[$integration] = isset($posted_integrations[$integration]);
		}
	}

	update_option('corsivo_theme_integrations', $new_integrations, false);
	if ($old_integrations !== $new_integrations) corsivo_theme_request_rewrite_flush();
	corsivo_theme_log_activity(
		'integrations_saved',
		'success',
		'Integration settings updated.',
		array('before' => $old_integrations, 'after' => $new_integrations)
	);

	wp_safe_redirect(admin_url('options-general.php?page=corsivo-core-editor&corsivo-theme-notice=integrations-saved#integrations'));
	exit;
}
add_action('admin_post_corsivo_theme_save_integrations', 'corsivo_theme_save_integrations');

function corsivo_theme_integrations_render() {
	$available = array_filter(array(
		'wpml' => array(
			'label' => 'WPML',
			'description' => 'Use language-specific pages, homepage assignments and custom slugs.',
		),
		'woocommerce' => array(
			'label' => 'WooCommerce',
			'description' => 'Add WooCommerce style contexts. Product permalinks always remain under WooCommerce control.',
		),
	), function ($integration) {
		return corsivo_theme_integration_available($integration);
	}, ARRAY_FILTER_USE_KEY);
	?>
	<div class="wrap corsivo-theme-component">
		<h2>Integrations</h2>
		<?php if (empty($available)): ?>
			<p>No supported integrations are active.</p>
		<?php else: ?>
			<form method="post" action="<?= esc_url(admin_url('admin-post.php')) ?>">
				<input type="hidden" name="action" value="corsivo_theme_save_integrations">
				<?php wp_nonce_field('corsivo_theme_save_integrations'); ?>
				<table class="form-table" role="presentation"><tbody>
				<?php foreach ($available as $slug => $integration): ?>
					<tr>
						<th scope="row"><?= esc_html($integration['label']) ?></th>
						<td>
							<label><input type="checkbox" name="integrations[<?= esc_attr($slug) ?>]" value="1" <?php checked(corsivo_theme_integration_enabled($slug)); ?>> Enable</label>
							<p class="description"><?= esc_html($integration['description']) ?></p>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody></table>
				<button type="submit" class="button button-primary">Save integrations</button>
			</form>
		<?php endif; ?>
	</div>
	<?php
}
