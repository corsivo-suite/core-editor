<?php

add_filter('corsivo_theme_fonts', function($fonts) {
    $fonts[] = array(
        'slug' => 'custom-font',
        'css' => "font-family: \"Custom Font\", sans-serif;\nfont-size: 16px;\nline-height: 1.5;",
        'name' => 'Custom Font'
    );
    return $fonts;
});

add_filter('corsivo_theme_colors', function($colors) {
    $colors[] = array(
        'slug' => 'custom-color',
        'color' => '#123456',
        'name' => 'Custom Color'
    );
    return $colors;
});

add_filter('corsivo_theme_custom_fonts', function($fonts) {
    $fonts[] = array(
        'url' => 'https://fonts.googleapis.com/css2?family=Custom+Font',
        'fonts' => array(
            array(
                'family' => 'Custom Font',
                'weights' => '400',
                'italic' => false,
                'varname' => 'custom-font'
            )
        )
    );
    return $fonts;
});

add_filter('corsivo_theme_install_fonts', function($fonts) {
    $fonts[] = array(
        'font-family' => 'Custom Font',
        'font-weight' => array(
            '400' => 'https://example.com/fonts/custom-font-regular.woff2'
        ),
        'format' => 'woff2'
    );
    return $fonts;
});

add_filter('corsivo_theme_breakpoints', function($breakpoints) {
    $breakpoints['m'] = 1024;
    $breakpoints['s'] = 768;
    $breakpoints['xs'] = 480;
    return $breakpoints;
});
