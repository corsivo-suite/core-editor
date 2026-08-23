<?php
if (!defined('ABSPATH')) exit;

/**
 * Single source of truth for a CPT archive override.
 * Precedence: archive_page (the page drives URL + path) > plural (text slug) > none.
 * '-' and '' both mean "no override, fall back to WP defaults".
 * Returns array{page_id:int, path:string, url:string} or null.
 */
function corsivo_theme_get_archive_override($post_type, $lang = null) {
	if (!corsivo_theme_custom_slug_post_type_allowed($post_type)) {
		return null;
	}
    $corsivo_theme_config = corsivo_theme_get_settings();
    if (empty($corsivo_theme_config['custom_slugs'][$post_type])) {
        return null;
    }

    if ($lang === null) {
        $lang = corsivo_theme_current_language();
    }
    $slugs = $corsivo_theme_config['custom_slugs'][$post_type][$lang] ?? array();

    $archive_page = isset($slugs['archive_page']) ? trim((string) $slugs['archive_page']) : '';
    if ($archive_page !== '' && $archive_page !== '-') {
        $page = get_post((int) $archive_page);
        if ($page && $page->post_status === 'publish') {
            return array(
                'page_id' => (int) $archive_page,
                'path'    => get_page_uri($page),
                'url'     => get_permalink($page),
            );
        }
    }

    $plural = isset($slugs['plural']) ? trim((string) $slugs['plural']) : '';
    if ($plural !== '' && $plural !== '-') {
        return array(
            'page_id' => 0,
            'path'    => $plural,
            'url'     => home_url('/' . $plural . '/'),
        );
    }

    return null;
}

/**
 * Flag a rewrite flush for the next init. Set when custom_slugs change so the
 * user no longer needs to visit Settings > Permalinks by hand.
 */
function corsivo_theme_request_rewrite_flush() {
    update_option('corsivo_theme_rewrite_flush_needed', 1);
}

function corsivo_theme_maybe_flush_rewrite_rules() {
    if (get_option('corsivo_theme_rewrite_flush_needed')) {
        flush_rewrite_rules(false);
        delete_option('corsivo_theme_rewrite_flush_needed');
    }
}
add_action('init', 'corsivo_theme_maybe_flush_rewrite_rules', 20);

/**
 * When a page assigned as a CPT archive_page changes its slug or status,
 * the archive rewrite rule (built from the page URI) goes stale. Flag a flush.
 */
function corsivo_theme_flush_on_archive_page_change($post_id, $post_after, $post_before) {
    if ($post_after->post_type !== 'page') {
        return;
    }
    if ($post_after->post_name === $post_before->post_name
        && $post_after->post_status === $post_before->post_status) {
        return;
    }

    $corsivo_theme_config = corsivo_theme_get_settings();
    if (empty($corsivo_theme_config['custom_slugs'])) {
        return;
    }

	foreach ($corsivo_theme_config['custom_slugs'] as $post_type => $languages) {
		if (!corsivo_theme_custom_slug_post_type_allowed($post_type) || !is_array($languages)) continue;
		foreach ($languages as $slugs) {
			if (is_array($slugs) && isset($slugs['archive_page']) && (int) $slugs['archive_page'] === (int) $post_id) {
                corsivo_theme_request_rewrite_flush();
                return;
            }
        }
    }
}
add_action('post_updated', 'corsivo_theme_flush_on_archive_page_change', 10, 3);

/**
 * Resolve the URL base segment a post type's permalinks actually use.
 * Honors a custom rewrite slug; falls back to the post type name.
 */
function corsivo_theme_post_type_rewrite_base($post_type) {
    $pt_obj = get_post_type_object($post_type);
    if ($pt_obj && is_array($pt_obj->rewrite) && !empty($pt_obj->rewrite['slug'])) {
        return $pt_obj->rewrite['slug'];
    }
    return $post_type;
}

/**
 * Whether a singular slug override would shadow a real page or another
 * post type at the same top-level URL segment. Used to skip both the
 * rewrite rule and the permalink rewrite so we never produce dead links.
 */
