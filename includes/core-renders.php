<?php
if (!defined('ABSPATH')) exit;
/**
 * Core Block Render Helpers
 *
 * Programmatic PHP rendering of WordPress core "leaf" blocks
 * (blocks that have no child blocks). Uses render_block() so
 * all server-side filters and block supports are applied.
 *
 * Activated only when "Core Renders" is enabled in Settings.
 *
 * @package ThemeJsonStudio
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/* ================================================================
 * Helper: build a CSS class string from block attrs
 * ================================================================ */

function corsivo_theme_block_classes( $base_class, $attrs = array() ) {
	$classes = array( $base_class );

	if ( ! empty( $attrs['style'] ) ) {
		$classes[] = 'is-style-' . sanitize_html_class( $attrs['style'] );
	}
	if ( ! empty( $attrs['class'] ) ) {
		$classes = array_merge($classes, array_map('sanitize_html_class', preg_split('/\s+/', $attrs['class'])));
	}
	if ( ! empty( $attrs['align'] ) ) {
		$classes[] = 'align' . sanitize_html_class( $attrs['align'] );
	}

	return implode( ' ', $classes );
}

function render_core_button( $attrs = array() ) {
	$wrapper_cls = corsivo_theme_block_classes( 'wp-block-button', $attrs );
	$id_attr     = ! empty( $attrs['id'] ) ? ' id="' . esc_attr( $attrs['id'] ) . '"' : '';

	$text = isset( $attrs['label'] ) ? $attrs['label'] : 'Click me';
	$url  = ! empty( $attrs['url'] ) ? $attrs['url'] : '';
	$tag = ! empty( $attrs['tag'] ) ? $attrs['tag'] : ( $url ? 'a' : 'button' );
	$aria = ! empty( $attrs['aria_label'] ) ? ' aria-label="' . esc_attr( $attrs['aria_label'] ) . '"' : '';

	if ( $tag === 'a' ) {
		$href   = ' href="' . esc_url( $url ) . '"';
		$target_value = !empty($attrs['target']) && in_array($attrs['target'], array('_blank', '_self', '_parent', '_top'), true) ? $attrs['target'] : '';
		$target = $target_value ? ' target="' . esc_attr($target_value) . '"' : '';
		$rel_value = !empty($attrs['rel']) ? sanitize_text_field($attrs['rel']) : '';
		if ($target_value === '_blank' && strpos(' ' . $rel_value . ' ', ' noopener ') === false) $rel_value = trim($rel_value . ' noopener');
		$rel = $rel_value ? ' rel="' . esc_attr($rel_value) . '"' : '';
		$inner  = '<a class="wp-block-button__link wp-element-button"' . $href . $target . $rel . $aria . '>' . wp_kses_post($text) . '</a>';
	} else {
		$inner = '<button class="wp-block-button__link wp-element-button" type="button"' . $aria . '>' . wp_kses_post($text) . '</button>';
	}

	$html = '<div class="' . esc_attr( $wrapper_cls ) . '"' . $id_attr . '>' . $inner . '</div>';

	return render_block( array(
		'blockName'    => 'core/button',
		'attrs'        => array_filter( array(
			'className' => ! empty( $attrs['style'] ) ? 'is-style-' . $attrs['style'] : null,
		) ),
		'innerHTML'    => $html,
		'innerContent' => array( $html ),
	) );
}

