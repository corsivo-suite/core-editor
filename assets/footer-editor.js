( function( wp ) {
	const { createElement: el, useEffect } = wp.element;
	const { PluginDocumentSettingPanel } = wp.editor;
	const { useSelect, useDispatch } = wp.data;
	const { ColorPalette, Button, BaseControl } = wp.components;

	const themeColors = window.corsivoThemeFooterColors || [];

	function findSlug( hexColor ) {
		if ( ! hexColor ) return '';
		const match = themeColors.find( ( c ) => c.color.toLowerCase() === hexColor.toLowerCase() );
		return match ? match.slug : '';
	}

	function applyPreview( bgColor, textColor ) {
		const iframe = document.querySelector( 'iframe[name="editor-canvas"]' );
		const target = iframe
			? iframe.contentDocument?.querySelector( '.editor-styles-wrapper' )
			: document.querySelector( '.editor-styles-wrapper' );
		if ( target ) {
			target.style.backgroundColor = bgColor || '';
			target.style.color = textColor || '';
		}
	}

	function FooterPanel() {
		const meta = useSelect( ( s ) =>
			s( 'core/editor' ).getEditedPostAttribute( 'meta' ) || {}
		);
		const { editPost } = useDispatch( 'core/editor' );

		const bgColor   = meta._corsivo_theme_footer_bg || '';
		const textColor = meta._corsivo_theme_footer_color || '';

		// Live preview
		useEffect( () => {
			applyPreview( bgColor, textColor );
			const timer = setTimeout( () => applyPreview( bgColor, textColor ), 500 );
			return () => clearTimeout( timer );
		}, [ bgColor, textColor ] );

		const setBg = ( color ) => {
			editPost( { meta: {
				_corsivo_theme_footer_bg: color || '',
				_corsivo_theme_footer_bg_slug: findSlug( color ),
			} } );
		};

		const setColor = ( color ) => {
			editPost( { meta: {
				_corsivo_theme_footer_color: color || '',
				_corsivo_theme_footer_color_slug: findSlug( color ),
			} } );
		};

		return el( PluginDocumentSettingPanel, {
			name: 'corsivo-theme-footer-colors',
			title: 'Footer',
			icon: 'art',
		},
			el( BaseControl, { label: 'Background', __nextHasNoMarginBottom: true },
				el( ColorPalette, {
					colors: themeColors,
					value: bgColor,
					onChange: setBg,
					clearable: true,
					enableAlpha: true,
				} )
			),
			el( BaseControl, { label: 'Text color', __nextHasNoMarginBottom: true },
				el( ColorPalette, {
					colors: themeColors,
					value: textColor,
					onChange: setColor,
					clearable: true,
				} )
			),
			( bgColor || textColor ) && el( Button, {
				variant: 'tertiary',
				isSmall: true,
				onClick: () => { setBg( '' ); setColor( '' ); },
				style: { marginTop: '0.5rem' },
			}, 'Reset all' )
		);
	}

	wp.plugins.registerPlugin( 'corsivo-theme-footer-colors', {
		render: FooterPanel,
		icon: 'art',
	} );
} )( window.wp );