function corsivo_theme_singular_rewrite_conflicts($singular, $post_type) {
    if (get_page_by_path($singular)) {
        return true;
    }
    foreach (get_post_types(array(), 'objects') as $pt) {
        if ($pt->name === $post_type) {
            continue;
        }
        if (corsivo_theme_post_type_rewrite_base($pt->name) === $singular) {
            return true;
        }
    }
    return false;
}

function corsivo_theme_modify_cpt_permalink($post_link, $post) {
    $corsivo_theme_config = corsivo_theme_get_settings();
	$post_type = $post->post_type;
	if (!corsivo_theme_custom_slug_post_type_allowed($post_type)) return $post_link;
    $current_lang = corsivo_theme_current_language();

    $singular = $corsivo_theme_config['custom_slugs'][$post_type][$current_lang]['singular'] ?? '';
    if ($singular !== '' && $singular !== '-' && !corsivo_theme_singular_rewrite_conflicts($singular, $post_type)) {
        $base = corsivo_theme_post_type_rewrite_base($post_type);
        $post_link = str_replace('/' . $base . '/', '/' . $singular . '/', $post_link);
    }

    return $post_link;
}
add_filter('post_type_link', 'corsivo_theme_modify_cpt_permalink', 10, 2);

function corsivo_theme_register_archive_rewrite_rules() {
    $corsivo_theme_config = corsivo_theme_get_settings();
    if (empty($corsivo_theme_config['custom_slugs'])) {
        return;
    }

	$wpml_active = corsivo_theme_wpml_enabled();
	$languages = array_keys(corsivo_theme_active_languages());

	foreach ($corsivo_theme_config['custom_slugs'] as $post_type => $lang_slugs) {
		if (!corsivo_theme_custom_slug_post_type_allowed($post_type)) continue;
        foreach ($languages as $lang) {
            if ($wpml_active && $lang === 'default') {
                continue;
            }

            $archive = corsivo_theme_get_archive_override($post_type, $lang);
            if ($archive) {
                $path = preg_quote($archive['path'], '/');
                add_rewrite_rule('^' . $path . '/?$', 'index.php?post_type=' . $post_type, 'top');
                add_rewrite_rule('^' . $path . '/page/([0-9]+)/?$', 'index.php?post_type=' . $post_type . '&paged=$matches[1]', 'top');
            }

            $singular = isset($lang_slugs[$lang]['singular']) ? trim((string) $lang_slugs[$lang]['singular']) : '';
            if ($singular !== '' && $singular !== '-' && !corsivo_theme_singular_rewrite_conflicts($singular, $post_type)) {
                add_rewrite_rule('^' . preg_quote($singular, '/') . '/([^/]+)/?$', 'index.php?' . $post_type . '=$matches[1]', 'top');
            }
        }
    }
}
add_action('init', 'corsivo_theme_register_archive_rewrite_rules');

/**
 * When a CPT archive has an archive_page set, load that page's template
 * and render its Gutenberg content instead of the default archive.
 */
function corsivo_theme_archive_page_template( $template ) {
	if ( ! is_post_type_archive() ) return $template;

	$post_type = get_queried_object()->name ?? get_query_var( 'post_type' );
	if ( is_array( $post_type ) ) $post_type = $post_type[0] ?? '';
	if ( ! $post_type ) return $template;

	$archive = corsivo_theme_get_archive_override( $post_type );
	if ( ! $archive || empty( $archive['page_id'] ) ) return $template;

	$page_id = $archive['page_id'];
	$page = get_post( $page_id );
	if ( ! $page || $page->post_status !== 'publish' ) return $template;

	$page_template = get_page_template_slug( $page_id );
	if ( ! $page_template && trim( (string) $page->post_content ) === '' ) return $template;

	$resolved_template = '';
	if ( $page_template ) {
		$resolved = locate_template( array( $page_template, 'templates/' . $page_template ) );
		if ( $resolved ) $resolved_template = $resolved;
	}

	if (!$resolved_template) $resolved_template = locate_template('page.php');
	if (!$resolved_template) return $template;

	$GLOBALS['corsivo_theme_archive_page_context'] = array(
		'page_id' => $page_id,
		'post_type' => $post_type,
		'template' => $resolved_template,
	);
	return CORSIVO_THEME_EDITOR_DIR . 'templates/archive-page-proxy.php';
}
add_filter( 'template_include', 'corsivo_theme_archive_page_template', 40 );

