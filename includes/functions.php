<?php
if (!defined('ABSPATH')) exit;

	function corsivo_theme_rewire_block_style_variation_global_dependency() {
		$styles = wp_styles();
		$variation_styles = $styles->query('block-style-variation-styles', 'registered');

		if (
			!$variation_styles
			|| $styles->query('global-styles', 'registered')
			|| !$styles->query('wp-global-styles-placeholder', 'registered')
		) return;

		$dependency_index = array_search('global-styles', $variation_styles->deps, true);
		if (false === $dependency_index) return;

		$variation_styles->deps[$dependency_index] = 'wp-global-styles-placeholder';
	}
	add_action('wp_print_styles', 'corsivo_theme_rewire_block_style_variation_global_dependency', 0);

	function corsivo_theme_render_svg($url, $label = '') {
		$url = esc_url_raw($url, array('http', 'https'));
		$fallback = $url ? '<img src="' . esc_url($url) . '" alt="' . esc_attr($label) . '">' : '';
		if (!$url) return '';

		$response = wp_safe_remote_get($url, array(
			'timeout' => 5,
			'redirection' => 2,
			'limit_response_size' => 512 * KB_IN_BYTES,
		));
		if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) return $fallback;

		$svg = wp_remote_retrieve_body($response);
		if (!is_string($svg) || stripos($svg, '<svg') === false) return $fallback;

		$allowed = array(
			'svg' => array('xmlns' => true, 'viewbox' => true, 'width' => true, 'height' => true, 'role' => true, 'aria-hidden' => true, 'aria-label' => true, 'focusable' => true, 'fill' => true, 'stroke' => true, 'class' => true),
			'g' => array('fill' => true, 'stroke' => true, 'stroke-width' => true, 'transform' => true, 'class' => true, 'clip-path' => true, 'mask' => true),
			'path' => array('d' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true, 'stroke-linecap' => true, 'stroke-linejoin' => true, 'fill-rule' => true, 'clip-rule' => true, 'transform' => true, 'class' => true),
			'circle' => array('cx' => true, 'cy' => true, 'r' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true, 'class' => true),
			'ellipse' => array('cx' => true, 'cy' => true, 'rx' => true, 'ry' => true, 'fill' => true, 'stroke' => true, 'class' => true),
			'rect' => array('x' => true, 'y' => true, 'width' => true, 'height' => true, 'rx' => true, 'ry' => true, 'fill' => true, 'stroke' => true, 'class' => true),
			'line' => array('x1' => true, 'y1' => true, 'x2' => true, 'y2' => true, 'stroke' => true, 'stroke-width' => true, 'class' => true),
			'polyline' => array('points' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true, 'class' => true),
			'polygon' => array('points' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true, 'class' => true),
			'defs' => array(),
			'clippath' => array('id' => true),
			'mask' => array('id' => true),
			'lineargradient' => array('id' => true, 'x1' => true, 'y1' => true, 'x2' => true, 'y2' => true, 'gradientunits' => true),
			'stop' => array('offset' => true, 'stop-color' => true, 'stop-opacity' => true),
			'title' => array(),
			'desc' => array(),
		);

		$sanitized = wp_kses($svg, $allowed);
		return stripos($sanitized, '<svg') !== false ? $sanitized : $fallback;
	}

	/**
	 * Extract a CSS property value from a raw CSS string.
	 * e.g. corsivo_theme_parse_css_property('font-size: 1.2rem; line-height: 1.5;', 'font-size') => '1.2rem'
	 */
	function corsivo_theme_parse_css_property($css_string, $property) {
		// Anchor on start-or-semicolon so e.g. 'color' never matches inside
		// 'background-color' / 'border-color'.
		if (preg_match('/(?:^|;)\s*' . preg_quote($property, '/') . '\s*:\s*([^;]+)/', $css_string, $matches)) {
			return trim($matches[1]);
		}
		return null;
	}

	/**
	 * Whether a CSS value is a plain color (hex/rgb/hsl/var/named) rather than a
	 * gradient, image or multi-layer background that theme.json color.background
	 * cannot represent.
	 */
	function corsivo_theme_is_plain_color($value) {
		$v = strtolower(trim($value));
		if ($v === '') return false;
		if (strpos($v, 'gradient') !== false || strpos($v, 'url(') !== false) return false;
		if ($v[0] === '#') return true;
		if (strpos($v, 'rgb') === 0 || strpos($v, 'hsl') === 0 || strpos($v, 'var(') === 0) return true;
		return (bool) preg_match('/^[a-z]+$/', $v);
	}

	function corsivo_theme_check_field($field, $value) {

		switch($field):
			case 'letter-spacing':
				return $value !== '0rem';

			case 'scope':
				return false;

			default:
				return true;
		endswitch;
	}

	function corsivo_theme_register_block_style_once($block_name, $style) {
		if (
			class_exists('WP_Block_Styles_Registry')
			&& WP_Block_Styles_Registry::get_instance()->is_registered($block_name, $style['name'])
		) {
			return false;
		}

		return register_block_style($block_name, $style);
	}

	function corsivo_theme_register_font_style($font, $name, $scopes = array()) {

		if(isset($font['scope'])) {
			if($font['scope'] === true) {
				$scopes = array('heading','paragraph');
			}
			else {
				$scopes = is_array($font['scope'])? $font['scope'] : array($font['scope']);
			}

			unset($font['scope']);
		}

		if(in_array('heading',$scopes)) {
			$font = apply_filters('corsivo_theme_edit_font_heading',$font);
			corsivo_theme_register_block_style_once(
				'core/heading',
					array(
						'name'          => 'font-'.$font['slug'],
						'label'         => $name,
					)
			);
		}
		if(in_array('paragraph',$scopes)) {
			$font = apply_filters('corsivo_theme_edit_font_paragraph',$font);
			corsivo_theme_register_block_style_once(
				'core/paragraph',
					array(
						'name'          => 'font-'.$font['slug'],
						'label'         => $name,
					)
			);
		}
		if(empty($scopes)) {
			$font = apply_filters('corsivo_theme_edit_font',$font);
			corsivo_theme_register_block_style_once(
				'core/paragraph',
					array(
						'name'          => 'font-'.$font['slug'],
						'label'         => $name,
					)
			);
			corsivo_theme_register_block_style_once(
				'core/heading',
					array(
						'name'          => 'font-'.$font['slug'],
						'label'         => $name,
					)
			);
		}
	}

	/**
	 * Recursively deep-merge two associative arrays. $b values override $a.
	 */
	function corsivo_theme_deep_merge($a, $b) {
		foreach ($b as $key => $value) {
			if (is_array($value) && isset($a[$key]) && is_array($a[$key])) {
				$a[$key] = corsivo_theme_deep_merge($a[$key], $value);
			} else {
				$a[$key] = $value;
			}
		}
		return $a;
	}

	/**
	 * Resolve a color slug to a CSS custom property.
	 */
	function corsivo_theme_color_var($slug) {
		if (empty($slug)) return '';
		// Already a var() or hex value — pass through
		if (strpos($slug, 'var(') === 0 || strpos($slug, '#') === 0 || strpos($slug, 'rgb') === 0) {
			return $slug;
		}
		return 'var(--wp--preset--color--' . $slug . ')';
	}

	/**
	 * Look up a font slug in the fonts array and return all typography
	 * properties as a theme.json-compatible array.
	 * e.g. { fontFamily: 'var(--wp--preset--font-family--slug)', fontSize: '5.5rem', lineHeight: '1.5', ... }
	 */
	function corsivo_theme_resolve_font_typography($slug, $fonts) {
		if (empty($slug) || empty($fonts)) return array();

		$typography = array(
			'fontFamily' => 'var(--wp--preset--font-family--' . $slug . ')',
		);

		foreach ($fonts as $font) {
			if (!isset($font['slug']) || $font['slug'] !== $slug) continue;

			$css = isset($font['css']) ? $font['css'] : '';
			$map = array(
				'font-size'       => 'fontSize',
				'line-height'     => 'lineHeight',
				'font-weight'     => 'fontWeight',
				'font-style'      => 'fontStyle',
				'letter-spacing'  => 'letterSpacing',
				'text-transform'  => 'textTransform',
				'text-decoration' => 'textDecoration',
			);
			foreach ($map as $css_prop => $json_prop) {
				$val = corsivo_theme_parse_css_property($css, $css_prop);
				if ($val) {
					$typography[$json_prop] = $val;
				}
			}
			break;
		}

		return $typography;
	}

	/**
	 * Parse a raw CSS string into theme.json-compatible structure.
	 * Maps CSS properties to their theme.json equivalents.
	 * e.g. "background-color: red; font-size: 1rem;" → { color: { background: 'red' }, typography: { fontSize: '1rem' } }
	 */
	function corsivo_theme_css_to_theme_json($css_string) {
		if (empty($css_string)) return array();
		$result = array();

		preg_match_all('/([a-z-]+)\s*:\s*([^;]+);?/i', $css_string, $matches, PREG_SET_ORDER);

		foreach ($matches as $m) {
			$prop = strtolower(trim($m[1]));
			$value = trim($m[2]);
			if ($value === '') continue;

			switch ($prop) {
				case 'background-color':
					$result['color']['background'] = $value;
					break;
				case 'background':
					// Only a plain color maps to theme.json color.background.
					// Gradients/images are left to the inline-CSS path (which
					// emits the raw `background:` rule verbatim).
					if (corsivo_theme_is_plain_color($value)) {
						$result['color']['background'] = $value;
					}
					break;
				case 'color':
					$result['color']['text'] = $value;
					break;
				case 'font-family':
					$result['typography']['fontFamily'] = $value;
					break;
				case 'font-size':
					$result['typography']['fontSize'] = $value;
					break;
				case 'font-weight':
					$result['typography']['fontWeight'] = $value;
					break;
				case 'font-style':
					$result['typography']['fontStyle'] = $value;
					break;
				case 'line-height':
					$result['typography']['lineHeight'] = $value;
					break;
				case 'letter-spacing':
					$result['typography']['letterSpacing'] = $value;
					break;
				case 'text-transform':
					$result['typography']['textTransform'] = $value;
					break;
				case 'text-decoration':
					$result['typography']['textDecoration'] = $value;
					break;
				case 'border-radius':
					$result['border']['radius'] = $value;
					break;
				case 'border-style':
					$result['border']['style'] = $value;
					break;
				case 'border-width':
					$result['border']['width'] = $value;
					break;
				case 'border-color':
					$result['border']['color'] = $value;
					break;
				case 'border':
					// Parse shorthand: border: <width> <style> <color>
					$border_kw = array('none','solid','dashed','dotted','double','groove','ridge','inset','outset','hidden');
					if (strtolower($value) === 'none') {
						$result['border']['style'] = 'none';
						$result['border']['width'] = '0';
					} else {
						$bparts = preg_split('/\s+/', $value, 3);
						foreach ($bparts as $bp) {
							if (preg_match('/^(\d|thin|medium|thick)/i', $bp)) {
								$result['border']['width'] = $bp;
							} elseif (in_array(strtolower($bp), $border_kw, true)) {
								$result['border']['style'] = $bp;
							} else {
								$result['border']['color'] = $bp;
							}
						}
					}
					break;
				case 'padding':
					$parts = preg_split('/\s+/', $value);
					$c = count($parts);
					if ($c === 1) {
						$result['spacing']['padding'] = array('top' => $parts[0], 'right' => $parts[0], 'bottom' => $parts[0], 'left' => $parts[0]);
					} elseif ($c === 2) {
						$result['spacing']['padding'] = array('top' => $parts[0], 'right' => $parts[1], 'bottom' => $parts[0], 'left' => $parts[1]);
					} elseif ($c === 3) {
						$result['spacing']['padding'] = array('top' => $parts[0], 'right' => $parts[1], 'bottom' => $parts[2], 'left' => $parts[1]);
					} elseif ($c >= 4) {
						$result['spacing']['padding'] = array('top' => $parts[0], 'right' => $parts[1], 'bottom' => $parts[2], 'left' => $parts[3]);
					}
					break;
				case 'padding-top':
					$result['spacing']['padding']['top'] = $value;
					break;
				case 'padding-right':
					$result['spacing']['padding']['right'] = $value;
					break;
				case 'padding-bottom':
					$result['spacing']['padding']['bottom'] = $value;
					break;
				case 'padding-left':
					$result['spacing']['padding']['left'] = $value;
					break;
				case 'margin':
					$parts = preg_split('/\s+/', $value);
					$c = count($parts);
					if ($c === 1) {
						$result['spacing']['margin'] = array('top' => $parts[0], 'right' => $parts[0], 'bottom' => $parts[0], 'left' => $parts[0]);
					} elseif ($c === 2) {
						$result['spacing']['margin'] = array('top' => $parts[0], 'right' => $parts[1], 'bottom' => $parts[0], 'left' => $parts[1]);
					} elseif ($c >= 4) {
						$result['spacing']['margin'] = array('top' => $parts[0], 'right' => $parts[1], 'bottom' => $parts[2], 'left' => $parts[3]);
					}
					break;
				case 'margin-top':
					$result['spacing']['margin']['top'] = $value;
					break;
				case 'margin-right':
					$result['spacing']['margin']['right'] = $value;
					break;
				case 'margin-bottom':
					$result['spacing']['margin']['bottom'] = $value;
					break;
				case 'margin-left':
					$result['spacing']['margin']['left'] = $value;
					break;
				case 'box-shadow':
					$result['shadow'] = $value;
					break;
			}
		}

		return $result;
	}

	function corsivo_theme_font_feature_settings($features) {
		if (!is_array($features)) {
			return '';
		}

		$settings = array();
		foreach ($features as $feature) {
			$feature = preg_replace('/[^a-z0-9]/i', '', (string) $feature);
			if ($feature !== '') {
				$settings[] = '"' . strtolower($feature) . '"';
			}
		}

		return implode(',', array_values(array_unique($settings)));
	}

	function corsivo_theme_build_install_font_faces($font) {
		if (!is_array($font) || empty($font['font-family'])) {
			return array();
		}

		$font_faces = array();
		$font_display = !empty($font['font-display']) ? sanitize_key($font['font-display']) : '';
		$style_weights = !empty($font['font-style-weight']) && is_array($font['font-style-weight'])
			? $font['font-style-weight']
			: array();
		$font_feature_settings = corsivo_theme_font_feature_settings($font['features'] ?? array());

		if (!empty($font['font-weight']) && is_array($font['font-weight'])) {
			foreach ($font['font-weight'] as $weight => $url) {
				if (empty($url) || !is_string($url)) continue;
				$face = array(
					'fontFamily' => $font['font-family'],
					'fontWeight' => preg_replace('/[^0-9A-Za-z -]/', '', (string) $weight),
					'fontStyle'  => 'normal',
					'src'        => array($url),
				);
				if ($font_display) {
					$face['fontDisplay'] = $font_display;
				}
				if ($font_feature_settings) {
					$face['fontFeatureSettings'] = $font_feature_settings;
				}
				$font_faces[] = $face;
			}
		}

		if (!empty($font['font-style']) && is_array($font['font-style'])) {
			foreach ($font['font-style'] as $style => $url) {
				if (empty($url) || !is_string($url)) continue;
				$font_weight = isset($style_weights[$style])
					? preg_replace('/[^0-9A-Za-z -]/', '', (string) $style_weights[$style])
					: '400';
				$face = array(
					'fontFamily' => $font['font-family'],
					'fontWeight' => $font_weight ?: '400',
					'fontStyle'  => sanitize_key($style),
					'src'        => array($url),
				);
				if ($font_display) {
					$face['fontDisplay'] = $font_display;
				}
				if ($font_feature_settings) {
					$face['fontFeatureSettings'] = $font_feature_settings;
				}
				$font_faces[] = $face;
			}
		}

		return $font_faces;
	}

	/**
	 * Inject colors, fonts, and global styles into theme.json at runtime.
	 * Uses wp_theme_json_data_theme filter.
	 */
	function corsivo_theme_inject_theme_json($theme_json) {
		$colors        = apply_filters('corsivo_theme_colors', array());
		$fonts         = apply_filters('corsivo_theme_fonts', array());
		$global_styles = apply_filters('corsivo_theme_global_styles', array());

		$new_data = array(
			'version'  => 2,
			'settings' => array(),
		);

		// --- Color palette ---
		$palette = array();
		foreach($colors as $color) {
			$palette[] = array(
				'slug'  => $color['slug'],
				'color' => $color['color'],
				'name'  => isset($color['name']) ? $color['name'] : $color['slug'],
			);
		}
		$new_data['settings']['color'] = array(
			'defaultPalette' => false,
			'palette'        => $palette,
		);

		// --- Font families ---
		// Register a font-family preset for EVERY typography style slug.
		// Multiple styles may share the same font-family value (e.g. a
		// "paragraph" and a "heading" style both using Bricolage Grotesque).
		// WordPress simply generates duplicate CSS custom properties pointing
		// to the same value — that is perfectly valid and necessary so that
		// Global / Style Templates can reference any slug.
		if(!empty($fonts)) {
			$seen_slugs = array();
			$font_families = array();
			foreach($fonts as $font) {
				$fontFamily = null;
				if (isset($font['css']) && !empty($font['css'])) {
					$fontFamily = corsivo_theme_parse_css_property($font['css'], 'font-family');
				}
				if(!$fontFamily) continue;
				// Deduplicate only by slug (not by font-family value)
				if(in_array($font['slug'], $seen_slugs)) continue;
				$seen_slugs[] = $font['slug'];

				$font_families[] = array(
					'slug'       => $font['slug'],
					'name'       => isset($font['name']) ? $font['name'] : $font['slug'],
					'fontFamily' => $fontFamily,
				);
			}
			// Register font-family presets from Google Fonts with varname
			$custom_fonts_raw = apply_filters('corsivo_theme_custom_fonts', array());
			foreach ($custom_fonts_raw as $entry) {
				if (!is_array($entry) || empty($entry['fonts'])) continue;
				foreach ($entry['fonts'] as $gfont) {
					if (empty($gfont['varname']) || empty($gfont['family'])) continue;
					$slug = sanitize_title($gfont['varname']);
					if (in_array($slug, $seen_slugs)) continue;
					$seen_slugs[] = $slug;
					$font_families[] = array(
						'slug'       => $slug,
						'name'       => $gfont['family'],
						'fontFamily' => $gfont['family'],
					);
				}
			}

			if(!empty($font_families)) {
				$new_data['settings']['typography'] = array(
					'fontFamilies' => $font_families,
				);
			}
		}

		// --- Font face declarations from install_fonts ---
		$install_fonts = apply_filters('corsivo_theme_install_fonts', array());
		if (!empty($install_fonts)) {
			if (!isset($new_data['settings']['typography'])) {
				$new_data['settings']['typography'] = array('fontFamilies' => array());
			}
			if (!isset($new_data['settings']['typography']['fontFamilies'])) {
				$new_data['settings']['typography']['fontFamilies'] = array();
			}

			foreach ($install_fonts as $install_font) {
				if (empty($install_font['font-family'])) continue;

				$family_name = $install_font['font-family'];
				$family_slug = !empty($install_font['varname'])
					? sanitize_title($install_font['varname'])
					: sanitize_title($family_name);
				$font_faces = corsivo_theme_build_install_font_faces($install_font);

				if (empty($font_faces)) continue;

				// Check if this font-family already exists in fontFamilies
				$found = false;
				foreach ($new_data['settings']['typography']['fontFamilies'] as &$existing) {
					if (isset($existing['slug']) && sanitize_title($existing['slug']) === $family_slug) {
						$existing['fontFace'] = $font_faces;
						$found = true;
						break;
					}
				}
				unset($existing);

				if (!$found) {
					$entry_data = array(
						'slug'       => $family_slug,
						'name'       => $family_name,
						'fontFamily' => $family_name,
						'fontFace'   => $font_faces,
					);
					$new_data['settings']['typography']['fontFamilies'][] = $entry_data;
				}
			}
		}

		// --- Spacing presets & layout ---
		$spacing_config = apply_filters('corsivo_theme_spacing', array());
		if (!empty($spacing_config)) {
			// Layout settings (contentSize, wideSize)
			if (!empty($spacing_config['contentSize']) || !empty($spacing_config['wideSize'])) {
				$new_data['settings']['layout'] = array();
				if (!empty($spacing_config['contentSize'])) {
					$new_data['settings']['layout']['contentSize'] = $spacing_config['contentSize'];
				}
				if (!empty($spacing_config['wideSize'])) {
					$new_data['settings']['layout']['wideSize'] = $spacing_config['wideSize'];
				}
			}

			// Spacing sizes presets
			if (!empty($spacing_config['spacingSizes']) && is_array($spacing_config['spacingSizes'])) {
				$sizes = array();
				foreach ($spacing_config['spacingSizes'] as $preset) {
					if (empty($preset['slug']) || empty($preset['size'])) continue;
					$sizes[] = array(
						'slug' => $preset['slug'],
						'size' => $preset['size'],
						'name' => isset($preset['name']) ? $preset['name'] : $preset['slug'],
					);
				}
				if (!empty($sizes)) {
					$new_data['settings']['spacing'] = array(
						'spacingSizes' => $sizes,
					);
				}
			}

			// Block gap (global)
			if (!empty($spacing_config['blockGap'])) {
				if (!isset($new_data['styles'])) {
					$new_data['styles'] = array();
				}
				$new_data['styles']['spacing'] = array(
					'blockGap' => $spacing_config['blockGap'],
				);
			}

			// Root padding (viewport edge → content)
			$root_padding = array();
			if (!empty($spacing_config['rootPaddingLeft'])) {
				$root_padding['left'] = $spacing_config['rootPaddingLeft'];
			}
			if (!empty($spacing_config['rootPaddingRight'])) {
				$root_padding['right'] = $spacing_config['rootPaddingRight'];
			}
			if (!empty($root_padding)) {
				if (!isset($new_data['styles'])) {
					$new_data['styles'] = array();
				}
				if (!isset($new_data['styles']['spacing'])) {
					$new_data['styles']['spacing'] = array();
				}
				$new_data['styles']['spacing']['padding'] = array_merge(
					$new_data['styles']['spacing']['padding'] ?? array(),
					$root_padding
				);
			}
		}

		// --- Global styles (body, heading, link) ---
		// When a font slug is selected, resolve ALL its typography properties
		// (font-family, font-size, line-height, font-weight, etc.) from the
		// matching typography style — not just the font-family preset variable.
		if(!empty($global_styles)) {
			$styles = array();

			// Body
			$body = isset($global_styles['body']) ? $global_styles['body'] : array();
			if(!empty($body['background'])) {
				$styles['color']['background'] = 'var(--wp--preset--color--' . $body['background'] . ')';
			}
			if(!empty($body['color'])) {
				$styles['color']['text'] = 'var(--wp--preset--color--' . $body['color'] . ')';
			}
			if(!empty($body['font'])) {
				$body_typo = corsivo_theme_resolve_font_typography($body['font'], $fonts);
				if (!empty($body_typo)) {
					$styles['typography'] = array_merge(
						isset($styles['typography']) ? $styles['typography'] : array(),
						$body_typo
					);
				}
			}

			// Heading element
			$heading = isset($global_styles['heading']) ? $global_styles['heading'] : array();
			if(!empty($heading['font']) || !empty($heading['color']) || !empty($heading['fontWeight'])) {
				$heading_el = array();
				if(!empty($heading['font'])) {
					$heading_typo = corsivo_theme_resolve_font_typography($heading['font'], $fonts);
					if (!empty($heading_typo)) {
						$heading_el['typography'] = $heading_typo;
					}
				}
				if(!empty($heading['color'])) {
					$heading_el['color']['text'] = 'var(--wp--preset--color--' . $heading['color'] . ')';
				}
				// Explicit fontWeight from the Global template overrides the one from the font style
				if(!empty($heading['fontWeight'])) {
					$heading_el['typography']['fontWeight'] = $heading['fontWeight'];
				}
				$styles['elements']['heading'] = $heading_el;
			}

			// Link element
			$link = isset($global_styles['link']) ? $global_styles['link'] : array();
			if(!empty($link['color']) || !empty($link['hoverColor'])) {
				$link_el = array();
				if(!empty($link['color'])) {
					$link_el['color']['text'] = 'var(--wp--preset--color--' . $link['color'] . ')';
				}
				if(!empty($link['hoverColor'])) {
					$link_el[':hover']['color']['text'] = 'var(--wp--preset--color--' . $link['hoverColor'] . ')';
				}
				$styles['elements']['link'] = $link_el;
			}

			if(!empty($styles)) {
				$new_data['styles'] = array_replace_recursive(
					isset($new_data['styles']) ? $new_data['styles'] : array(),
					$styles
				);
			}
		}

		// --- Step 6: Default font in theme.json ---
		if (empty($global_styles['body']['font'])) {
			foreach ($fonts as $font) {
				if (!empty($font['default'])) {
					$defaultFamily = null;
					if (isset($font['css']) && !empty($font['css'])) {
						$defaultFamily = corsivo_theme_parse_css_property($font['css'], 'font-family');
					}
					if ($defaultFamily) {
						if (!isset($new_data['styles'])) $new_data['styles'] = array();
						if (!isset($new_data['styles']['typography'])) $new_data['styles']['typography'] = array();
						$new_data['styles']['typography']['fontFamily'] = $defaultFamily;
					}
					break;
				}
			}
		}

		// --- Font styles → theme.json block variations (WP 6.6+) ---
		if (!empty($fonts)) {
			if (!isset($new_data['styles'])) $new_data['styles'] = array();
			if (!isset($new_data['styles']['blocks'])) $new_data['styles']['blocks'] = array();

			foreach ($fonts as $font) {
				if (empty($font['slug']) || empty($font['css'])) continue;

				$variation_json = corsivo_theme_css_to_theme_json($font['css']);
				if (empty($variation_json)) continue;

				// Determine scope (paragraph, heading, or both)
				$blocks = array();
				$scope = isset($font['scope']) ? $font['scope'] : true;
				if ($scope === true || $scope === 'paragraph' || (is_array($scope) && in_array('paragraph', $scope))) {
					$blocks[] = 'core/paragraph';
				}
				if ($scope === true || $scope === 'heading' || (is_array($scope) && in_array('heading', $scope))) {
					$blocks[] = 'core/heading';
				}
				if (empty($blocks)) {
					$blocks = array('core/paragraph', 'core/heading');
				}

				$var_name = 'font-' . $font['slug'];
				foreach ($blocks as $block) {
					if (!isset($new_data['styles']['blocks'][$block])) {
						$new_data['styles']['blocks'][$block] = array();
					}
					if (!isset($new_data['styles']['blocks'][$block]['variations'])) {
						$new_data['styles']['blocks'][$block]['variations'] = array();
					}
					$new_data['styles']['blocks'][$block]['variations'][$var_name] = $variation_json;
				}
			}
		}

		// --- Core Components (elements + blocks) ---
		$core_components = apply_filters('corsivo_theme_core_components', array());

		if (!empty($core_components)) {
			if (!isset($new_data['styles'])) {
				$new_data['styles'] = array();
			}

			// Elements (button, link, heading, h1-h6, caption, cite)
			if (!empty($core_components['elements']) && is_array($core_components['elements'])) {
				if (!isset($new_data['styles']['elements'])) {
					$new_data['styles']['elements'] = array();
				}
				foreach ($core_components['elements'] as $element_name => $element_data) {
					if (!is_array($element_data)) continue;
					$element_json = array();

					if (isset($element_data['css']) && is_string($element_data['css']) && !empty($element_data['css'])) {
						$element_json = corsivo_theme_css_to_theme_json($element_data['css']);
					}

					// Pseudo-states (:hover, :focus, etc.)
					foreach (array(':hover', ':focus', ':active', ':focus-visible') as $pseudo) {
						if (!empty($element_data[$pseudo]) && is_string($element_data[$pseudo])) {
							$pseudo_json = corsivo_theme_css_to_theme_json($element_data[$pseudo]);
							if (!empty($pseudo_json)) {
								$element_json[$pseudo] = $pseudo_json;
							}
						}
					}

					if (!empty($element_json)) {
						$new_data['styles']['elements'][$element_name] = isset($new_data['styles']['elements'][$element_name])
							? corsivo_theme_deep_merge($new_data['styles']['elements'][$element_name], $element_json)
							: $element_json;
					}
				}
			}

			// Block style variations → styles.blocks.*.variations.* (WP 6.6+)
			// Parses CSS from blockStyles into theme.json structure so the
			// editor can natively understand them. This includes core/button:
			// WP 6.6+ correctly maps variation properties to inner elements.
			// Inline CSS in corsivo_theme_enqueue_cc_block_styles_css() reinforces on frontend.

			if (!empty($core_components['blockStyles']) && is_array($core_components['blockStyles'])) {
				if (!isset($new_data['styles']['blocks'])) {
					$new_data['styles']['blocks'] = array();
				}

				// Track whether we found a default button style
				$default_button_css = '';
				$default_button_hover = '';

				foreach ($core_components['blockStyles'] as $bs) {
					if (empty($bs['target']) || empty($bs['slug'])) continue;
					if (!empty($bs['hideInEditor'])) continue;
					if (array_key_exists('register', $bs) && !$bs['register']) continue;

					// Resolve inheritance chain for theme.json
					$resolved_css = corsivo_theme_resolve_inherits_css($bs, $fonts, $core_components['blockStyles']);

					$variation_json = array();
					if (!empty($resolved_css)) {
						$variation_json = corsivo_theme_css_to_theme_json($resolved_css);
					}
					// Resolve pseudo-states through inheritance chain
					$resolved_hover = corsivo_theme_resolve_inherits_pseudo($bs, ':hover', $fonts, $core_components['blockStyles']);
					if (!empty($resolved_hover)) {
						$hover_json = corsivo_theme_css_to_theme_json($resolved_hover);
						if (!empty($hover_json)) {
							$variation_json[':hover'] = $hover_json;
						}
					}

					if (!empty($variation_json)) {
						if (!isset($new_data['styles']['blocks'][$bs['target']])) {
							$new_data['styles']['blocks'][$bs['target']] = array();
						}
						if (!isset($new_data['styles']['blocks'][$bs['target']]['variations'])) {
							$new_data['styles']['blocks'][$bs['target']]['variations'] = array();
						}
						$new_data['styles']['blocks'][$bs['target']]['variations'][$bs['slug']] = $variation_json;
					}

					// If this is the default button style, capture its resolved CSS + hover
					// so we can apply it as the base button element styling.
					// First default wins, so the base override is deterministic.
					if ($bs['target'] === 'core/button' && !empty($bs['isDefault']) && empty($default_button_css) && !empty($resolved_css)) {
						$default_button_css = $resolved_css;
						$default_button_hover = $resolved_hover;
					}
				}

				// When a button style is marked as default, override WP's
				// built-in button appearance (black bg, rounded corners).
				// Inject into styles.elements.button so it becomes the base
				// for ALL buttons — not just those with the variation class.
				if (!empty($default_button_css)) {
					$base_button_json = corsivo_theme_css_to_theme_json($default_button_css);
					if (!empty($base_button_json)) {
						// Add :hover from inherited chain
						if (!empty($default_button_hover)) {
							$hover_json = corsivo_theme_css_to_theme_json($default_button_hover);
							if (!empty($hover_json)) {
								$base_button_json[':hover'] = $hover_json;
							}
						}
						if (!isset($new_data['styles']['elements'])) {
							$new_data['styles']['elements'] = array();
						}
						$new_data['styles']['elements']['button'] = array_replace_recursive(
							isset($new_data['styles']['elements']['button']) ? $new_data['styles']['elements']['button'] : array(),
							$base_button_json
						);
					}
				}
			}
		}

		return $theme_json->update_with($new_data);
	}
	add_filter('wp_theme_json_data_theme', 'corsivo_theme_inject_theme_json');

	/**
	 * Configurator data merge filters (priority 20).
	 * corsivo-theme.json "configurator" data wins over theme filters (priority 10).
	 * If configurator data is empty, theme filter data passes through.
	 */
	add_filter('corsivo_theme_fonts', 'corsivo_theme_merge_fonts', 20);
	function corsivo_theme_merge_fonts($theme_fonts) {
		$config = corsivo_theme_get_settings();
		if (!empty($config['configurator']['fonts'])) {
			return $config['configurator']['fonts'];
		}
		return $theme_fonts;
	}

	add_filter('corsivo_theme_colors', 'corsivo_theme_merge_colors', 20);
	function corsivo_theme_merge_colors($theme_colors) {
		$config = corsivo_theme_get_settings();
		if (!empty($config['configurator']['colors'])) {
			return $config['configurator']['colors'];
		}
		return $theme_colors;
	}

	add_filter('corsivo_theme_breakpoints', 'corsivo_theme_merge_breakpoints', 20);
	function corsivo_theme_merge_breakpoints($theme_breakpoints) {
		$config = corsivo_theme_get_settings();
		if (!empty($config['configurator']['breakpoints'])) {
			return (array) $config['configurator']['breakpoints'];
		}
		return $theme_breakpoints;
	}

	add_filter('corsivo_theme_custom_fonts', 'corsivo_theme_merge_custom_fonts', 20);
	function corsivo_theme_merge_custom_fonts($theme_custom_fonts) {
		$config = corsivo_theme_get_settings();
		if (!empty($config['configurator']['custom_fonts'])) {
			return $config['configurator']['custom_fonts'];
		}
		return $theme_custom_fonts;
	}

	add_filter('corsivo_theme_install_fonts', 'corsivo_theme_merge_install_fonts', 20);
	function corsivo_theme_merge_install_fonts($theme_install_fonts) {
		$config = corsivo_theme_get_settings();
		if (!empty($config['configurator']['install_fonts'])) {
			return $config['configurator']['install_fonts'];
		}
		return $theme_install_fonts;
	}

	add_filter('corsivo_theme_pages', 'corsivo_theme_merge_pages', 20);
	function corsivo_theme_merge_pages($theme_pages) {
		$config = corsivo_theme_get_settings();
		if (!empty($config['configurator']['functional_pages'])) {
			foreach ($config['configurator']['functional_pages'] as $fp) {
				if (!empty($fp['slug']) && !empty($fp['label'])) {
					$theme_pages[$fp['slug']] = $fp['label'];
				}
			}
		}
		return $theme_pages;
	}

	add_filter('corsivo_theme_global_styles', 'corsivo_theme_merge_global_styles', 20);
	function corsivo_theme_merge_global_styles($theme_global_styles) {
		$config = corsivo_theme_get_settings();
		if (!empty($config['configurator']['global_styles'])) {
			return (array) $config['configurator']['global_styles'];
		}
		return $theme_global_styles;
	}

	add_filter('corsivo_theme_context_styles', 'corsivo_theme_merge_context_styles', 20);
	function corsivo_theme_merge_context_styles($theme_context_styles) {
		$config = corsivo_theme_get_settings();
		if (!empty($config['configurator']['context_styles'])) {
			return $config['configurator']['context_styles'];
		}
		return $theme_context_styles;
	}

	add_filter('corsivo_theme_style_templates', 'corsivo_theme_merge_style_templates', 20);
	function corsivo_theme_merge_style_templates($theme_style_templates) {
		$config = corsivo_theme_get_settings();
		if (!empty($config['configurator']['style_templates'])) {
			return $config['configurator']['style_templates'];
		}
		return $theme_style_templates;
	}

	add_filter('corsivo_theme_core_components', 'corsivo_theme_merge_core_components', 20);
	function corsivo_theme_merge_core_components($theme_core_components) {
		$config = corsivo_theme_get_settings();
		if (!empty($config['configurator']['core_components'])) {
			return (array) $config['configurator']['core_components'];
		}
		return $theme_core_components;
	}

	add_filter('corsivo_theme_spacing', 'corsivo_theme_merge_spacing', 20);
	function corsivo_theme_merge_spacing($theme_spacing) {
		$config = corsivo_theme_get_settings();
		if (!empty($config['configurator']['spacing'])) {
			return (array) $config['configurator']['spacing'];
		}
		return $theme_spacing;
	}

	/**
	 * Quote a font-family value when it is a single multi-word family without
	 * quotes (e.g. "Heal the web" -> '"Heal the web"'). Lists and already-quoted
	 * values are returned unchanged.
	 */
	function corsivo_theme_quote_font_family($value) {
		$value = trim((string) $value);
		if ($value === '') return '';
		if (strpos($value, ',') !== false) return $value;
		$first = $value[0];
		if ($first === '"' || $first === "'") return $value;
		if (strpos($value, ' ') === false) return $value;
		return '"' . $value . '"';
	}

	/**
	 * Resolve a font style's actual font-family value from its CSS, quoted for
	 * safe CSS use. Empty when the style declares none.
	 */
	function corsivo_theme_resolve_font_family_value($font) {
		$family = '';
		if (isset($font['css']) && !empty($font['css'])) {
			$family = corsivo_theme_parse_css_property($font['css'], 'font-family');
		}
		return $family ? corsivo_theme_quote_font_family($family) : '';
	}

	/**
	 * Build a CSS declarations string for all typography properties of a font slug.
	 *
	 * Emits the resolved ACTUAL font-family value (e.g. '"Akt", sans-serif') rather
	 * than a `var(--wp--preset--font-family--slug)` reference, so the rule never
	 * depends on a theme.json preset that may be missing or stale-cached. Returns
	 * an empty string when the slug matches no known font style.
	 */
	function corsivo_theme_font_css_declarations($slug) {
		if (empty($slug)) return '';

		$fonts = apply_filters('corsivo_theme_fonts', array());

		$matched = null;
		foreach ($fonts as $font) {
			if (isset($font['slug']) && $font['slug'] === $slug) {
				$matched = $font;
				break;
			}
		}

		// Unknown slug -> emit nothing instead of a dangling preset variable.
		if (!$matched) return '';

		$family = corsivo_theme_resolve_font_family_value($matched);
		$declarations = $family !== '' ? 'font-family:' . $family . ';' : '';

		$css = isset($matched['css']) ? $matched['css'] : '';
		$props = array('font-size','line-height','font-weight','font-style','letter-spacing','text-transform','text-decoration');
		foreach ($props as $prop) {
			$val = corsivo_theme_parse_css_property($css, $prop);
			if ($val) {
				$declarations .= $prop . ':' . $val . ';';
			}
		}

		return $declarations;
	}

	/**
	 * Invalidate WordPress' cached theme.json / global-styles data so config
	 * changes (colors, fonts, presets) are reflected immediately instead of
	 * after the cache naturally expires.
	 */
	function corsivo_theme_clear_theme_json_cache() {
		if (function_exists('wp_clean_theme_json_cache')) {
			wp_clean_theme_json_cache();
		} elseif (class_exists('WP_Theme_JSON_Resolver') && method_exists('WP_Theme_JSON_Resolver', 'clean_cached_data')) {
			WP_Theme_JSON_Resolver::clean_cached_data();
		}
		if (function_exists('get_stylesheet')) {
			delete_transient('global_styles_' . get_stylesheet());
		}
	}

	/**
	 * Frontend selectors that represent the current page title.
	 */
	function corsivo_theme_get_frontend_page_title_selectors($selector = '') {
		$targets = array(
			'.entry-title',
			'.page-title',
			'.wp-block-post-title',
			'.archive-title',
		);

		$selectors = array();
		foreach ($targets as $target) {
			$selectors[] = $selector ? trim($selector . ' ' . $target) : $target;
		}

		return $selectors;
	}

	/**
	 * Editor selectors that represent the editable post title.
	 */
	function corsivo_theme_get_editor_page_title_selectors() {
		return array(
			'.editor-post-title__input',
			'.editor-post-title__block .editor-post-title__input',
			'.editor-styles-wrapper .wp-block-post-title',
		);
	}

	/**
	 * Frontend selectors for template-aware default paragraph / heading styles.
	 * These only target content areas and only blocks/elements without an
	 * explicit is-style-font-* class.
	 */
	function corsivo_theme_get_frontend_default_block_selectors($selector = '', $type = 'paragraph') {
		$areas = array('.entry-content', '.wp-block-post-content');
		$prefix = $selector ? $selector . ' ' : '';
		$selectors = array();

		if ($type === 'paragraph') {
			foreach ($areas as $area) {
				$selectors[] = $prefix . ':where(' . $area . ' p:not([class*="is-style-font-"]))';
				$selectors[] = $prefix . ':where(' . $area . ' .wp-block-list:not([class*="is-style-font-"]))';
			}
			$selectors[] = $prefix . ':where(p:not([class*="is-style-font-"]))';
			$selectors[] = $prefix . ':where(.wp-block-list:not([class*="is-style-font-"]))';
			return $selectors;
		}

		if ($type === 'heading') {
			foreach ($areas as $area) {
				for ($i = 1; $i <= 6; $i++) {
					$selectors[] = $prefix . ':where(' . $area . ' h' . $i . ':not(.page-title):not(.entry-title):not(.wp-block-post-title):not(.archive-title):not([class*="is-style-font-"]))';
				}
			}
			for ($i = 1; $i <= 6; $i++) {
				$selectors[] = $prefix . ':where(h' . $i . ':not(.page-title):not(.entry-title):not(.wp-block-post-title):not(.archive-title):not([class*="is-style-font-"]))';
			}
		}

		return $selectors;
	}

	/**
	 * Editor selectors for template-aware default paragraph / heading styles.
	 * Targets unstyled paragraph/heading block wrappers and their editable nodes.
	 */
	function corsivo_theme_get_editor_default_block_selectors($type = 'paragraph') {
		if ($type === 'paragraph') {
			return array(
				'.editor-styles-wrapper [data-type="core/paragraph"]:not([class*="is-style-font-"])',
				'.editor-styles-wrapper .block-editor-block-list__block[data-type="core/paragraph"]:not([class*="is-style-font-"])',
				'.editor-styles-wrapper .wp-block-paragraph:not([class*="is-style-font-"])',
				'.editor-styles-wrapper [data-type="core/paragraph"]:not([class*="is-style-font-"]) p',
				'.editor-styles-wrapper .block-editor-block-list__block[data-type="core/paragraph"]:not([class*="is-style-font-"]) p',
				'.editor-styles-wrapper .wp-block-paragraph:not([class*="is-style-font-"]) p',
				'.editor-styles-wrapper [data-type="core/paragraph"]:not([class*="is-style-font-"]) .block-editor-rich-text__editable',
				'.editor-styles-wrapper .block-editor-block-list__block[data-type="core/paragraph"]:not([class*="is-style-font-"]) .block-editor-rich-text__editable',
				'.editor-styles-wrapper .wp-block-paragraph:not([class*="is-style-font-"]) .block-editor-rich-text__editable',
				'.editor-styles-wrapper [data-type="core/list"]:not([class*="is-style-font-"])',
				'.editor-styles-wrapper .block-editor-block-list__block[data-type="core/list"]:not([class*="is-style-font-"])',
				'.editor-styles-wrapper .wp-block-list:not([class*="is-style-font-"])',
				'.editor-styles-wrapper [data-type="core/list"]:not([class*="is-style-font-"]) .block-editor-rich-text__editable',
				'.editor-styles-wrapper .block-editor-block-list__block[data-type="core/list"]:not([class*="is-style-font-"]) .block-editor-rich-text__editable',
			);
		}

		if ($type === 'heading') {
			$selectors = array(
				'.editor-styles-wrapper [data-type="core/heading"]:not([class*="is-style-font-"])',
				'.editor-styles-wrapper .block-editor-block-list__block[data-type="core/heading"]:not([class*="is-style-font-"])',
				'.editor-styles-wrapper .wp-block-heading:not([class*="is-style-font-"])',
				'.editor-styles-wrapper [data-type="core/heading"]:not([class*="is-style-font-"]) .block-editor-rich-text__editable',
				'.editor-styles-wrapper .block-editor-block-list__block[data-type="core/heading"]:not([class*="is-style-font-"]) .block-editor-rich-text__editable',
				'.editor-styles-wrapper .wp-block-heading:not([class*="is-style-font-"]) .block-editor-rich-text__editable',
			);
			for ($i = 1; $i <= 6; $i++) {
				$selectors[] = '.editor-styles-wrapper [data-type="core/heading"]:not([class*="is-style-font-"]) h' . $i;
				$selectors[] = '.editor-styles-wrapper .block-editor-block-list__block[data-type="core/heading"]:not([class*="is-style-font-"]) h' . $i;
				$selectors[] = '.editor-styles-wrapper .wp-block-heading:not([class*="is-style-font-"]) h' . $i;
			}
			return $selectors;
		}

		return array();
	}

	/**
	 * Generate template-aware CSS for default paragraph and heading blocks.
	 */
	function corsivo_theme_generate_default_block_css($settings, $selector = '', $is_editor = false) {
		if (empty($settings) || !is_array($settings)) {
			return '';
		}

		$css = '';

		if (!empty($settings['defaultParagraphStyle'])) {
			$paragraph_css = corsivo_theme_font_css_declarations($settings['defaultParagraphStyle']);
			$paragraph_selectors = $is_editor
				? corsivo_theme_get_editor_default_block_selectors('paragraph')
				: corsivo_theme_get_frontend_default_block_selectors($selector, 'paragraph');

			if ($paragraph_css && !empty($paragraph_selectors)) {
				$css .= implode(',', $paragraph_selectors) . ' {' . $paragraph_css . '}' . "\n";
			}
		}

		if (!empty($settings['defaultHeadingStyle'])) {
			$heading_css = corsivo_theme_font_css_declarations($settings['defaultHeadingStyle']);
			$heading_selectors = $is_editor
				? corsivo_theme_get_editor_default_block_selectors('heading')
				: corsivo_theme_get_frontend_default_block_selectors($selector, 'heading');

			if ($heading_css && !empty($heading_selectors)) {
				$css .= implode(',', $heading_selectors) . ' {' . $heading_css . '}' . "\n";
			}
		}

		return $css;
	}

	/**
	 * Generate the global page title CSS fallback from the Global template.
	 */
	function corsivo_theme_generate_global_page_title_css() {
		$global_styles = apply_filters('corsivo_theme_global_styles', array());
		$title_style = isset($global_styles['titleStyle']) ? $global_styles['titleStyle'] : '';

		if (empty($title_style)) {
			return '';
		}

		$title_css = corsivo_theme_font_css_declarations($title_style);
		if (empty($title_css)) {
			return '';
		}

		return '/* Global page title */' . "\n"
			. implode(',', corsivo_theme_get_frontend_page_title_selectors('body')) . ' {' . $title_css . '}' . "\n";
	}

	/**
	 * Generate the editor fallback CSS for the global page title.
	 */
	function corsivo_theme_generate_editor_global_page_title_css() {
		$global_styles = apply_filters('corsivo_theme_global_styles', array());
		$title_style = isset($global_styles['titleStyle']) ? $global_styles['titleStyle'] : '';

		if (empty($title_style)) {
			return '';
		}

		$title_css = corsivo_theme_font_css_declarations($title_style);
		if (empty($title_css)) {
			return '';
		}

		return '/* Global page title */' . "\n"
			. implode(',', corsivo_theme_get_editor_page_title_selectors()) . ' {' . $title_css . '}' . "\n";
	}

	/**
	 * Generate the global fallback CSS for unstyled paragraph / heading blocks.
	 */
	function corsivo_theme_generate_global_default_block_css() {
		$global_styles = apply_filters('corsivo_theme_global_styles', array());
		$css = corsivo_theme_generate_default_block_css($global_styles, 'body', false);
		if (empty($css)) {
			return '';
		}
		return '/* Global default blocks */' . "\n" . $css;
	}

	/**
	 * Generate the editor fallback CSS for unstyled paragraph / heading blocks.
	 */
	function corsivo_theme_generate_editor_global_default_block_css() {
		$global_styles = apply_filters('corsivo_theme_global_styles', array());
		$css = corsivo_theme_generate_default_block_css($global_styles, '', true);
		if (empty($css)) {
			return '';
		}
		return '/* Global default blocks */' . "\n" . $css;
	}

	/**
	 * Generate CSS for a single template applied to a given body selector.
	 * Handles body, .wp-site-blocks, headings, page titles, and links.
	 * Resolves full typography properties from font slugs (not just font-family).
	 */
	function corsivo_theme_generate_template_css_for_selector($selector, $tpl, $comment = '') {
		$css = '';

		if ($comment) {
			$css .= '/* ' . $comment . ' */' . "\n";
		}

		// Body overrides — also target .wp-site-blocks for block themes
		$body = isset($tpl['body']) ? $tpl['body'] : array();
		if (!empty($body)) {
			$bg_css = '';
			$text_css = '';
			if (!empty($body['background'])) {
				$bg_css .= 'background-color:' . corsivo_theme_color_var($body['background']) . ';';
			}
			if (!empty($body['color'])) {
				$text_css .= 'color:' . corsivo_theme_color_var($body['color']) . ';';
			}
			if (!empty($body['font'])) {
				$text_css .= corsivo_theme_font_css_declarations($body['font']);
			}
			if ($bg_css || $text_css) {
				$css .= $selector . ' {' . $bg_css . $text_css . '}' . "\n";
				if ($bg_css) {
					$css .= $selector . ' .wp-site-blocks {' . $bg_css . '}' . "\n";
				}
			}
		}

		// Heading overrides
		$heading = isset($tpl['heading']) ? $tpl['heading'] : array();
		if (!empty($heading)) {
			$heading_css = '';
			if (!empty($heading['font'])) {
				$heading_css .= corsivo_theme_font_css_declarations($heading['font']);
			}
			if (!empty($heading['color'])) {
				$heading_css .= 'color:' . corsivo_theme_color_var($heading['color']) . ';';
			}
			// Explicit fontWeight overrides the one from font style
			if (!empty($heading['fontWeight'])) {
				$heading_css .= 'font-weight:' . $heading['fontWeight'] . ';';
			}
			if ($heading_css) {
				$heading_selectors = array();
				for ($i = 1; $i <= 6; $i++) {
					$heading_selectors[] = $selector . ' :where(h' . $i . ':not(.entry-title):not(.page-title):not(.wp-block-post-title):not(.archive-title):not([class*="is-style-font-"]))';
				}
				$css .= implode(',', $heading_selectors) . ' {' . $heading_css . '}' . "\n";
			}
		}

		// Page title overrides
		if (!empty($tpl['titleStyle'])) {
			$title_css = corsivo_theme_font_css_declarations($tpl['titleStyle']);
			if ($title_css) {
				$css .= implode(',', corsivo_theme_get_frontend_page_title_selectors($selector)) . ' {' . $title_css . '}' . "\n";
			}
		}

		// Default paragraph / heading block overrides
		$css .= corsivo_theme_generate_default_block_css($tpl, $selector, false);

		// Link overrides
		$link = isset($tpl['link']) ? $tpl['link'] : array();
		if (!empty($link) && !empty($link['color'])) {
			$css .= $selector . ' a:where(:not(.wp-element-button)) {color:' . corsivo_theme_color_var($link['color']) . ';}' . "\n";
		}

		return $css;
	}

	/**
	 * Build a slug => template lookup map for style templates.
	 */
	function corsivo_theme_get_style_template_map($style_templates) {
		$tpl_map = array();

		foreach ($style_templates as $tpl) {
			if (!empty($tpl['slug'])) {
				$tpl_map[$tpl['slug']] = $tpl;
			}
		}

		return $tpl_map;
	}

	/**
	 * Return the first template marked as default.
	 */
	function corsivo_theme_get_default_style_template($style_templates) {
		foreach ($style_templates as $tpl) {
			if (!empty($tpl['slug']) && !empty($tpl['isDefault'])) {
				return $tpl;
			}
		}

		return null;
	}

	/**
	 * Canonical body-class slug for a page-template context. Used identically by
	 * the admin options, runtime context detection, and the frontend body_class
	 * so a template assignment matches regardless of WP's own (post-type-prefixed,
	 * subdir-mangled) template body classes.
	 */
	function corsivo_theme_page_template_context_slug($template) {
		return 'page-template-' . sanitize_title(str_replace(array('.php', '/'), array('', '-'), $template));
	}

	/**
	 * Specificity rank for a context slug, so the most specific assignment wins
	 * deterministically instead of depending on admin row order.
	 */
	function corsivo_theme_context_specificity($context) {
		if (strpos($context, 'page-template-') === 0) return 100;
		if ($context === 'home' || $context === 'blog') return 90;
		if (strpos($context, 'single-') === 0) return 80;
		if (strpos($context, 'post-type-archive-') === 0) return 70;
		if ($context === 'page') return 65;
		return 60;
	}

	/**
	 * Resolve the effective style template for a post in this order:
	 * per-post override => context assignment => default template.
	 */
	function corsivo_theme_resolve_style_template_for_post($post, $style_templates = null, $context_styles = null) {
		if (!$post) {
			return null;
		}

		if ($style_templates === null) {
			$style_templates = apply_filters('corsivo_theme_style_templates', array());
		}

		if ($context_styles === null) {
			$context_styles = apply_filters('corsivo_theme_context_styles', array());
		}

		$tpl_map = corsivo_theme_get_style_template_map($style_templates);
		$matched_tpl = corsivo_theme_get_default_style_template($style_templates);

		if (!empty($context_styles)) {
			$post_contexts = corsivo_theme_detect_post_context($post);
			$best_rank = -1;
			foreach ($context_styles as $cs) {
				if (empty($cs['context']) || empty($cs['template'])) continue;
				if (!in_array($cs['context'], $post_contexts, true)) continue;
				if (!isset($tpl_map[$cs['template']])) continue;

				$rank = corsivo_theme_context_specificity($cs['context']);
				if ($rank >= $best_rank) {
					$best_rank = $rank;
					$matched_tpl = $tpl_map[$cs['template']];
				}
			}
		}

		$meta_slug = get_post_meta($post->ID, '_corsivo_theme_style_template', true);
		if (!empty($meta_slug) && isset($tpl_map[$meta_slug])) {
			$matched_tpl = $tpl_map[$meta_slug];
		}

		return $matched_tpl;
	}

	/**
	 * Generate context overrides CSS (body-class scoped).
	 * Produces CSS for:
	 *   1. Context assignments → body.{context}
	 *   2. Per-page overrides → body.corsivo-theme-template-{slug}  (higher specificity)
	 */
	function corsivo_theme_generate_context_css() {
		$context_styles   = apply_filters('corsivo_theme_context_styles', array());
		$style_templates  = apply_filters('corsivo_theme_style_templates', array());

		if (empty($style_templates)) return '';

		// Build a slug → template lookup map
		$tpl_map = corsivo_theme_get_style_template_map($style_templates);

		$css = '';

		// --- 0. Default template ---
		$default_tpl = corsivo_theme_get_default_style_template($style_templates);
		if ($default_tpl) {
			$default_slug = !empty($default_tpl['slug']) ? $default_tpl['slug'] : 'default';
			$default_name = !empty($default_tpl['name']) ? $default_tpl['name'] : $default_slug;
			$css .= corsivo_theme_generate_template_css_for_selector(
				'body',
				$default_tpl,
				'Default template: ' . $default_name
			);
		}

		// --- 1. Context assignments ---
		if (!empty($context_styles)) {
			// Emit least-specific first so the most specific context wins by source order.
			usort($context_styles, function ($a, $b) {
				$ra = isset($a['context']) ? corsivo_theme_context_specificity($a['context']) : 0;
				$rb = isset($b['context']) ? corsivo_theme_context_specificity($b['context']) : 0;
				return $ra - $rb;
			});
			foreach ($context_styles as $assignment) {
				if (empty($assignment['context']) || empty($assignment['template'])) continue;

				$tpl_slug = $assignment['template'];
				if (!isset($tpl_map[$tpl_slug])) continue;

				$tpl = $tpl_map[$tpl_slug];
				$ctx = sanitize_html_class($assignment['context']);
				$selector = 'body.' . $ctx;
				$comment = 'Context: ' . $ctx . ' → Template: ' . (!empty($tpl['name']) ? $tpl['name'] : $tpl_slug);

				$css .= corsivo_theme_generate_template_css_for_selector($selector, $tpl, $comment);
			}
		}

		// --- 2. Per-page template overrides (body.corsivo-theme-template-{slug}) ---
		$css .= "\n" . '/* === Per-page template overrides === */' . "\n";
		foreach ($style_templates as $tpl) {
			if (empty($tpl['slug'])) continue;
			$selector = 'body.corsivo-theme-template-' . sanitize_html_class($tpl['slug']);
			$comment = 'Per-page: ' . (!empty($tpl['name']) ? $tpl['name'] : $tpl['slug']);
			$css .= corsivo_theme_generate_template_css_for_selector($selector, $tpl, $comment);
		}

		return $css;
	}

	/**
	 * Enqueue context overrides CSS on the frontend.
	 * Loads AFTER WordPress global-styles to guarantee override.
	 */
	function corsivo_theme_enqueue_global_styles() {
		$css = corsivo_theme_generate_global_page_title_css()
			. corsivo_theme_generate_global_default_block_css()
			. corsivo_theme_generate_context_css();
		if (empty(trim($css))) return;

		wp_register_style('corsivo-theme-global-styles', false);
		wp_enqueue_style('corsivo-theme-global-styles');
		wp_add_inline_style('corsivo-theme-global-styles', corsivo_theme_sanitize_css_block($css));
	}
	add_action('wp_enqueue_scripts', 'corsivo_theme_enqueue_global_styles', 99);

	/**
	 * Enqueue Google Fonts as <link> tags instead of @import in CSS.
	 */
	function corsivo_theme_enqueue_google_fonts() {
		if (is_admin()) {
			$screen = function_exists('get_current_screen') ? get_current_screen() : null;
			if (!$screen) return;
			if (!$screen->is_block_editor() && $screen->id !== 'settings_page_corsivo-theme-editor') return;
		}

		$import_fonts = apply_filters('corsivo_theme_custom_fonts', array());
		if (empty($import_fonts)) return;

		foreach ($import_fonts as $index => $entry) {
			$url = is_array($entry) && isset($entry['url']) ? $entry['url'] : '';
			if (empty($url)) continue;
			wp_enqueue_style('corsivo-theme-google-font-' . $index, $url, array(), null);
		}
	}
	add_action('wp_enqueue_scripts', 'corsivo_theme_enqueue_google_fonts');
	add_action('admin_enqueue_scripts', 'corsivo_theme_enqueue_google_fonts');

	/**
	 * Load the Google Fonts stylesheets off the render-blocking critical path:
	 * swap media to print and restore it on load, with a <noscript> fallback.
	 * Frontend only — the editor needs the fonts applied synchronously.
	 */
	function corsivo_theme_async_google_fonts($tag, $handle) {
		if (is_admin() || strpos($handle, 'corsivo-theme-google-font-') !== 0) {
			return $tag;
		}
		$async = str_replace("media='all'", "media='print' onload=\"this.media='all'\"", $tag);
		if ($async !== $tag) {
			$tag = $async . '<noscript>' . $tag . '</noscript>';
		}
		return $tag;
	}
	add_filter('style_loader_tag', 'corsivo_theme_async_google_fonts', 10, 2);

	/**
	 * Preconnect to the Google Fonts hosts when custom fonts are loaded, so the
	 * stylesheet and woff2 fetch don't wait on the connection handshake.
	 */
	function corsivo_theme_preconnect_font_hosts($urls, $relation) {
		if ('preconnect' !== $relation) {
			return $urls;
		}

		foreach (apply_filters('corsivo_theme_custom_fonts', array()) as $entry) {
			if (!is_array($entry) || empty($entry['url'])) continue;
			$urls[] = 'https://fonts.googleapis.com';
			$urls[] = array('href' => 'https://fonts.gstatic.com', 'crossorigin');
			break;
		}
		return $urls;
	}
	add_filter('wp_resource_hints', 'corsivo_theme_preconnect_font_hosts', 10, 2);

	/**
	 * Default every installed font to font-display:swap when none is set, avoiding
	 * FOIT without forcing the admin to fill the field per font. Runs after the
	 * configurator merge (priority 20) so it sees the final list, and feeds all
	 * three @font-face emitters since they read the same corsivo_theme_install_fonts filter.
	 */
	function corsivo_theme_default_font_display($fonts) {
		if (!is_array($fonts)) {
			return $fonts;
		}
		foreach ($fonts as &$font) {
			if (is_array($font) && empty($font['font-display'])) {
				$font['font-display'] = 'swap';
			}
		}
		unset($font);
		return $fonts;
	}
	add_filter('corsivo_theme_install_fonts', 'corsivo_theme_default_font_display', 30);

	/**
	 * Return true when frontend preset CSS should be printed independently of
	 * Gutenberg's block-aware global-styles output.
	 */
	function corsivo_theme_should_force_style_injection() {
		$config = corsivo_theme_get_settings();
		$global = isset($config['global']) && is_array($config['global']) ? $config['global'] : array();
		return !empty($global['force_style_injection']);
	}

	function corsivo_theme_sanitize_css_block($css) {
		return str_replace('</', '\\3c /', (string) $css);
	}

	/**
	 * Flatten theme.json preset arrays, whether grouped by origin or already flat.
	 */
	function corsivo_theme_flatten_theme_json_presets($presets) {
		$items = array();
		if (!is_array($presets)) return $items;

		foreach ($presets as $preset) {
			if (is_array($preset) && isset($preset['slug'])) {
				$items[] = $preset;
				continue;
			}

			if (!is_array($preset)) continue;
			foreach ($preset as $nested) {
				if (is_array($nested) && isset($nested['slug'])) {
					$items[] = $nested;
				}
			}
		}

		return $items;
	}

	/**
	 * Read the merged theme.json data after Corsivo Theme's runtime injection.
	 */
	function corsivo_theme_get_merged_theme_json_data() {
		if (!class_exists('WP_Theme_JSON_Resolver')) {
			return array();
		}

		$theme_data = WP_Theme_JSON_Resolver::get_merged_data();
		if (!$theme_data || !method_exists($theme_data, 'get_data')) {
			return array();
		}

		$data = $theme_data->get_data();
		return is_array($data) ? $data : array();
	}

	/**
	 * Force-print preset variables and utility classes for frontend CSS that
	 * references theme.json presets outside Gutenberg-rendered block content.
	 */
	function corsivo_theme_generate_forced_preset_css() {
		$data = corsivo_theme_get_merged_theme_json_data();
		$settings = isset($data['settings']) && is_array($data['settings']) ? $data['settings'] : array();

		$colors = corsivo_theme_flatten_theme_json_presets($settings['color']['palette'] ?? array());
		$font_families = corsivo_theme_flatten_theme_json_presets($settings['typography']['fontFamilies'] ?? array());
		$spacing_sizes = corsivo_theme_flatten_theme_json_presets($settings['spacing']['spacingSizes'] ?? array());

		$vars = array();
		$classes = '';

		foreach ($colors as $color) {
			if (empty($color['slug']) || empty($color['color'])) continue;
			$slug = sanitize_title($color['slug']);
			$value = trim((string) $color['color']);
			if (!$slug || !$value) continue;

			$vars['color-' . $slug] = '--wp--preset--color--' . $slug . ':' . $value . ';';
			$classes .= '.has-' . $slug . '-color{color:var(--wp--preset--color--' . $slug . ') !important;}';
			$classes .= '.has-' . $slug . '-background-color{background-color:var(--wp--preset--color--' . $slug . ') !important;}';
			$classes .= '.has-' . $slug . '-border-color{border-color:var(--wp--preset--color--' . $slug . ') !important;}';
		}

		foreach ($font_families as $font_family) {
			if (empty($font_family['slug']) || empty($font_family['fontFamily'])) continue;
			$slug = sanitize_title($font_family['slug']);
			$value = trim((string) $font_family['fontFamily']);
			if (!$slug || !$value) continue;

			$vars['font-family-' . $slug] = '--wp--preset--font-family--' . $slug . ':' . $value . ';';
			$classes .= '.has-' . $slug . '-font-family{font-family:var(--wp--preset--font-family--' . $slug . ') !important;}';
		}

		foreach ($spacing_sizes as $spacing_size) {
			if (empty($spacing_size['slug']) || empty($spacing_size['size'])) continue;
			$slug = sanitize_title($spacing_size['slug']);
			$value = trim((string) $spacing_size['size']);
			if (!$slug || !$value) continue;

			$vars['spacing-' . $slug] = '--wp--preset--spacing--' . $slug . ':' . $value . ';';
		}

		if (!empty($settings['layout']['contentSize'])) {
			$vars['global-content-size'] = '--wp--style--global--content-size:' . trim((string) $settings['layout']['contentSize']) . ';';
		}
		if (!empty($settings['layout']['wideSize'])) {
			$vars['global-wide-size'] = '--wp--style--global--wide-size:' . trim((string) $settings['layout']['wideSize']) . ';';
		}

		$css = '';
		if (!empty($vars)) {
			$css .= ':root{' . implode('', $vars) . '}' . "\n";
		}
		$css .= $classes;

		return $css;
	}

	/**
	 * Enqueue forced frontend preset CSS when enabled in Settings > Extra.
	 */
	function corsivo_theme_enqueue_forced_style_injection() {
		if (!corsivo_theme_should_force_style_injection()) return;

		$css = corsivo_theme_generate_forced_preset_css();
		if (empty(trim($css))) return;

		wp_register_style('corsivo-theme-forced-styles', false);
		wp_enqueue_style('corsivo-theme-forced-styles');
		wp_add_inline_style('corsivo-theme-forced-styles', corsivo_theme_sanitize_css_block($css));
	}
	add_action('wp_enqueue_scripts', 'corsivo_theme_enqueue_forced_style_injection', 98);

	/**
	 * Ensure the editor canvas gets the same preview wrapper class used by
	 * the compiled editor stylesheet.
	 */
	function corsivo_theme_add_editor_preview_body_class($settings) {
		$preview_class = 'salvatorecorsi-dynamic-blocks-preview';
		$existing = isset($settings['bodyClassName']) ? trim($settings['bodyClassName']) : '';

		if (strpos(' ' . $existing . ' ', ' ' . $preview_class . ' ') === false) {
			$settings['bodyClassName'] = trim($existing . ' ' . $preview_class);
		}

		return $settings;
	}
	add_filter('block_editor_settings_all', 'corsivo_theme_add_editor_preview_body_class', 20);

	/**
	 * Register custom block style variations from core_components.blockStyles.
	 * Also unregister hidden core block styles.
	 */
	function corsivo_theme_register_cc_block_styles() {
		$config = corsivo_theme_get_settings();
		$cc = isset($config['configurator']['core_components']) ? $config['configurator']['core_components'] : array();
		$block_styles = isset($cc['blockStyles']) ? $cc['blockStyles'] : array();

		if (empty($block_styles)) return;

		foreach ($block_styles as $bs) {
			if (empty($bs['target']) || empty($bs['slug'])) continue;
			if (array_key_exists('register', $bs) && !$bs['register']) continue;

			// Hidden styles: only unregister non-core ones via PHP.
			// Core block.json styles (fromCore) can't be removed with
			// unregister_block_style — they are handled via JS in the editor
			// (see corsivo_theme_editor_context_styles section 6).
			if (!empty($bs['hideInEditor'])) {
				if (empty($bs['fromCore'])) {
					$registry = WP_Block_Styles_Registry::get_instance();
					if ($registry->is_registered($bs['target'], $bs['slug'])) {
						unregister_block_style($bs['target'], $bs['slug']);
					}
				}
				continue;
			}

			// Register custom (non-core) styles
			if (empty($bs['fromCore'])) {
				register_block_style($bs['target'], array(
					'name'       => $bs['slug'],
					'label'      => !empty($bs['name']) ? $bs['name'] : $bs['slug'],
					'is_default' => !empty($bs['isDefault']),
				));
			}
		}
	}
	add_action('init', 'corsivo_theme_register_cc_block_styles', 30);

	/**
	 * Resolve the inherited CSS chain for a block style.
	 *
	 * The "inherits" field can be:
	 *   - "font:{slug}"          → inherits CSS from a font style
	 *   - "bs:{target}::{slug}"  → inherits CSS from another block style
	 *
	 * Returns the merged CSS string (parent first, child overrides).
	 * Handles chains up to 10 levels deep to prevent infinite loops.
	 */
	function corsivo_theme_resolve_inherits_css($bs, $fonts, $block_styles, $depth = 0, $seen = array()) {
		if ($depth > 10 || empty($bs['inherits'])) {
			return isset($bs['css']) ? $bs['css'] : '';
		}

		$self_key = (isset($bs['target']) ? $bs['target'] : '') . '::' . (isset($bs['slug']) ? $bs['slug'] : '');
		if (in_array($self_key, $seen, true)) {
			return isset($bs['css']) ? $bs['css'] : '';
		}
		$seen[] = $self_key;

		$inherits = $bs['inherits'];
		$parent_css = '';

		if (strpos($inherits, 'font:') === 0) {
			// Inherit from a font style
			$font_slug = substr($inherits, 5);
			foreach ($fonts as $font) {
				if (isset($font['slug']) && $font['slug'] === $font_slug && !empty($font['css'])) {
					$parent_css = $font['css'];
					break;
				}
			}
		} elseif (strpos($inherits, 'bs:') === 0) {
			// Inherit from another block style: "bs:{target}::{slug}"
			$ref = substr($inherits, 3);
			$parts = explode('::', $ref, 2);
			if (count($parts) === 2) {
				$ref_target = $parts[0];
				$ref_slug = $parts[1];
				foreach ($block_styles as $parent_bs) {
					if (isset($parent_bs['target']) && $parent_bs['target'] === $ref_target
						&& isset($parent_bs['slug']) && $parent_bs['slug'] === $ref_slug) {
						// Recursively resolve the parent's chain
						$parent_css = corsivo_theme_resolve_inherits_css($parent_bs, $fonts, $block_styles, $depth + 1, $seen);
						break;
					}
				}
			}
		}

		$own_css = isset($bs['css']) ? $bs['css'] : '';

		if (empty($parent_css)) return $own_css;
		if (empty($own_css)) return $parent_css;

		// Merge: parent props first, child overrides
		return corsivo_theme_merge_css_strings($parent_css, $own_css);
	}

	/**
	 * Resolve the inherited pseudo-state CSS (e.g. :hover, :focus) for a block style.
	 *
	 * Walks up the "inherits" chain collecting the pseudo-state CSS from
	 * each ancestor. The child's own pseudo CSS overrides the parent's props.
	 * If the child has NO pseudo CSS of its own, the parent's is inherited as-is.
	 */
	function corsivo_theme_resolve_inherits_pseudo($bs, $pseudo, $fonts, $block_styles, $depth = 0, $seen = array()) {
		if ($depth > 10) {
			return isset($bs[$pseudo]) ? $bs[$pseudo] : '';
		}

		$self_key = (isset($bs['target']) ? $bs['target'] : '') . '::' . (isset($bs['slug']) ? $bs['slug'] : '');
		if (in_array($self_key, $seen, true)) {
			return isset($bs[$pseudo]) ? $bs[$pseudo] : '';
		}
		$seen[] = $self_key;

		$parent_pseudo = '';

		if (!empty($bs['inherits'])) {
			$inherits = $bs['inherits'];

			if (strpos($inherits, 'font:') === 0) {
				// Font styles don't have pseudo-states — nothing to inherit
				$parent_pseudo = '';
			} elseif (strpos($inherits, 'bs:') === 0) {
				$ref = substr($inherits, 3);
				$parts = explode('::', $ref, 2);
				if (count($parts) === 2) {
					$ref_target = $parts[0];
					$ref_slug = $parts[1];
					foreach ($block_styles as $parent_bs) {
						if (isset($parent_bs['target']) && $parent_bs['target'] === $ref_target
							&& isset($parent_bs['slug']) && $parent_bs['slug'] === $ref_slug) {
							// Recursively resolve the parent's pseudo chain
							$parent_pseudo = corsivo_theme_resolve_inherits_pseudo($parent_bs, $pseudo, $fonts, $block_styles, $depth + 1, $seen);
							break;
						}
					}
				}
			}
		}

		$own_pseudo = isset($bs[$pseudo]) ? $bs[$pseudo] : '';

		if (empty($parent_pseudo)) return $own_pseudo;
		if (empty($own_pseudo)) return $parent_pseudo;

		return corsivo_theme_merge_css_strings($parent_pseudo, $own_pseudo);
	}

	function corsivo_theme_resolve_inherits_parts($bs, $block_styles, $depth = 0, $seen = array()) {
		$own_parts = !empty($bs['parts']) && is_array($bs['parts']) ? $bs['parts'] : array();

		if ($depth >= 10) {
			return $own_parts;
		}

		$self_key = (isset($bs['target']) ? $bs['target'] : '') . '::' . (isset($bs['slug']) ? $bs['slug'] : '');
		if (in_array($self_key, $seen, true)) {
			return array();
		}
		$seen[] = $self_key;

		if (empty($bs['inherits']) || strpos($bs['inherits'], 'bs:') !== 0) {
			return $own_parts;
		}

		$reference = explode('::', substr($bs['inherits'], 3), 2);
		if (count($reference) !== 2) {
			return $own_parts;
		}

		$parent_parts = array();
		foreach ($block_styles as $parent_bs) {
			if (isset($parent_bs['target']) && $parent_bs['target'] === $reference[0]
				&& isset($parent_bs['slug']) && $parent_bs['slug'] === $reference[1]) {
				$parent_parts = corsivo_theme_resolve_inherits_parts($parent_bs, $block_styles, $depth + 1, $seen);
				break;
			}
		}

		return array_merge($parent_parts, $own_parts);
	}

	/**
	 * Merge two CSS declaration strings. Child properties override parent.
	 */
	function corsivo_theme_merge_css_strings($parent_css, $child_css) {
		$parent_props = corsivo_theme_parse_css_declarations($parent_css);
		$child_props = corsivo_theme_parse_css_declarations($child_css);
		$merged = array_merge($parent_props, $child_props);
		$result = '';
		foreach ($merged as $prop => $val) {
			$result .= $prop . ': ' . $val . ";\n";
		}
		return trim($result);
	}

	/**
	 * Split a CSS declaration string on ';' while ignoring semicolons that live
	 * inside parentheses (url(), gradients) or quoted strings (data-URIs).
	 */
	function corsivo_theme_split_css_declarations($css) {
		$result = array();
		$buffer = '';
		$depth = 0;
		$quote = '';
		$len = strlen($css);
		for ($i = 0; $i < $len; $i++) {
			$ch = $css[$i];
			if ($quote !== '') {
				$buffer .= $ch;
				if ($ch === $quote && ($i === 0 || $css[$i - 1] !== '\\')) {
					$quote = '';
				}
				continue;
			}
			if ($ch === '"' || $ch === "'") {
				$quote = $ch;
				$buffer .= $ch;
				continue;
			}
			if ($ch === '(') { $depth++; $buffer .= $ch; continue; }
			if ($ch === ')') { if ($depth > 0) $depth--; $buffer .= $ch; continue; }
			if ($ch === ';' && $depth === 0) {
				$result[] = $buffer;
				$buffer = '';
				continue;
			}
			$buffer .= $ch;
		}
		if (trim($buffer) !== '') {
			$result[] = $buffer;
		}
		return $result;
	}

	/**
	 * Parse CSS declaration string into associative array of prop => value.
	 */
	function corsivo_theme_parse_css_declarations($css) {
		$props = array();
		if (empty($css)) return $props;
		$declarations = corsivo_theme_split_css_declarations($css);
		foreach ($declarations as $decl) {
			$colon = strpos($decl, ':');
			if ($colon === false) continue;
			$prop = trim(substr($decl, 0, $colon));
			$val = trim(substr($decl, $colon + 1));
			if ($prop !== '' && $val !== '') {
				$props[$prop] = $val;
			}
		}
		return $props;
	}

	function corsivo_theme_block_wrapper_class($block_name) {
		$block_name = strtolower(trim((string) $block_name));
		if ($block_name === '') return '';

		if (strpos($block_name, 'core/') === 0) {
			$block_name = substr($block_name, 5);
		} else {
			$block_name = str_replace('/', '-', $block_name);
		}

		$block_name = preg_replace('/[^a-z0-9-]+/', '-', $block_name);
		$block_name = trim($block_name, '-');

		return $block_name === '' ? '' : 'wp-block-' . $block_name;
	}

	function corsivo_theme_block_style_wrapper_selector($block_style) {
		$selector = isset($block_style['selector']) ? trim((string) $block_style['selector']) : '';

		if ($selector === '') {
			$block_class = corsivo_theme_block_wrapper_class(isset($block_style['target']) ? $block_style['target'] : '');
			if ($block_class === '') return '';
			$selector = '.' . $block_class;
		}

		$style_slug = isset($block_style['slug']) ? sanitize_title($block_style['slug']) : '';
		if ($style_slug === '') return '';

		return $selector . '.is-style-' . $style_slug;
	}

	function corsivo_theme_block_style_nested_selector($wrapper_selector, $nested_selector) {
		$nested_selector = trim((string) $nested_selector);
		if ($wrapper_selector === '' || $nested_selector === '') return $wrapper_selector;

		if (strpos($nested_selector, '&') !== false) {
			return str_replace('&', $wrapper_selector, $nested_selector);
		}

		return $wrapper_selector . ' ' . $nested_selector;
	}

	function corsivo_theme_block_style_primary_selector($block_style, $inner_element_map = array()) {
		$wrapper_selector = corsivo_theme_block_style_wrapper_selector($block_style);
		if ($wrapper_selector === '') return '';

		$inner_selector = isset($block_style['innerSelector']) ? trim((string) $block_style['innerSelector']) : '';
		if ($inner_selector === '' && !empty($block_style['target']) && isset($inner_element_map[$block_style['target']])) {
			$inner_selector = $inner_element_map[$block_style['target']];
		}

		return corsivo_theme_block_style_nested_selector($wrapper_selector, $inner_selector);
	}

	function corsivo_theme_append_block_style_css(&$css, $selector, $style) {
		if ($selector === '' || !is_array($style)) return;

		$style_css = isset($style['css']) ? trim((string) $style['css']) : '';
		if ($style_css !== '') {
			$css .= $selector . '{' . $style_css . '}';
		}

		foreach (array(':hover', ':focus', ':focus-visible', ':active') as $pseudo) {
			$pseudo_css = isset($style[$pseudo]) ? trim((string) $style[$pseudo]) : '';
			if ($pseudo_css !== '') {
				$css .= $selector . $pseudo . '{' . $pseudo_css . '}';
			}
		}
	}

	/**
	 * Generate and enqueue CSS for block style variations.
	 * Both frontend and editor.
	 *
	 * This reinforces theme.json variations with inline CSS, ensuring
	 * styles are applied even when theme.json specificity is insufficient
	 * to override WP's core block stylesheets.
	 */
	function corsivo_theme_enqueue_cc_block_styles_css() {
		$config = corsivo_theme_get_settings();
		$cc = isset($config['configurator']['core_components']) ? $config['configurator']['core_components'] : array();
		$block_styles = isset($cc['blockStyles']) ? $cc['blockStyles'] : array();
		$fonts = isset($config['configurator']['fonts']) ? $config['configurator']['fonts'] : array();

		if (empty($block_styles)) return;

		// Blocks where styles must target an inner element instead of the wrapper
		$inner_element_map = array(
			'core/button' => '.wp-element-button',
		);

		$css = '';
		$default_button_css = '';
		$default_button_hover = '';

		foreach ($block_styles as $bs) {
			if (empty($bs['target']) || empty($bs['slug'])) continue;
			if (!empty($bs['hideInEditor'])) continue;

			// Resolve inheritance chain
			$resolved_css = corsivo_theme_resolve_inherits_css($bs, $fonts, $block_styles);
			$resolved_hover = corsivo_theme_resolve_inherits_pseudo($bs, ':hover', $fonts, $block_styles);
			$resolved_parts = corsivo_theme_resolve_inherits_parts($bs, $block_styles);
			$has_parts = !empty($resolved_parts);
			if (empty($resolved_css) && empty($resolved_hover) && !$has_parts) continue;

			$wrapper = corsivo_theme_block_style_wrapper_selector($bs);
			$selector = corsivo_theme_block_style_primary_selector($bs, $inner_element_map);
			if ($wrapper === '' || $selector === '') continue;

			if (!empty($resolved_css)) {
				$css .= $selector . '{' . $resolved_css . '}';
			}

			if (!empty($resolved_hover)) {
				$css .= $selector . ':hover{' . $resolved_hover . '}';
			}

			if ($has_parts) {
				foreach ($resolved_parts as $part) {
					if (!is_array($part) || empty($part['selector'])) continue;
					$part_selector = corsivo_theme_block_style_nested_selector($wrapper, $part['selector']);
					corsivo_theme_append_block_style_css($css, $part_selector, $part);
				}
			}

			// Track default button style for base override (first default wins)
			if ($bs['target'] === 'core/button' && !empty($bs['isDefault']) && empty($default_button_css) && empty($default_button_hover)) {
				$default_button_css = $resolved_css;
				$default_button_hover = $resolved_hover;
			}
		}

		// When a button style is default, override WP's base button appearance
		// (black background, border-radius: 9999px). Target .wp-element-button
		// directly so it affects ALL buttons including those without a style class.
		if (!empty($default_button_css)) {
			$css .= '.wp-block-button .wp-element-button{' . $default_button_css . '}';
		}
		if (!empty($default_button_hover)) {
			$css .= '.wp-block-button .wp-element-button:hover{' . $default_button_hover . '}';
		}

		if (empty($css)) return;

		wp_register_style('corsivo-theme-block-styles', false);
		wp_enqueue_style('corsivo-theme-block-styles');
		wp_add_inline_style('corsivo-theme-block-styles', corsivo_theme_sanitize_css_block($css));
	}
	add_action('wp_enqueue_scripts', 'corsivo_theme_enqueue_cc_block_styles_css', 99);
	add_action('enqueue_block_editor_assets', 'corsivo_theme_enqueue_cc_block_styles_css');

	function corsivo_theme_generate_font_breakpoint_css($font, $selector_prefix = '') {
		if (empty($font['slug']) || empty($font['breakpoints']) || !is_array($font['breakpoints'])) return '';

		$breakpoint_widths = apply_filters('corsivo_theme_breakpoints', array());
		$overrides = array();

		foreach ($font['breakpoints'] as $breakpoint_name => $override) {
			$override_css = is_array($override) && isset($override['css']) ? trim((string) $override['css']) : '';
			if ($override_css === '') continue;
			if (!isset($breakpoint_widths[$breakpoint_name]) || !is_numeric($breakpoint_widths[$breakpoint_name])) continue;
			$overrides[] = array('width' => $breakpoint_widths[$breakpoint_name] + 0, 'css' => $override_css);
		}

		if (empty($overrides)) return '';

		usort($overrides, function ($a, $b) {
			return $b['width'] <=> $a['width'];
		});

		$slug = sanitize_html_class($font['slug']);
		$selectors = $selector_prefix . '.' . $slug . ',' . $selector_prefix . '.is-style-font-' . $slug;

		$css = '';
		foreach ($overrides as $override) {
			$css .= '@media (max-width: ' . $override['width'] . 'px){' . $selectors . '{' . $override['css'] . '}}' . "\n";
		}

		return $css;
	}

	/**
	 * Enqueue font style variation CSS on the frontend.
	 * Generates .is-style-font-{slug} rules so that block style variations
	 * registered via corsivo_theme_register_font_style() are visible on the frontend,
	 * not only inside the editor.
	 */
	function corsivo_theme_enqueue_font_styles_css() {
		$fonts = apply_filters('corsivo_theme_fonts', array());
		if (empty($fonts)) return;

		$css = '';
		foreach ($fonts as $font) {
			if (empty($font['slug'])) continue;

			$style_css = '';
			if (isset($font['css']) && !empty($font['css'])) {
				$style_css = $font['css'];
			} else {
				$reserved = array('slug','name','default','hide','scope','scopes','breakpoints','css');
				foreach ($font as $field => $value) {
					if (in_array($field, $reserved)) continue;
					if (corsivo_theme_check_field($field, $value)) {
						$style_css .= $field . ':' . $value . ';';
					}
				}
			}
			if ($style_css) {
				$css .= '.is-style-font-' . sanitize_html_class($font['slug']) . '{' . $style_css . '}' . "\n";
			}
			$css .= corsivo_theme_generate_font_breakpoint_css($font);
		}

		if (empty($css)) return;

		wp_register_style('corsivo-theme-font-styles', false);
		wp_enqueue_style('corsivo-theme-font-styles');
		wp_add_inline_style('corsivo-theme-font-styles', corsivo_theme_sanitize_css_block($css));
	}
	add_action('wp_enqueue_scripts', 'corsivo_theme_enqueue_font_styles_css', 99);

	/**
	 * Detect which contexts apply to the post being edited.
	 * Returns an array of context slugs (e.g. ['home', 'page']).
	 */
	function corsivo_theme_detect_post_context($post) {
		$contexts = array();

		// Front page (Settings > Reading > Homepage)
		$front_page_id = (int) get_option('page_on_front');
		if ($front_page_id && $post->ID === $front_page_id) {
			$contexts[] = 'home';
		}

		// Blog / posts page
		$blog_page_id = (int) get_option('page_for_posts');
		if ($blog_page_id && $post->ID === $blog_page_id) {
			$contexts[] = 'blog';
		}

		// Post-type based context
		if ($post->post_type === 'post') {
			$contexts[] = 'single-post';
		} elseif ($post->post_type === 'page') {
			$contexts[] = 'page';
		} else {
			$contexts[] = 'single-' . $post->post_type;
		}

		// Page template
		$template = get_page_template_slug($post->ID);
		if ($template) {
			$contexts[] = corsivo_theme_page_template_context_slug($template);
		}

		if (corsivo_theme_integration_enabled('woocommerce') && function_exists('wc_get_page_id')) {
			$woocommerce_pages = array(
				'woocommerce-shop' => wc_get_page_id('shop'),
				'woocommerce-cart' => wc_get_page_id('cart'),
				'woocommerce-checkout' => wc_get_page_id('checkout'),
				'woocommerce-account' => wc_get_page_id('myaccount'),
			);
			foreach ($woocommerce_pages as $context => $page_id) {
				if ($page_id > 0 && (int) $post->ID === (int) $page_id) $contexts[] = $context;
			}
		}

		return $contexts;
	}

	/**
	 * Generate CSS for the Gutenberg editor using .editor-styles-wrapper selector.
	 */
	function corsivo_theme_generate_editor_template_css($tpl) {
		$sel = '.editor-styles-wrapper';
		$css = '';

		// Body
		$body = isset($tpl['body']) ? $tpl['body'] : array();
		if (!empty($body)) {
			$s = '';
			if (!empty($body['background'])) $s .= 'background-color:' . corsivo_theme_color_var($body['background']) . ';';
			if (!empty($body['color']))      $s .= 'color:' . corsivo_theme_color_var($body['color']) . ';';
			if (!empty($body['font']))       $s .= corsivo_theme_font_css_declarations($body['font']);
			if ($s) $css .= $sel . '{' . $s . '}' . "\n";
		}

		// Headings
		$heading = isset($tpl['heading']) ? $tpl['heading'] : array();
		if (!empty($heading)) {
			$s = '';
			if (!empty($heading['font']))  $s .= corsivo_theme_font_css_declarations($heading['font']);
			if (!empty($heading['color'])) $s .= 'color:' . corsivo_theme_color_var($heading['color']) . ';';
			if (!empty($heading['fontWeight'])) $s .= 'font-weight:' . $heading['fontWeight'] . ';';
			if ($s) {
				$h = array();
				for ($i = 1; $i <= 6; $i++) $h[] = $sel . ' h' . $i;
				$css .= implode(',', $h) . '{' . $s . '}' . "\n";
			}
		}

		// Page title
		if (!empty($tpl['titleStyle'])) {
			$title_css = corsivo_theme_font_css_declarations($tpl['titleStyle']);
			if ($title_css) {
				$css .= implode(',', corsivo_theme_get_editor_page_title_selectors()) . '{' . $title_css . '}' . "\n";
			}
		}

		// Default paragraph / heading blocks
		$css .= corsivo_theme_generate_default_block_css($tpl, '', true);

		// Links
		$link = isset($tpl['link']) ? $tpl['link'] : array();
		if (!empty($link) && !empty($link['color'])) {
			$css .= $sel . ' a:where(:not(.wp-element-button)){color:' . corsivo_theme_color_var($link['color']) . ';}' . "\n";
		}

		return $css;
	}

	/**
	 * Register the per-page style template override.
	 * Available on all public post types via register_post_meta('', ...).
	 */
	function corsivo_theme_register_post_meta() {
		register_post_meta('', '_corsivo_theme_style_template', array(
			'show_in_rest'  => true,
			'single'        => true,
			'type'          => 'string',
			'default'       => '',
			'sanitize_callback' => 'sanitize_title',
			'auth_callback' => function ($allowed, $meta_key, $post_id) {
				return current_user_can('edit_post', $post_id);
			},
		));
	}
	add_action('init', 'corsivo_theme_register_post_meta');

	/**
	 * Inject style template CSS into the Gutenberg editor based on post context.
	 * Priority: per-page meta override → context assignment → nothing.
	 * Also enqueues the sidebar panel script with template options.
	 */
	function corsivo_theme_editor_context_styles() {
		global $post;
		if (!$post) return;

		$footer_id = get_option( 'corsivo_theme_footer_page_id' );
		$is_footer_page = $footer_id && (int) $post->ID === (int) $footer_id;

		$style_templates = apply_filters('corsivo_theme_style_templates', array());
		$context_styles  = apply_filters('corsivo_theme_context_styles', array());
		$matched_tpl = corsivo_theme_resolve_style_template_for_post($post, $style_templates, $context_styles);
		$global_title_css = corsivo_theme_generate_editor_global_page_title_css();
		$global_default_block_css = corsivo_theme_generate_editor_global_default_block_css();
		$automatic_css = $global_title_css . $global_default_block_css;
		if ($matched_tpl) {
			$automatic_css .= corsivo_theme_generate_editor_template_css($matched_tpl);
		}

		// Inject CSS for the resolved automatic template
		if ($automatic_css) {
			wp_add_inline_style('wp-edit-blocks', corsivo_theme_sanitize_css_block($automatic_css));
		}

		// --- 3. Backend inline CSS (replaces backend.css) ---
		$fonts = apply_filters('corsivo_theme_fonts', array());
		$backend_css = '';
		$hidden_styles = array();

		foreach ($fonts as $font) {
			// Per-style CSS for the editor (font-size, line-height, etc.)
			if (!empty($font['slug'])) {
				$style_css = '';
				if (isset($font['css']) && !empty($font['css'])) {
					$style_css = $font['css'];
				} else {
					foreach ($font as $field => $value) {
						$reserved = array('slug','name','default','hide','scope','scopes','breakpoints','css');
						if (in_array($field, $reserved)) continue;
						if (corsivo_theme_check_field($field, $value)) {
							$style_css .= $field . ':' . $value . ';';
						}
					}
				}
				if ($style_css) {
					$backend_css .= '.editor-styles-wrapper .is-style-font-' . $font['slug'] . '{' . $style_css . '}';
				}
				$backend_css .= corsivo_theme_generate_font_breakpoint_css($font, '.editor-styles-wrapper ');
			}

			// Collect hidden font slugs for JS unregister
			if (isset($font['hide']) && $font['hide'] && !empty($font['slug'])) {
				$hidden_styles[] = 'font-' . $font['slug'];
			}
		}

		// Editor block margins
		$backend_css .= 'html .block-editor-block-list__layout .wp-block-heading, html .block-editor-block-list__layout .wp-block-paragraph { margin-top: 1rem; margin-bottom: 1rem; }';

		if ($backend_css) {
			wp_add_inline_style('wp-edit-blocks', corsivo_theme_sanitize_css_block($backend_css));
		}

		// --- 4. Enqueue sidebar panel script (skipped for the footer page) ---
		if (!$is_footer_page) {
			$options = array();
			foreach ($style_templates as $tpl) {
				if (empty($tpl['slug'])) continue;
				$options[] = array(
					'slug' => $tpl['slug'],
					'name' => !empty($tpl['name']) ? $tpl['name'] : $tpl['slug'],
				);
			}

			// Build a slug → CSS map so JS can live-preview template changes
			$css_map = array();
			foreach ($style_templates as $tpl) {
				if (empty($tpl['slug'])) continue;
				$css_map[$tpl['slug']] = $global_title_css . $global_default_block_css . corsivo_theme_generate_editor_template_css($tpl);
			}

			wp_enqueue_script(
				'corsivo-theme-editor-panel',
				plugin_dir_url(__FILE__) . 'admin/editor-panel.js',
				array('wp-plugins', 'wp-editor', 'wp-edit-post', 'wp-components', 'wp-data', 'wp-element'),
				filemtime(plugin_dir_path(__FILE__) . 'admin/editor-panel.js'),
				true
			);

			wp_localize_script('corsivo-theme-editor-panel', 'corsivoThemeEditorPanel', array(
				'options'      => $options,
				'cssMap'       => !empty($css_map) ? $css_map : (object) array(),
				'automaticCss' => $automatic_css,
				'previewClass' => 'salvatorecorsi-dynamic-blocks-preview',
			));
		}

		$inline_script_handle = $is_footer_page ? 'wp-edit-post' : 'corsivo-theme-editor-panel';

		// --- 5. Unregister hidden block styles via JS ---
		// Hidden font styles (paragraph/heading)
		if (!empty($hidden_styles)) {
			$js = 'wp.domReady(function(){';
			foreach ($hidden_styles as $style_name) {
				$js .= 'wp.blocks.unregisterBlockStyle("core/paragraph",' . wp_json_encode($style_name) . ');';
				$js .= 'wp.blocks.unregisterBlockStyle("core/heading",' . wp_json_encode($style_name) . ');';
			}
			$js .= '});';
			wp_add_inline_script($inline_script_handle, $js);
		}

		// --- 6. Unregister hidden core-component block styles via JS ---
		$config = corsivo_theme_get_settings();
		$cc = isset($config['configurator']['core_components']) ? $config['configurator']['core_components'] : array();
		$cc_block_styles = isset($cc['blockStyles']) ? $cc['blockStyles'] : array();
		$hidden_cc = array();
		foreach ($cc_block_styles as $bs) {
			if (!empty($bs['hideInEditor']) && !empty($bs['target']) && !empty($bs['slug'])) {
				$hidden_cc[] = array('block' => $bs['target'], 'style' => $bs['slug']);
			}
		}
		if (!empty($hidden_cc)) {
			$js2 = 'wp.domReady(function(){';
			foreach ($hidden_cc as $h) {
				$js2 .= 'wp.blocks.unregisterBlockStyle(' . wp_json_encode($h['block']) . ',' . wp_json_encode($h['style']) . ');';
			}
			$js2 .= '});';
			wp_add_inline_script($inline_script_handle, $js2);
		}
	}
	add_action('enqueue_block_editor_assets', 'corsivo_theme_editor_context_styles');

	/**
	 * Add body class for per-page style template on the frontend.
	 * Outputs "corsivo-theme-template-{slug}" so CSS can target it.
	 */
	function corsivo_theme_template_body_class($classes) {
		if (is_singular()) {
			$meta_slug = get_post_meta(get_the_ID(), '_corsivo_theme_style_template', true);
			if (!empty($meta_slug)) {
				$classes[] = 'corsivo-theme-template-' . sanitize_html_class($meta_slug);
			}

			// Own canonical page-template class so page-template context CSS
			// matches on the frontend for subdir/CPT templates too (not only the
			// cases where WP's native body class happens to coincide).
			$template = get_page_template_slug(get_the_ID());
			if ($template) {
				$classes[] = corsivo_theme_page_template_context_slug($template);
			}
		}

		if ( is_home() && ! is_front_page() ) {
			$page_id = (int) get_option( 'page_for_posts' );
			$meta_slug = '';

			if ( $page_id ) {
				$meta_slug = get_post_meta( $page_id, '_corsivo_theme_style_template', true );
			}

			if ( empty( $meta_slug ) ) {
				$meta_slug = 'article';
			}

			$classes[] = 'corsivo-theme-template-' . sanitize_html_class( $meta_slug );
		}

		$archive_context = $GLOBALS['corsivo_theme_archive_page_context'] ?? array();
		if (is_post_type_archive() || !empty($archive_context['post_type'])) {
			$corsivo_theme_config   = corsivo_theme_get_settings();
			$current_lang = corsivo_theme_current_language();
			$post_type = !empty($archive_context['post_type']) ? $archive_context['post_type'] : get_query_var('post_type');
			if (is_array($post_type)) $post_type = $post_type[0];
			$page_id = !empty($archive_context['page_id'])
				? (int) $archive_context['page_id']
				: ($corsivo_theme_config['custom_slugs'][$post_type][$current_lang]['archive_page'] ?? '');
			$meta_slug = '';
			$classes[] = 'archive';
			$classes[] = 'post-type-archive';
			$classes[] = 'post-type-archive-' . sanitize_html_class($post_type);

			// Try from archive page meta
			if ($page_id) {
				$meta_slug = get_post_meta((int) $page_id, '_corsivo_theme_style_template', true);
			}

			if (!empty($meta_slug)) {
				$classes[] = 'corsivo-theme-template-' . sanitize_html_class($meta_slug);
			}
		}

		return $classes;
	}
	add_filter('body_class', 'corsivo_theme_template_body_class');

	function corsivo_theme_register_configured_block_styles() {

		$breakpoints_values = apply_filters('corsivo_theme_breakpoints', array(
			'm' => 1024,
			'desktop' => 1024,
			'tablet' => 768,
			's' => 768,
			'mobile' => 480,
			'xs' => 480,
		));

		$fonts = apply_filters('corsivo_theme_fonts', array());
		$scoped_fonts = false;

		foreach($fonts as $font) {
			if(is_array($font) && array_key_exists('scope', $font)) {
				$scoped_fonts = true;
				break;
			}
		}

		$install_fonts = apply_filters('corsivo_theme_install_fonts', array());
		$reserved_names = array('slug', 'name', 'breakpoints', 'default', 'hide', 'scopes', 'scope', 'css');

		// Register font block styles
		$user_fonts = apply_filters('corsivo_theme_user_fonts', $fonts);

		foreach($user_fonts as $user_font) {
			if (!is_array($user_font) || empty($user_font['slug'])) continue;

			$name = isset($user_font['label']) ? $user_font['label'] : '';
			$name = empty($name) && isset($user_font['name']) ? $user_font['name'] : $user_font['slug'];

			$name = str_replace(array('-','_'), ' ', $name);
			$name = preg_replace('/\b(?:Desktop|Mobile|Tablet|desktop|mobile|tablet)\b/', '', $name);
			$name = trim($name);
			$name = ucwords($name);
			if(substr($name, 0, 1) == ' ') {
				$name = substr($name, 1);
			}

			if($scoped_fonts) {
				if(array_key_exists('scope', $user_font)) {
					corsivo_theme_register_font_style($user_font, $name, $user_font['scope']);
				}
				else {
					corsivo_theme_register_font_style($user_font, $name);
				}
			}
			else {
				corsivo_theme_register_font_style($user_font, $name);
			}
		}

		$colors = apply_filters('corsivo_theme_colors', array());

		$breakpoints = array();

	}
	function corsivo_theme_to_css() {
		return corsivo_theme_register_configured_block_styles();
	}
	add_action('init', 'corsivo_theme_register_configured_block_styles', 20);

	/**
	 * Escape a string for safe use as an SCSS quoted key.
	 */
	function corsivo_theme_scss_quote($value) {
		return "'" . str_replace(array('\\', "'"), array('\\\\', "\\'"), (string) $value) . "'";
	}

	/**
	 * Generate a small SCSS build artifact from the configurator data.
	 */
	function corsivo_theme_generate_scss_build() {
		$colors = apply_filters('corsivo_theme_colors', array());
		$fonts = apply_filters('corsivo_theme_fonts', array());
		$breakpoints = apply_filters('corsivo_theme_breakpoints', array());
		$spacing = apply_filters('corsivo_theme_spacing', array());
		$spacing_sizes = !empty($spacing['spacingSizes']) && is_array($spacing['spacingSizes']) ? $spacing['spacingSizes'] : array();

		$lines = array(
			'// Auto-generated by Theme.',
			'// Do not edit directly.',
			'',
			'$corsivo-theme-colors: (',
		);

		foreach ($colors as $color) {
			if (empty($color['slug']) || empty($color['color'])) continue;
			$lines[] = '  ' . corsivo_theme_scss_quote($color['slug']) . ': ' . $color['color'] . ',';
		}
		$lines[] = ');';
		$lines[] = '';
		$lines[] = '$corsivo-theme-font-families: (';

		foreach ($fonts as $font) {
			if (empty($font['slug'])) continue;

			$font_family = '';
			if (!empty($font['css'])) {
				$font_family = corsivo_theme_parse_css_property($font['css'], 'font-family');
			}

			if (!$font_family) continue;
			$scss_value = strpos($font_family, ',') !== false ? '(' . $font_family . ')' : $font_family;
			$lines[] = '  ' . corsivo_theme_scss_quote($font['slug']) . ': ' . $scss_value . ',';
		}
		$lines[] = ');';
		$lines[] = '';
		$lines[] = '$corsivo-theme-breakpoints: (';

		foreach ($breakpoints as $name => $value) {
			if ($value === '' || $value === null) continue;
			$unit_value = is_numeric($value) ? $value . 'px' : $value;
			$lines[] = '  ' . corsivo_theme_scss_quote($name) . ': ' . $unit_value . ',';
		}
		$lines[] = ');';
		$lines[] = '';
		$lines[] = '$corsivo-theme-spacing: (';

		foreach ($spacing_sizes as $preset) {
			if (empty($preset['slug']) || empty($preset['size'])) continue;
			$lines[] = '  ' . corsivo_theme_scss_quote($preset['slug']) . ': ' . $preset['size'] . ',';
		}
		$lines[] = ');';
		$lines[] = '';
		$lines[] = '@mixin corsivo-theme-down($breakpoint) {';
		$lines[] = '  $value: map-get($corsivo-theme-breakpoints, $breakpoint);';
		$lines[] = '  @if $value {';
		$lines[] = '    @media (max-width: $value) {';
		$lines[] = '      @content;';
		$lines[] = '    }';
		$lines[] = '  }';
		$lines[] = '}';
		$lines[] = '';
		$lines[] = ':root {';

		foreach ($colors as $color) {
			if (empty($color['slug']) || empty($color['color'])) continue;
			$lines[] = '  --corsivo-theme-color-' . $color['slug'] . ': ' . $color['color'] . ';';
		}

		foreach ($fonts as $font) {
			if (empty($font['slug'])) continue;

			$font_family = '';
			if (!empty($font['css'])) {
				$font_family = corsivo_theme_parse_css_property($font['css'], 'font-family');
			}

			if (!$font_family) continue;
			$lines[] = '  --corsivo-theme-font-' . $font['slug'] . ': ' . $font_family . ';';
		}

		$lines[] = '}';
		$lines[] = '';

		return implode("\n", $lines);
	}

	function corsivo_theme_get_function_page($slug) {
		$corsivo_theme_config = corsivo_theme_get_settings();
		$current_lang = corsivo_theme_current_language();
		$slug = sanitize_key($slug);

		if (empty($corsivo_theme_config['functional_pages'][$slug]) || !is_array($corsivo_theme_config['functional_pages'][$slug])) {
			return null;
		}

		$langs = $corsivo_theme_config['functional_pages'][$slug];
		$page_id = 0;

		if (!empty($langs[$current_lang])) {
			$page_id = (int) $langs[$current_lang];
		} elseif (!corsivo_theme_wpml_enabled()) {
			foreach ($langs as $value) {
				if (!empty($value)) {
					$page_id = (int) $value;
					break;
				}
			}
		}

		if (!$page_id) {
			return null;
		}

		$page = get_post($page_id);
		if (!$page || !is_post_publicly_viewable($page) || post_password_required($page)) {
			return null;
		}
		return $page;
	}

	function corsivo_theme_get_function_page_content($slug) {
		$page = corsivo_theme_get_function_page($slug);
		if (!$page) {
			return '';
		}
		$post_content = get_post_field('post_content', $page->ID);
		$parse_blocks = parse_blocks($post_content);
		$content = '';
			foreach($parse_blocks as $block) {
				$content .= render_block($block);
			}
		return $content;
	}
