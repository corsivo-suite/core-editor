<?php
if (!defined('ABSPATH')) exit;

function corsivo_theme_global_settings_render($settings) {
	$global = isset($settings['global']) ? $settings['global'] : array();
	?>
	<div id="corsivo-theme-pages-list-settings" class="wrap corsivo-theme-component">
		<h2>Settings</h2>
		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row">Download theme-json.scss</th>
					<td>
						<?php
							$download_url = admin_url('admin-ajax.php') . '?action=corsivo_theme_download_scss&nonce=' . wp_create_nonce('corsivo_theme_download_scss');
							echo '<a class="button corsivo-theme-download-with-rebuild" href="' . esc_url($download_url) . '">Download theme-json.scss</a>';
						?>
						<p class="description">Generated on demand with font styles, color variables, and responsive breakpoints.</p>
					</td>
				</tr>
				<tr>
					<th scope="row">Download theme.json</th>
					<td>
						<?php
							$download_url = admin_url('admin-ajax.php') . '?action=corsivo_theme_download_theme_json&nonce=' . wp_create_nonce('corsivo_theme_download_theme_json');
							echo '<a class="button corsivo-theme-download-with-rebuild" href="' . esc_url($download_url) . '">Download theme.json</a>';
						?>
						<p class="description">Enriched theme.json with colors, fonts, fontFace and global styles injected by Corsivo Theme. Can be used as a standalone theme.json without the plugin.</p>
					</td>
				</tr>
			</tbody>
		</table>

		<hr />

		<h3>Extra</h3>
		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row">
						<label for="corsivo-theme-tab-functional-pages">Functional Pages</label>
						<span class="dashicons dashicons-info-outline corsivo-theme-tooltip" title="Map WordPress pages to named slots (e.g. 'contact', 'about') so your theme can retrieve them with corsivo_theme_get_function_page('slug'). Useful for multilingual sites where page IDs differ per language."></span>
					</th>
					<td>
						<input type="checkbox" name="corsivo-theme-tab-functional-pages" id="corsivo-theme-tab-functional-pages" <?= !empty($global['tab_functional_pages']) ? 'checked' : '' ?>>
						<label for="corsivo-theme-tab-functional-pages">Show tab</label>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="corsivo-theme-tab-custom-slugs">Custom Slugs</label>
						<span class="dashicons dashicons-info-outline corsivo-theme-tooltip" title="Override the URL slugs for custom post types (e.g. change /portfolio/ to /work/). Supports per-language slugs when WPML is active."></span>
					</th>
					<td>
						<input type="checkbox" name="corsivo-theme-tab-custom-slugs" id="corsivo-theme-tab-custom-slugs" <?= !empty($global['tab_custom_slugs']) ? 'checked' : '' ?>>
						<label for="corsivo-theme-tab-custom-slugs">Show tab</label>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="corsivo-theme-core-renders">Core Renders</label>
						<span class="dashicons dashicons-info-outline corsivo-theme-tooltip" title="Load PHP helper functions to render core WordPress blocks programmatically in templates: render_core_button(), render_core_image(), render_core_heading(), render_core_paragraph(), render_core_separator(), render_core_spacer(), render_core_search(), render_core_code(), render_core_preformatted(), render_core_video(), render_core_audio()."></span>
					</th>
					<td>
						<input type="checkbox" name="corsivo-theme-core-renders" id="corsivo-theme-core-renders" <?= !empty($global['core_renders']) ? 'checked' : '' ?>>
						<label for="corsivo-theme-core-renders">Enable</label>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="corsivo-theme-gutenberg-footer">Gutenberg Footer</label>
						<span class="dashicons dashicons-info-outline corsivo-theme-tooltip" title="Edit the site footer using the Gutenberg block editor. Creates a private page editable from the admin. Use corsivo_theme_render_footer() in footer.php to output it."></span>
					</th>
					<td>
						<input type="checkbox" name="corsivo-theme-gutenberg-footer" id="corsivo-theme-gutenberg-footer" <?= !empty($global['gutenberg_footer']) ? 'checked' : '' ?>>
						<label for="corsivo-theme-gutenberg-footer">Enable</label>
						<?php
						$footer_id = get_option( 'corsivo_theme_footer_page_id' );
						if ( ! empty( $global['gutenberg_footer'] ) && $footer_id ) :
						?>
							<a href="<?= esc_url( get_edit_post_link( $footer_id ) ) ?>" class="button button-small" style="margin-left: 1rem;">Edit Footer</a>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="corsivo-theme-cpt-menu-parent">CPT Menu Parent</label>
						<span class="dashicons dashicons-info-outline corsivo-theme-tooltip" title="When viewing a single CPT post (e.g. a single experiment), automatically highlight the menu item that links to its archive page. WordPress doesn't do this natively for custom post types."></span>
					</th>
					<td>
						<input type="checkbox" name="corsivo-theme-cpt-menu-parent" id="corsivo-theme-cpt-menu-parent" <?= !empty($global['cpt_menu_parent']) ? 'checked' : '' ?>>
						<label for="corsivo-theme-cpt-menu-parent">Enable</label>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="corsivo-theme-force-style-injection">Force Style Injection</label>
						<span class="dashicons dashicons-info-outline corsivo-theme-tooltip" title="Always print Theme / theme.json preset CSS variables and utility classes on the frontend, even when Gutenberg would skip them because the matching block is not present on the page. Font faces remain owned by WordPress core."></span>
					</th>
					<td>
						<input type="checkbox" name="corsivo-theme-force-style-injection" id="corsivo-theme-force-style-injection" <?= !empty($global['force_style_injection']) ? 'checked' : '' ?>>
						<label for="corsivo-theme-force-style-injection">Force frontend injection</label>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="corsivo-theme-minify-css">Minify CSS</label>
						<span class="dashicons dashicons-info-outline corsivo-theme-tooltip" title="Combine and minify the active child theme's style.css and filtered stylesheets into a fingerprinted cache owned by the plugin. Source files in the theme are never changed."></span>
					</th>
					<td>
						<input type="checkbox" name="corsivo-theme-minify-css" id="corsivo-theme-minify-css" <?= !empty($global['minify_css']) ? 'checked' : '' ?>>
						<label for="corsivo-theme-minify-css">Enable</label>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="corsivo-theme-minify-js">Minify JS</label>
						<span class="dashicons dashicons-info-outline corsivo-theme-tooltip" title="Combine compatible enqueued footer scripts owned by the active theme into a fingerprinted plugin cache. Module, async, deferred, header, core and plugin scripts are left untouched."></span>
					</th>
					<td>
						<input type="checkbox" name="corsivo-theme-minify-js" id="corsivo-theme-minify-js" <?= !empty($global['minify_js']) ? 'checked' : '' ?>>
						<label for="corsivo-theme-minify-js">Enable</label>
					</td>
				</tr>
			</tbody>
		</table>
	</div>
	<?php
}