/**
 * Whether a menu item points to the archive of the given post type.
 * Besides the assigned archive page (matched by object_id elsewhere),
 * this matches custom links by URL: plural slug, archive page permalink,
 * or the native post type archive link.
 */
function corsivo_theme_menu_item_targets_archive_url( $item, $post_type, $page_id, $plural ) {
	if ( empty( $item->url ) ) return false;

	$item_url   = untrailingslashit( $item->url );
	$candidates = array();

	if ( $plural ) {
		$candidates[] = untrailingslashit( home_url( '/' . $plural ) );
	}
	if ( $page_id ) {
		$permalink = get_permalink( (int) $page_id );
		if ( $permalink ) $candidates[] = untrailingslashit( $permalink );
	}
	$native = get_post_type_archive_link( $post_type );
	if ( $native ) $candidates[] = untrailingslashit( $native );

	return in_array( $item_url, $candidates, true );
}

/**
 * Highlight the correct menu item when viewing a CPT archive
 * that has an archive_page assigned.
 */
function corsivo_theme_archive_menu_highlight( $classes, $item ) {
	$corsivo_theme_config   = corsivo_theme_get_settings();
	$current_lang = corsivo_theme_current_language();
	$global = isset($corsivo_theme_config['global']) ? $corsivo_theme_config['global'] : array();

	// Determine the CPT and its archive_page for both archive and single views
	$post_type = null;
	$page_id   = '';

	if ( is_post_type_archive() ) {
		$post_type = get_query_var( 'post_type' );
		if ( is_array( $post_type ) ) $post_type = $post_type[0];
	} elseif ( is_singular() && ! empty( $global['cpt_menu_parent'] ) ) {
		$post_type = get_post_type();
		// Skip built-in types (post/page already handled by WP)
		if ( in_array( $post_type, array( 'page', 'attachment' ), true ) ) return $classes;
	}

	if ( ! $post_type ) return $classes;

	$archive = corsivo_theme_get_archive_override( $post_type, $current_lang );
	if ( ! $archive ) return $classes;
	$page_id = $archive['page_id'];
	$plural  = $corsivo_theme_config['custom_slugs'][ $post_type ][ $current_lang ]['plural'] ?? '';

	$is_archive_item = ( (int) $item->object_id === (int) $page_id && $item->object === 'page' )
		|| corsivo_theme_menu_item_targets_archive_url( $item, $post_type, $page_id, $plural );

	if ( $is_archive_item ) {
		$classes[] = 'current-menu-item';
		$classes[] = 'current_page_item';
		if ( is_singular() ) {
			$classes[] = 'current-menu-parent';
			$classes[] = 'current_page_parent';
		}
	} else {
		// Remove false current markers from other items
		$classes = array_diff( $classes, array(
			'current-menu-item', 'current_page_item',
			'current-menu-parent', 'current_page_parent',
			'current-menu-ancestor', 'current_page_ancestor',
		) );
	}

	return $classes;
}
add_filter( 'nav_menu_css_class', 'corsivo_theme_archive_menu_highlight', 10, 2 );

