<?php
/**
 * Name: SVG Upload
 * Description: Abilita upload e anteprime SVG nella media library.
 * Category: Media
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'corsivo_toolkit_svg_current_user_can_upload' ) ) {

	require_once dirname(__DIR__) . '/includes/svg-sanitizer.php';

	function corsivo_toolkit_svg_current_user_can_upload() {
		return current_user_can( apply_filters( 'corsivo_toolkit_svg_upload_capability', 'manage_options' ) );
	}

	function corsivo_toolkit_svg_get_file_contents( $path, $extension = '' ) {
		if ( ! file_exists( $path ) || ! is_readable( $path ) ) {
			corsivo_core_toolkit_log( sprintf( 'SVG Upload: file "%s" is missing or not readable.', $path ) );
			return '';
		}

		$limit = 2 * 1024 * 1024;
		$contents = file_get_contents( $path, false, null, 0, $limit + 1 );

		if ( false === $contents || strlen( $contents ) > $limit ) {
			corsivo_core_toolkit_log( sprintf( 'SVG Upload: could not read "%s".', $path ) );
			return '';
		}

		if ( 'svgz' === strtolower( $extension ) ) {
			if ( ! function_exists( 'gzdecode' ) ) {
				corsivo_core_toolkit_log( 'SVG Upload: gzdecode() is unavailable, svgz files cannot be read.' );
				return '';
			}

			$contents = @gzdecode( $contents, $limit + 1 );

			if ( false === $contents || strlen( $contents ) > $limit ) {
				corsivo_core_toolkit_log( sprintf( 'SVG Upload: could not decompress "%s".', $path ) );
				return '';
			}
		}

		return $contents;
	}

	function corsivo_toolkit_svg_is_safe( $contents ) {
		$sanitized = corsivo_core_svg_sanitize( $contents );
		if ( ! is_string( $sanitized ) ) {
			return false;
		}
		$original = new DOMDocument();
		$clean = new DOMDocument();
		return @$original->loadXML( $contents, LIBXML_NONET ) && @$clean->loadXML( $sanitized, LIBXML_NONET )
			&& $original->C14N() === $clean->C14N();
	}

	function corsivo_toolkit_svg_validate_upload( $file ) {
		if ( empty( $file['name'] ) || empty( $file['tmp_name'] ) ) {
			return $file;
		}

		$extension = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );

		if ( ! in_array( $extension, array( 'svg', 'svgz' ), true ) ) {
			return $file;
		}

		if ( ! corsivo_toolkit_svg_current_user_can_upload() ) {
			$file['error'] = __( 'Non puoi caricare file SVG.', 'corsivo-core-editor' );
			return $file;
		}

		$contents = corsivo_toolkit_svg_get_file_contents( $file['tmp_name'], $extension );

		if ( '' === $contents ) {
			$file['error'] = __( 'Impossibile leggere il file SVG.', 'corsivo-core-editor' );
			return $file;
		}

		$sanitized = corsivo_core_svg_sanitize( $contents );
		if ( ! is_string( $sanitized ) ) {
			$file['error'] = __( 'Il file SVG contiene markup non consentito.', 'corsivo-core-editor' );
			return $file;
		}
		$stored = 'svgz' === $extension ? gzencode( $sanitized ) : $sanitized;
		if ( ! is_string( $stored ) || file_put_contents( $file['tmp_name'], $stored, LOCK_EX ) !== strlen( $stored ) ) {
			$file['error'] = __( 'Impossibile salvare il file SVG sanitizzato.', 'corsivo-core-editor' );
			return $file;
		}
		$file['size'] = strlen( $stored );

		return $file;
	}
	add_filter( 'wp_handle_upload_prefilter', 'corsivo_toolkit_svg_validate_upload' );
	add_filter( 'wp_handle_sideload_prefilter', 'corsivo_toolkit_svg_validate_upload' );

	function corsivo_toolkit_svg_upload_mimes( $mimes ) {
		if ( ! corsivo_toolkit_svg_current_user_can_upload() ) {
			return $mimes;
		}

		$mimes['svg'] = 'image/svg+xml';
		$mimes['svgz'] = 'image/svg+xml';

		return $mimes;
	}
	add_filter( 'upload_mimes', 'corsivo_toolkit_svg_upload_mimes' );

	function corsivo_toolkit_svg_check_filetype_and_ext( $data, $file, $filename, $mimes ) {
		$extension = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );

		if ( ! in_array( $extension, array( 'svg', 'svgz' ), true ) || ! corsivo_toolkit_svg_current_user_can_upload() ) {
			return $data;
		}

		$data['ext'] = $extension;
		$data['type'] = 'image/svg+xml';
		$data['proper_filename'] = $filename;

		return $data;
	}
	add_filter( 'wp_check_filetype_and_ext', 'corsivo_toolkit_svg_check_filetype_and_ext', 100, 4 );

	function corsivo_toolkit_svg_get_dimensions( $path ) {
		$dimensions = array(
			'width'  => 0,
			'height' => 0,
		);

		$extension = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
		$contents = corsivo_toolkit_svg_get_file_contents( $path, $extension );
		$contents = corsivo_core_svg_sanitize( $contents );

		if ( ! is_string( $contents ) || '' === trim( $contents ) || ! function_exists( 'simplexml_load_string' ) ) {
			return $dimensions;
		}

		$previous = libxml_use_internal_errors( true );
		$svg = simplexml_load_string( $contents, 'SimpleXMLElement', LIBXML_NONET );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		if ( ! $svg || 'svg' !== strtolower( $svg->getName() ) ) {
			return $dimensions;
		}

		$attributes = $svg->attributes();

		if ( isset( $attributes->width, $attributes->height ) ) {
			$dimensions['width'] = (float) $attributes->width;
			$dimensions['height'] = (float) $attributes->height;
		}

		if ( ( ! $dimensions['width'] || ! $dimensions['height'] ) && isset( $attributes->viewBox ) ) {
			$view_box = preg_split( '/[\s,]+/', trim( (string) $attributes->viewBox ) );

			if ( isset( $view_box[2], $view_box[3] ) ) {
				$dimensions['width'] = (float) $view_box[2];
				$dimensions['height'] = (float) $view_box[3];
			}
		}

		return $dimensions;
	}

	function corsivo_toolkit_svg_generate_attachment_metadata( $metadata, $attachment_id ) {
		if ( 'image/svg+xml' !== get_post_mime_type( $attachment_id ) ) {
			return $metadata;
		}

		$dimensions = corsivo_toolkit_svg_get_dimensions( get_attached_file( $attachment_id ) );

		if ( $dimensions['width'] && $dimensions['height'] ) {
			$metadata['width'] = $dimensions['width'];
			$metadata['height'] = $dimensions['height'];
		} else {
			corsivo_core_toolkit_log( sprintf( 'SVG Upload: no readable dimensions for attachment %d, metadata left without width/height.', $attachment_id ) );
		}

		return $metadata;
	}
	add_filter( 'wp_generate_attachment_metadata', 'corsivo_toolkit_svg_generate_attachment_metadata', 10, 2 );

	function corsivo_toolkit_svg_prepare_attachment_for_js( $response, $attachment, $meta ) {
		if ( empty( $response['mime'] ) || 'image/svg+xml' !== $response['mime'] || ! empty( $response['sizes'] ) ) {
			return $response;
		}

		$dimensions = corsivo_toolkit_svg_get_dimensions( get_attached_file( $attachment->ID ) );

		if ( ! $dimensions['width'] || ! $dimensions['height'] ) {
			return $response;
		}

		$response['sizes'] = array(
			'full' => array(
				'url'         => $response['url'],
				'width'       => $dimensions['width'],
				'height'      => $dimensions['height'],
				'orientation' => $dimensions['width'] > $dimensions['height'] ? 'landscape' : 'portrait',
			),
		);

		return $response;
	}
	add_filter( 'wp_prepare_attachment_for_js', 'corsivo_toolkit_svg_prepare_attachment_for_js', 10, 3 );
}