function render_core_image( $attrs = array() ) {
	$id   = ! empty( $attrs['id'] ) ? (int) $attrs['id'] : 0;
	$size = ! empty( $attrs['size'] ) ? $attrs['size'] : 'full';
	$url  = ! empty( $attrs['url'] ) ? $attrs['url'] : '';
	$alt  = isset( $attrs['alt'] ) ? $attrs['alt'] : '';

	if ( $id && ! $url ) {
		$src = wp_get_attachment_image_url( $id, $size );
		if ( $src ) $url = $src;
	}

	if ( ! $url ) return '';

	$figure_cls = corsivo_theme_block_classes( 'wp-block-image', $attrs );
	if ( $size ) {
		$figure_cls .= ' size-' . esc_attr( $size );
	}

	$style_parts = array();
	if ( ! empty( $attrs['width'] ) && preg_match('/^(?:\d+(?:\.\d+)?(?:px|%|vw|rem|em)|auto)$/', $attrs['width']) ) $style_parts[] = 'width:' . $attrs['width'];
	if ( ! empty( $attrs['height'] ) && preg_match('/^(?:\d+(?:\.\d+)?(?:px|%|vh|rem|em)|auto)$/', $attrs['height']) ) $style_parts[] = 'height:' . $attrs['height'];
	$style_attr = $style_parts ? ' style="' . implode( ';', $style_parts ) . '"' : '';

	$img = '<img src="' . esc_url( $url ) . '" alt="' . esc_attr( $alt ) . '"' . ( $id ? ' class="wp-image-' . $id . '"' : '' ) . $style_attr . ' />';

	if ( ! empty( $attrs['href'] ) ) {
		$img = '<a href="' . esc_url( $attrs['href'] ) . '">' . $img . '</a>';
	}

	$caption = ! empty( $attrs['caption'] ) ? '<figcaption class="wp-element-caption">' . wp_kses_post($attrs['caption']) . '</figcaption>' : '';
	$html    = '<figure class="' . esc_attr( $figure_cls ) . '">' . $img . $caption . '</figure>';

	return render_block( array(
		'blockName'    => 'core/image',
		'attrs'        => array_filter( array(
			'id'        => $id ?: null,
			'sizeSlug'  => $size,
			'className' => ! empty( $attrs['style'] ) ? 'is-style-' . $attrs['style'] : null,
		) ),
		'innerHTML'    => $html,
		'innerContent' => array( $html ),
	) );
}

function render_core_heading( $text, $attrs = array() ) {
	$level   = isset( $attrs['level'] ) ? max( 1, min( 6, (int) $attrs['level'] ) ) : 2;
	$tag     = 'h' . $level;
	$classes = array( 'wp-block-heading' );

	if ( ! empty( $attrs['style'] ) ) $classes[] = 'is-style-' . sanitize_html_class( $attrs['style'] );
	if ( ! empty( $attrs['class'] ) ) $classes[] = $attrs['class'];
	if ( ! empty( $attrs['align'] ) ) $classes[] = 'has-text-align-' . sanitize_html_class( $attrs['align'] );

	$id_attr = ! empty( $attrs['id'] ) ? ' id="' . esc_attr( $attrs['id'] ) . '"' : '';
	$html    = '<' . $tag . ' class="' . esc_attr( implode( ' ', $classes ) ) . '"' . $id_attr . '>' . wp_kses_post($text) . '</' . $tag . '>';

	return render_block( array(
		'blockName'    => 'core/heading',
		'attrs'        => array_filter( array(
			'level'     => $level,
			'className' => ! empty( $attrs['style'] ) ? 'is-style-' . $attrs['style'] : null,
			'textAlign' => ! empty( $attrs['align'] ) ? $attrs['align'] : null,
		) ),
		'innerHTML'    => $html,
		'innerContent' => array( $html ),
	) );
}
function render_core_paragraph( $text, $attrs = array() ) {
	$classes = array();

	if ( ! empty( $attrs['style'] ) )    $classes[] = 'is-style-' . sanitize_html_class( $attrs['style'] );
	if ( ! empty( $attrs['class'] ) )    $classes[] = $attrs['class'];
	if ( ! empty( $attrs['align'] ) )    $classes[] = 'has-text-align-' . sanitize_html_class( $attrs['align'] );
	if ( ! empty( $attrs['fontSize'] ) ) $classes[] = 'has-' . sanitize_html_class( $attrs['fontSize'] ) . '-font-size';

	$cls_attr = $classes ? ' class="' . esc_attr( implode( ' ', $classes ) ) . '"' : '';
	$html     = '<p' . $cls_attr . '>' . wp_kses_post($text) . '</p>';

	return render_block( array(
		'blockName'    => 'core/paragraph',
		'attrs'        => array_filter( array(
			'className' => ! empty( $attrs['style'] ) ? 'is-style-' . $attrs['style'] : null,
			'align'     => ! empty( $attrs['align'] ) ? $attrs['align'] : null,
			'fontSize'  => ! empty( $attrs['fontSize'] ) ? $attrs['fontSize'] : null,
		) ),
		'innerHTML'    => $html,
		'innerContent' => array( $html ),
	) );
}