function corsivo_theme_handle_missing_cpt_posts() {
	$current_lang = corsivo_theme_current_language();

    $corsivo_theme_config = corsivo_theme_get_settings();
    global $wp_query;

	if (corsivo_theme_wpml_enabled()) {
		$languages = array_keys(corsivo_theme_active_languages());
		$main_lang = corsivo_theme_default_language();

        $request_uri   = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '';
        $request_path  = (string) wp_parse_url($request_uri, PHP_URL_PATH);
        $request_query = (string) wp_parse_url($request_uri, PHP_URL_QUERY);
        $post_type     = isset($wp_query->query_vars['post_type']) ? $wp_query->query_vars['post_type'] : null;

        if (!empty($post_type) && isset($corsivo_theme_config['custom_slugs'][$post_type])) {
            $segments = array_values(array_filter(explode('/', $request_path), 'strlen'));
            $first    = isset($segments[0]) ? $segments[0] : '';

            // Only act on URLs that are NOT already language-prefixed.
            if ($first !== '' && !in_array($first, $languages, true)) {
                $requested_lang = false;

                foreach ($corsivo_theme_config['custom_slugs'][$post_type] as $lang => $slugs) {
                    if ($lang === 'default') {
                        continue;
                    }
                    if ((isset($slugs['singular']) && $slugs['singular'] === $first)
                        || (isset($slugs['plural']) && $slugs['plural'] === $first)) {
                        $requested_lang = $lang;
                        break;
                    }
                }

                // The default language carries no URL prefix; only prepend for
                // non-default, non-current languages, and never double-prefix.
                if ($requested_lang && $requested_lang !== $current_lang && $requested_lang !== $main_lang) {
                    $target_path = '/' . $requested_lang . '/' . ltrim($request_path, '/');
                    if (untrailingslashit($target_path) !== untrailingslashit($request_path)) {
                        $target = $target_path . ($request_query !== '' ? '?' . $request_query : '');
						wp_safe_redirect(home_url($target), 301);
                        exit;
                    }
                }
            }
        }
    }

    if (isset($wp_query->is_archive) && $wp_query->is_archive
        && isset($wp_query->query) && isset($wp_query->query['post_type']) && $wp_query->query['post_type']) {

        $post_type = $wp_query->query['post_type'];

        $archive = corsivo_theme_get_archive_override($post_type, $current_lang);
        if (!$archive) {
            return;
        }

        global $corsivo_theme_custom_slug_query;
        if ($corsivo_theme_custom_slug_query) {
            return;
        }

        $request_uri   = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '';
        $request_path  = (string) wp_parse_url($request_uri, PHP_URL_PATH);
        $request_query = (string) wp_parse_url($request_uri, PHP_URL_QUERY);

        $paged      = (int) get_query_var('paged');
        $target_url = $paged > 1
            ? trailingslashit($archive['url']) . 'page/' . $paged . '/'
            : $archive['url'];

        $target_path = (string) wp_parse_url($target_url, PHP_URL_PATH);
        if (untrailingslashit($request_path) === untrailingslashit($target_path)) {
            return;
        }

		wp_safe_redirect($target_url . ($request_query !== '' ? '?' . $request_query : ''), 301);
        exit;
    }
}
add_action('template_redirect', 'corsivo_theme_handle_missing_cpt_posts');


function corsivo_theme_translate_archive_link($link, $post_type) {
    $archive = corsivo_theme_get_archive_override($post_type);
    return $archive ? $archive['url'] : $link;
}
add_filter('post_type_archive_link', 'corsivo_theme_translate_archive_link', 10, 2);

function corsivo_theme_set_wp_query($params) {
    if (isset($params) && isset($params->request)) {
        $corsivo_theme_config = corsivo_theme_get_settings();
        $request = $params->request;
        $request_parts = explode('/', $request);

        global $corsivo_theme_custom_slug_query;
        if (!isset($corsivo_theme_config['custom_slugs'])) {
            return;
        }

		foreach ($corsivo_theme_config['custom_slugs'] as $post_type => $langs) {
			if (!corsivo_theme_custom_slug_post_type_allowed($post_type)) continue;
			foreach ($langs as $lang => $slugs) {
				if (corsivo_theme_wpml_enabled() && $lang === 'default') {
                    continue;
                }
                if (isset($slugs['plural']) && in_array($slugs['plural'], $request_parts)) {
                    $corsivo_theme_custom_slug_query = true;
                    break;
                }
            }
        }
    }
}   
add_action('wp', 'corsivo_theme_set_wp_query', 10, 2);
