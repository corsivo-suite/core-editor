import { getFontPreviewStyles } from './fontPreviewStyles';

const font = {
	css: 'font-family: Georgia; font-size: 5rem; font-weight: 700; line-height: 1.2;',
	breakpoints: {
		mobile: { css: 'font-size: 2rem;' },
		tablet: { css: 'font-size: 3rem; line-height: 1.4; letter-spacing: 0.1em;' },
		unknown: { css: 'font-weight: 100;' },
	},
};

describe( 'responsive typography preview', () => {
	it( 'composes larger max-width overrides before the selected breakpoint', () => {
		expect( getFontPreviewStyles( font, { mobile: 640, tablet: 960 }, 'mobile' ) ).toEqual( {
			fontFamily: 'Georgia',
			fontSize: '2rem',
			fontWeight: '700',
			lineHeight: '1.4',
			letterSpacing: '0.1em',
		} );
	} );

	it( 'inherits a larger breakpoint at widths without their own override', () => {
		const styles = getFontPreviewStyles( font, { tablet: 960, mobile: 640, small: 480 }, 'small' );
		expect( styles.fontSize ).toBe( '2rem' );
		expect( styles.lineHeight ).toBe( '1.4' );
	} );

	it( 'excludes smaller breakpoints and unconfigured overrides', () => {
		const styles = getFontPreviewStyles( font, { mobile: 640, tablet: 960 }, 'tablet' );
		expect( styles.fontSize ).toBe( '3rem' );
		expect( styles.fontWeight ).toBe( '700' );
	} );

	it( 'uses desktop styles after a breakpoint is removed and responds to changed widths', () => {
		const breakpoints = { mobile: 640, tablet: 960 };
		expect( getFontPreviewStyles( font, breakpoints, 'desktop' ).fontSize ).toBe( '5rem' );
		expect( getFontPreviewStyles( font, {}, 'mobile' ).fontSize ).toBe( '5rem' );
		expect( getFontPreviewStyles( font, { ...breakpoints, tablet: 480 }, 'mobile' ).lineHeight ).toBe( '1.2' );
	} );

	it( 'keeps declaration order for equal thresholds even when configuration order differs', () => {
		const equalWidthFont = {
			css: 'font-size: 5rem;',
			breakpoints: {
				compact: { css: 'font-size: 3rem;' },
				mobile: { css: 'font-size: 2rem;' },
			},
		};
		expect( getFontPreviewStyles( equalWidthFont, { mobile: 640, compact: 640 }, 'compact' ).fontSize ).toBe( '2rem' );
	} );
} );
