<?php
if (!defined('ABSPATH')) exit;

function corsivo_theme_functional_pages_post_states( $post_states, $post ) {
	$corsivo_theme_pages = apply_filters('corsivo_theme_pages',array());
    $corsivo_theme_config = corsivo_theme_get_settings();
	$current_lang = corsivo_theme_current_language();

    if(!isset($corsivo_theme_config['functional_pages'])) return $post_states;
	foreach($corsivo_theme_pages as $slug => $label) {
        if (isset($corsivo_theme_config['functional_pages'][$slug][$current_lang]) && ($corsivo_theme_config['functional_pages'][$slug][$current_lang] == $post->ID)) {
            $post_states[] = $label;
        }
	}
	
	return $post_states;
}
add_filter( 'display_post_states', 'corsivo_theme_functional_pages_post_states', 10, 2 );