function render_core_separator( $attrs = array() ) {
	$classes = array( 'wp-block-separator', 'has-alpha-channel-opacity' );

	if ( ! empty( $attrs['style'] ) ) $classes[] = 'is-style-' . sanitize_html_class( $attrs['style'] );
	if ( ! empty( $attrs['class'] ) ) $classes[] = $attrs['class'];
	if ( ! empty( $attrs['align'] ) ) $classes[] = 'align' . sanitize_html_class( $attrs['align'] );

	$html = '<hr class="' . esc_attr( implode( ' ', $classes ) ) . '" />';

	return render_block( array(
		'blockName'    => 'core/separator',
		'attrs'        => array_filter( array(
			'className' => ! empty( $attrs['style'] ) ? 'is-style-' . $attrs['style'] : null,
		) ),
		'innerHTML'    => $html,
		'innerContent' => array( $html ),
	) );
}

function render_core_spacer( $attrs = array() ) {
	$height = !empty($attrs['height']) && preg_match('/^\d+(?:\.\d+)?(?:px|vh|rem|em|%)$/', $attrs['height']) ? $attrs['height'] : '50px';
	$cls    = 'wp-block-spacer';
	if ( ! empty( $attrs['class'] ) ) $cls .= ' ' . $attrs['class'];

	$html = '<div class="' . esc_attr( $cls ) . '" style="height:' . esc_attr( $height ) . '" aria-hidden="true"></div>';

	return render_block( array(
		'blockName'    => 'core/spacer',
		'attrs'        => array( 'height' => $height ),
		'innerHTML'    => $html,
		'innerContent' => array( $html ),
	) );
}

function render_core_search( $attrs = array() ) {
	$block_attrs = array(
		'label'      => isset( $attrs['label'] ) ? $attrs['label'] : 'Search',
		'showLabel'  => isset( $attrs['showLabel'] ) ? (bool) $attrs['showLabel'] : true,
		'buttonText' => isset( $attrs['buttonText'] ) ? $attrs['buttonText'] : 'Search',
	);

	if ( ! empty( $attrs['placeholder'] ) ) $block_attrs['placeholder'] = $attrs['placeholder'];
	if ( ! empty( $attrs['width'] ) )       $block_attrs['width']       = $attrs['width'];
	if ( ! empty( $attrs['style'] ) )       $block_attrs['className']   = 'is-style-' . $attrs['style'];
	if ( ! empty( $attrs['class'] ) )       $block_attrs['className']   = ( $block_attrs['className'] ?? '' ) . ' ' . $attrs['class'];

	// core/search is a dynamic block — render_block handles everything.
	return render_block( array(
		'blockName'    => 'core/search',
		'attrs'        => $block_attrs,
		'innerHTML'    => '',
		'innerContent' => array(),
	) );
}

function render_core_code( $code, $attrs = array() ) {
	$classes = array( 'wp-block-code' );

	if ( ! empty( $attrs['style'] ) ) $classes[] = 'is-style-' . sanitize_html_class( $attrs['style'] );
	if ( ! empty( $attrs['class'] ) ) $classes[] = $attrs['class'];

	$html = '<pre class="' . esc_attr( implode( ' ', $classes ) ) . '"><code>' . esc_html( $code ) . '</code></pre>';

	return render_block( array(
		'blockName'    => 'core/code',
		'attrs'        => array_filter( array(
			'className' => ! empty( $attrs['style'] ) ? 'is-style-' . $attrs['style'] : null,
		) ),
		'innerHTML'    => $html,
		'innerContent' => array( $html ),
	) );
}