function corsivo_theme_functional_pages_render($settings, $current_lang) {
    $functional_pages = isset($settings['functional_pages']) ? $settings['functional_pages'] : array();
    $corsivo_theme_pages = apply_filters('corsivo_theme_pages', array());
    $pages = get_pages();
    ?>
    <div class="wrap corsivo-theme-component">
        <h2>Functional Pages</h2>
        <p>
            Pages retrievable via <em>corsivo_theme_get_function_page('slug')</em>.<br>
            Readme: <a target="_blank" href="https://gitlab.com/sh/plugins/modules/functional-archives-pages">Functional Archives Pages</a>
        </p>
        <?php foreach ($corsivo_theme_pages as $slug => $label): ?>
            <table class="form-table">
                <tbody>
                        <tr>
                            <th scope="row"><label for="functional_pages_<?= esc_attr($slug) ?>"><?= esc_html($label) ?></label></th>
                            <td>
                                <select name="functional_pages[<?= esc_attr($slug) ?>][<?= esc_attr($current_lang) ?>]" id="functional_pages_<?= esc_attr($slug) ?>">
                                    <option value="">—</option>
                                    <?php foreach ($pages as $page): if (empty($page->post_title)) continue; ?>
                                        <option value="<?= esc_attr($page->ID) ?>" <?php selected(isset($functional_pages[$slug][$current_lang]) && $functional_pages[$slug][$current_lang] == $page->ID); ?>><?= esc_html($page->post_title) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <td></td>
                            <td style="color: #999; font-style: italic;">wp-json/corsivo-theme-data/page/<?= esc_html($slug) ?></td>
                        </tr>
                </tbody>
            </table>
        <?php endforeach; ?>
    </div>
    <?php
}

