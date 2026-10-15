function parseCssProperties( cssText = '' ) {
	return cssText.split( ';' ).reduce( ( properties, declaration ) => {
		const separator = declaration.indexOf( ':' );
		if ( separator === -1 ) {
			return properties;
		}
		const property = declaration.slice( 0, separator ).trim();
		const value = declaration.slice( separator + 1 ).trim();
		if ( property && value ) {
			properties[ property ] = value;
		}
		return properties;
	}, {} );
}

export function getFontPreviewStyles( font, breakpoints, activeBreakpoint ) {
	let properties = parseCssProperties( font.css );
	const activeWidth = Number( breakpoints[ activeBreakpoint ] );
	if ( activeBreakpoint !== 'desktop' && activeWidth > 0 ) {
		Object.entries( font.breakpoints || {} )
			.map( ( [ name, override ] ) => [ override, Number( breakpoints[ name ] ) ] )
			.filter( ( [ , width ] ) => width >= activeWidth )
			.sort( ( first, second ) => Number( second[ 1 ] ) - Number( first[ 1 ] ) )
			.forEach( ( [ override ] ) => {
				properties = { ...properties, ...parseCssProperties( override.css ) };
			} );
	}
	return {
		fontFamily: properties[ 'font-family' ] || 'inherit',
		fontSize: properties[ 'font-size' ] || '1rem',
		lineHeight: properties[ 'line-height' ] || '1.5',
		letterSpacing: properties[ 'letter-spacing' ] || 'normal',
		fontWeight: properties[ 'font-weight' ] || 'normal',
	};
}