function render_core_preformatted( $text, $attrs = array() ) {
	$classes = array( 'wp-block-preformatted' );

	if ( ! empty( $attrs['style'] ) ) $classes[] = 'is-style-' . sanitize_html_class( $attrs['style'] );
	if ( ! empty( $attrs['class'] ) ) $classes[] = $attrs['class'];

	$html = '<pre class="' . esc_attr( implode( ' ', $classes ) ) . '">' . esc_html($text) . '</pre>';

	return render_block( array(
		'blockName'    => 'core/preformatted',
		'attrs'        => array_filter( array(
			'className' => ! empty( $attrs['style'] ) ? 'is-style-' . $attrs['style'] : null,
		) ),
		'innerHTML'    => $html,
		'innerContent' => array( $html ),
	) );
}

function render_core_video( $attrs = array() ) {
	$id  = ! empty( $attrs['id'] ) ? (int) $attrs['id'] : 0;
	$src = ! empty( $attrs['src'] ) ? $attrs['src'] : '';

	if ( $id && ! $src ) {
		$src = wp_get_attachment_url( $id );
	}

	if ( ! $src ) return '';

	$figure_cls = corsivo_theme_block_classes( 'wp-block-video', $attrs );

	$video_attrs = array();
	if ( isset( $attrs['controls'] ) ? $attrs['controls'] : true ) $video_attrs[] = 'controls';
	if ( ! empty( $attrs['autoplay'] ) )    $video_attrs[] = 'autoplay';
	if ( ! empty( $attrs['loop'] ) )        $video_attrs[] = 'loop';
	if ( ! empty( $attrs['muted'] ) )       $video_attrs[] = 'muted';
	if ( ! empty( $attrs['playsinline'] ) ) $video_attrs[] = 'playsinline';

	$preload = ! empty( $attrs['preload'] ) ? ' preload="' . esc_attr( $attrs['preload'] ) . '"' : '';
	$poster  = ! empty( $attrs['poster'] )  ? ' poster="' . esc_url( $attrs['poster'] ) . '"'    : '';

	$video = '<video src="' . esc_url( $src ) . '"' . $poster . $preload . ' ' . implode( ' ', $video_attrs ) . '></video>';

	$caption = ! empty( $attrs['caption'] ) ? '<figcaption class="wp-element-caption">' . wp_kses_post($attrs['caption']) . '</figcaption>' : '';
	$html    = '<figure class="' . esc_attr( $figure_cls ) . '">' . $video . $caption . '</figure>';

	return render_block( array(
		'blockName'    => 'core/video',
		'attrs'        => array_filter( array(
			'id'        => $id ?: null,
			'className' => ! empty( $attrs['style'] ) ? 'is-style-' . $attrs['style'] : null,
		) ),
		'innerHTML'    => $html,
		'innerContent' => array( $html ),
	) );
}

function render_core_audio( $attrs = array() ) {
	$id  = ! empty( $attrs['id'] ) ? (int) $attrs['id'] : 0;
	$src = ! empty( $attrs['src'] ) ? $attrs['src'] : '';

	if ( $id && ! $src ) {
		$src = wp_get_attachment_url( $id );
	}

	if ( ! $src ) return '';

	$figure_cls = corsivo_theme_block_classes( 'wp-block-audio', $attrs );

	$audio_attrs = array( 'controls' );
	if ( ! empty( $attrs['autoplay'] ) ) $audio_attrs[] = 'autoplay';
	if ( ! empty( $attrs['loop'] ) )     $audio_attrs[] = 'loop';

	$preload = ! empty( $attrs['preload'] ) ? ' preload="' . esc_attr( $attrs['preload'] ) . '"' : '';

	$audio   = '<audio src="' . esc_url( $src ) . '"' . $preload . ' ' . implode( ' ', $audio_attrs ) . '></audio>';
	$caption = ! empty( $attrs['caption'] ) ? '<figcaption class="wp-element-caption">' . wp_kses_post($attrs['caption']) . '</figcaption>' : '';
	$html    = '<figure class="' . esc_attr( $figure_cls ) . '">' . $audio . $caption . '</figure>';

	return render_block( array(
		'blockName'    => 'core/audio',
		'attrs'        => array_filter( array(
			'id'        => $id ?: null,
			'className' => ! empty( $attrs['style'] ) ? 'is-style-' . $attrs['style'] : null,
		) ),
		'innerHTML'    => $html,
		'innerContent' => array( $html ),
	) );
}