function corsivo_theme_wordpress_render($settings, $current_lang) {
    $wordpress = isset($settings['wordpress']) ? $settings['wordpress'] : array();
	$is_wpml_active = corsivo_theme_wpml_enabled();

    if(!isset($wordpress['homepage']) || !is_array($wordpress['homepage'])) {
        $wordpress['homepage'] = array();
    }

    if (!isset($wordpress['homepage'][$current_lang])) {
        if ($is_wpml_active) {
			$default_front_page_id = corsivo_theme_translate_object_id(get_option('page_on_front'), 'page', true, $current_lang);
            $wordpress['homepage'][$current_lang] = $default_front_page_id;
        } else {
            $wordpress['homepage'][$current_lang] = get_option('page_on_front');
        }
    } else {
        $wordpress['homepage'][$current_lang] = intval($wordpress['homepage'][$current_lang]);
    }

    // Get pages for current language
    if ($is_wpml_active) {
        $args = array(
            'post_type'        => 'page',
            'suppress_filters' => false,
            'lang'             => $current_lang,
        );
        $pages = get_pages($args);
    } else {
        $pages = get_pages();
    }

    ?>
    <div class="wrap corsivo-theme-component">
        <h2>WordPress</h2>
        <table class="form-table">
            <tbody>
                <tr>
                    <th scope="row"><label for="wordpress_homepage_<?= esc_attr($current_lang) ?>">Home Page (<?= esc_html($current_lang) ?>)</label></th>
                    <td>
                        <select name="wordpress[homepage][<?= esc_attr($current_lang) ?>]" id="wordpress_homepage_<?= esc_attr($current_lang) ?>">
                            <option value="">—</option>
                            <?php foreach ($pages as $page): ?>
                                <?php if (empty($page->post_title)) continue; ?>
                                <option value="<?= esc_attr($page->ID) ?>" <?php selected(isset($wordpress['homepage'][$current_lang]) && $wordpress['homepage'][$current_lang] == $page->ID); ?>><?= esc_html($page->post_title) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <!-- Add more WordPress settings here if needed -->
            </tbody>
        </table>
    </div>
    <?php
}

function corsivo_theme_custom_slugs_render($settings, $current_lang) {
    $custom_slugs = isset($settings['custom_slugs']) ? $settings['custom_slugs'] : array();
	$post_types = get_post_types(array('public' => true, '_builtin' => false), 'objects');
	$post_types['post'] = get_post_type_object('post');
	$post_types = array_filter($post_types, function ($post_type) {
		return $post_type && corsivo_theme_custom_slug_post_type_allowed($post_type->name);
	});
	$is_wpml_active = corsivo_theme_wpml_enabled();
	$page_args = $is_wpml_active ? array('suppress_filters' => false, 'lang' => $current_lang) : array();
	$pages = get_pages($page_args);
    ?>
    <div class="wrap corsivo-theme-component">
        <h2>Custom Slugs</h2>
        <table class="form-table">
            <thead>
                <tr>
                    <th>Post Type</th>
                    <th>Single slug</th>
                    <th>Archive slug</th>
                    <th>Archive Page</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($post_types as $post_type): ?>
                    <tr>
                        <th scope="row"><?= esc_html($post_type->labels->singular_name) ?></th>
                        <td>
                            <input type="text" name="custom_slugs[<?= esc_attr($post_type->name) ?>][<?= esc_attr($current_lang) ?>][singular]" value="<?= esc_attr(isset($custom_slugs[$post_type->name][$current_lang]['singular']) ? $custom_slugs[$post_type->name][$current_lang]['singular'] : '') ?>">
                        </td>
                        <td>
                            <input type="text" name="custom_slugs[<?= esc_attr($post_type->name) ?>][<?= esc_attr($current_lang) ?>][plural]" value="<?= esc_attr(isset($custom_slugs[$post_type->name][$current_lang]['plural']) ? $custom_slugs[$post_type->name][$current_lang]['plural'] : '') ?>">
                        </td>
                        <td>
                            <select name="custom_slugs[<?= esc_attr($post_type->name) ?>][<?= esc_attr($current_lang) ?>][archive_page]">
                                <option value="">—</option>
                                <?php
                                $current_archive_page = isset($custom_slugs[$post_type->name][$current_lang]['archive_page'])
                                    ? $custom_slugs[$post_type->name][$current_lang]['archive_page']
                                    : '';
                                foreach ($pages as $page):
                                    if (empty($page->post_title)) continue;
                                ?>
                                    <option value="<?= esc_attr($page->ID) ?>" <?php selected($current_archive_page, $page->ID); ?>><?= esc_html($page->post_title) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php if ($is_wpml_active): ?>
            <p class="description">Enter custom slugs for each Custom Post Type per language.</p>
        <?php else: ?>
            <p class="description">Enter custom slugs for each Custom Post Type.</p>
        <?php endif; ?>
    </div>
    <?php
}
