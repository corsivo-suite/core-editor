<?php
/**
 * Name: Grid
 * Description: Aggiunge funzione e shortcode per griglie di post.
 * Category: Frontend
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'corsivo_toolkit_grid_enqueue_styles' ) ) {

	function corsivo_toolkit_grid_enqueue_styles() {
		wp_enqueue_style(
			'corsivo-toolkit-grid',
			plugin_dir_url( CORSIVO_CORE_EDITOR_FILE ) . 'toolkit/grid/style.css',
			array(),
			(string) filemtime( __DIR__ . '/style.css' )
		);
	}
	add_action( 'wp_enqueue_scripts', 'corsivo_toolkit_grid_enqueue_styles' );

	function corsivo_toolkit_grid_get_item_id( $item ) {
		if ( is_numeric( $item ) ) {
			return (int) $item;
		}

		if ( is_object( $item ) && isset( $item->ID ) ) {
			return (int) $item->ID;
		}

		if ( is_array( $item ) && isset( $item['ID'] ) ) {
			return (int) $item['ID'];
		}

		return 0;
	}

	function corsivo_toolkit_grid_get_image_info( $item ) {
		$aspect_ratio = '1 / 1';
		$width = 1;
		$height = 1;
		$post_id = corsivo_toolkit_grid_get_item_id( $item );
		$media_id = $post_id ? get_post_thumbnail_id( $post_id ) : 0;

		if ( $media_id ) {
			$metadata = wp_get_attachment_metadata( $media_id );

			if ( ! empty( $metadata['width'] ) && ! empty( $metadata['height'] ) ) {
				$width = (float) $metadata['width'];
				$height = (float) $metadata['height'];
				$aspect_ratio = $width . ' / ' . $height;
			}
		}

		if ( $width > $height ) {
			$format = 'landscape';
		} elseif ( $width < $height ) {
			$format = 'portrait';
		} else {
			$format = 'square';
		}

		return array(
			'aspect_ratio' => $aspect_ratio,
			'format'       => $format,
			'grow'         => $height ? $width / $height : 1,
		);
	}

	function corsivo_toolkit_grid_compute_permutations( $items ) {
		$count = count( $items );

		if ( 1 === $count ) {
			return array( $items );
		}

		$results = array();

		for ( $index = 0; $index < $count; $index++ ) {
			$first = $items[ $index ];
			$remaining = array_merge( array_slice( $items, 0, $index ), array_slice( $items, $index + 1 ) );

			foreach ( corsivo_toolkit_grid_compute_permutations( $remaining ) as $permutation ) {
				$results[] = array_merge( array( $first ), $permutation );
			}
		}

		return $results;
	}

	function corsivo_toolkit_grid_generate_rows( $items, $patterns = array() ) {
		if ( empty( $patterns ) ) {
			$patterns = array(
				array( 'portrait', 'portrait', 'portrait' ),
				array( 'landscape', 'portrait' ),
				array( 'portrait', 'portrait' ),
				array( 'square', 'square', 'square' ),
				array( 'square', 'square' ),
				array( 'landscape' ),
			);
		}

		$available_patterns = array();

		foreach ( $patterns as $pattern ) {
			$available_patterns = array_merge( $available_patterns, corsivo_toolkit_grid_compute_permutations( $pattern ) );
		}

		$available_patterns = array_map( 'unserialize', array_unique( array_map( 'serialize', $available_patterns ) ) );
		$formats = array_map(
			function ( $item ) {
				return corsivo_toolkit_grid_get_image_info( $item )['format'];
			},
			$items
		);
		$rows = array();

		while ( count( $formats ) > 0 ) {
			$take = 0;

			foreach ( $available_patterns as $pattern ) {
				if ( array_slice( $formats, 0, count( $pattern ) ) === $pattern ) {
					$take = count( $pattern );
					break;
				}
			}

			if ( ! $take ) {
				$take = min( 2, count( $formats ) );
				corsivo_core_toolkit_log( sprintf( 'Grid: no pattern matched [%s], falling back to a %d-item row.', implode( ', ', array_slice( $formats, 0, $take ) ), $take ) );
			}

			$rows[] = array_slice( $items, 0, $take );
			$items = array_slice( $items, $take );
			$formats = array_slice( $formats, $take );
		}

		return $rows;
	}

	function corsivo_toolkit_render_grid_item( $item ) {
		$post_id = corsivo_toolkit_grid_get_item_id( $item );
		$post = $post_id ? get_post( $post_id ) : null;

		if ( ! $post ) {
			return;
		}

		$url = get_permalink( $post );
		?>
		<article class="corsivo-toolkit-grid-card">
			<?php if ( has_post_thumbnail( $post ) ) : ?>
				<a class="corsivo-toolkit-grid-card__media" href="<?php echo esc_url( $url ); ?>">
					<?php echo get_the_post_thumbnail( $post, 'large', array( 'loading' => 'lazy' ) ); ?>
				</a>
			<?php endif; ?>
			<h3 class="corsivo-toolkit-grid-card__title">
				<a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( get_the_title( $post ) ); ?></a>
			</h3>
		</article>
		<?php
	}

	function corsivo_toolkit_grid_render_item_callback( $item, $args ) {
		if ( ! is_callable( $args['item_render_callback'] ) ) {
			corsivo_core_toolkit_log( 'Grid: item_render_callback is not callable, item skipped.' );
			return;
		}

		call_user_func( $args['item_render_callback'], $item, $args );
	}

	function corsivo_toolkit_render_grid( $items = array(), $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'id'                   => '',
				'class'                => '',
				'gap'                  => '1rem',
				'columns'              => 3,
				'patterns'             => array(),
				'item_render_callback' => 'corsivo_toolkit_render_grid_item',
			)
		);
		$args['columns'] = max( 1, (int) $args['columns'] );
		$mobile_columns = min( 2, $args['columns'] );

		corsivo_toolkit_grid_enqueue_styles();

		$extra_classes = array_filter( array_map( 'sanitize_html_class', preg_split( '/\s+/', (string) $args['class'] ) ) );
		$classes = implode( ' ', array_merge( array( 'corsivo-toolkit-grid-wrap' ), $extra_classes ) );
		$style = sprintf(
			'--corsivo-toolkit-grid-gap:%s;--corsivo-toolkit-grid-columns:%d;--corsivo-toolkit-grid-mobile-columns:%d;',
			esc_attr( $args['gap'] ),
			$args['columns'],
			$mobile_columns
		);
		?>
		<div<?php echo $args['id'] ? ' id="' . esc_attr( $args['id'] ) . '"' : ''; ?> class="<?php echo esc_attr( $classes ); ?>" style="<?php echo esc_attr( $style ); ?>">
			<?php if ( empty( $items ) ) : ?>
				<div class="corsivo-toolkit-grid-empty">
					<?php do_action( 'corsivo_toolkit_grid_no_items', $args ); ?>
				</div>
			<?php elseif ( ! empty( $args['patterns'] ) ) : ?>
				<div class="corsivo-toolkit-grid corsivo-toolkit-grid--pattern">
					<?php foreach ( corsivo_toolkit_grid_generate_rows( $items, $args['patterns'] ) as $row ) : ?>
						<div class="corsivo-toolkit-grid-row">
							<?php foreach ( $row as $item ) : ?>
								<?php $image_info = corsivo_toolkit_grid_get_image_info( $item ); ?>
								<div class="corsivo-toolkit-grid-cell" style="<?php echo esc_attr( 'aspect-ratio:' . $image_info['aspect_ratio'] . ';flex-grow:' . $image_info['grow'] . ';' ); ?>">
									<?php corsivo_toolkit_grid_render_item_callback( $item, $args ); ?>
								</div>
							<?php endforeach; ?>
						</div>
					<?php endforeach; ?>
				</div>
			<?php else : ?>
				<div class="corsivo-toolkit-grid">
					<?php foreach ( $items as $item ) : ?>
						<?php corsivo_toolkit_grid_render_item_callback( $item, $args ); ?>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	function corsivo_toolkit_get_grid( $items = array(), $args = array() ) {
		ob_start();
		corsivo_toolkit_render_grid( $items, $args );
		return ob_get_clean();
	}

	function corsivo_toolkit_grid_shortcode( $attributes ) {
		$attributes = shortcode_atts(
			array(
				'post_type'      => 'post',
				'posts_per_page' => 6,
				'columns'        => 3,
				'gap'            => '1rem',
				'orderby'        => 'date',
				'order'          => 'DESC',
				'ids'            => '',
				'class'          => '',
			),
			$attributes,
			'corsivo_grid'
		);

		$post_type = sanitize_key( $attributes['post_type'] );
		if ( ! is_post_type_viewable( $post_type ) ) {
			return '';
		}
		$query_args = array(
			'post_type'      => $post_type,
			'posts_per_page' => max( 1, (int) $attributes['posts_per_page'] ),
			'post_status'    => 'publish',
			'has_password'   => false,
			'orderby'        => sanitize_key( $attributes['orderby'] ),
			'order'          => 'ASC' === strtoupper( $attributes['order'] ) ? 'ASC' : 'DESC',
		);

		if ( $attributes['ids'] ) {
			$ids = array_filter( array_map( 'absint', explode( ',', $attributes['ids'] ) ) );
			$query_args['post__in'] = $ids ?: array( 0 );
			$query_args['orderby'] = 'post__in';
			$query_args['posts_per_page'] = max( 1, count( $ids ) );
		}

		$query = new WP_Query( $query_args );
		$html = corsivo_toolkit_get_grid(
			$query->posts,
			array(
				'columns' => (int) $attributes['columns'],
				'gap'     => $attributes['gap'],
				'class'   => $attributes['class'],
			)
		);

		wp_reset_postdata();

		return $html;
	}
	add_shortcode( 'corsivo_grid', 'corsivo_toolkit_grid_shortcode' );
}
