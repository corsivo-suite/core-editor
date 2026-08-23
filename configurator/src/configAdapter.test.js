import {
	createConfigSavePayload,
	normalizePageAssignments,
	normalizeRecordCollection,
} from './configAdapter';

describe( 'theme configurator persistence adapter', () => {
	it( 'normalizes legacy records and saves the existing JSON shape', () => {
		const colors = normalizeRecordCollection(
			{
				primary: { name: 'Primary', color: '#112233' },
			},
			'slug'
		);
		const spacingSizes = normalizeRecordCollection(
			{
				10: { name: 'Small', size: '0.5rem' },
			},
			'slug'
		);
		const pageAssignments = normalizePageAssignments(
			{
				account: 11,
				checkout: { it: 22 },
			},
			'it'
		);
		const state = {
			serverRevision: 'revision-1',
			fonts: [],
			colors,
			breakpoints: {},
			customFonts: [],
			installFonts: [],
			functionalPages: [
				{ slug: 'account', label: 'Account', fromFilter: true },
				{
					slug: 'checkout',
					label: 'Checkout',
					fromFilter: false,
					_autoSlug: true,
				},
			],
			pageAssignments,
			globalStyles: {},
			styleTemplates: [],
			contextStyles: [],
			coreComponents: { elements: {}, blockStyles: [] },
			spacing: { spacingSizes },
		};
		const payload = createConfigSavePayload( state );

		expect( state.serverRevision ).toBe( 'revision-1' );
		expect( state.colors ).toEqual( [
			{ slug: 'primary', name: 'Primary', color: '#112233' },
		] );
		expect( state.spacing.spacingSizes ).toEqual( [
			{ slug: '10', name: 'Small', size: '0.5rem' },
		] );
		expect( state.pageAssignments.account ).toEqual( { it: 11 } );
		expect( payload.functional_pages ).toEqual( [
			{ slug: 'checkout', label: 'Checkout' },
		] );
		expect( payload.page_assignments ).toBe( state.pageAssignments );
		expect( payload.colors ).toBe( state.colors );
		expect( payload.spacing ).toBe( state.spacing );
	} );

	it( 'preserves collection order and strips only transient UI fields', () => {
		const first = { slug: 'first', name: 'First', _uid: 'first-ui' };
		const second = { slug: 'second', name: 'Second', _uid: 'second-ui' };
		const colors = [
			{ slug: 'secondary', color: '#ffffff', name: 'Secondary' },
			{ slug: 'primary', color: '#000000', name: 'Primary' },
		];
		const state = {
			serverRevision: 'revision-2',
			fonts: [ second, first ],
			colors,
			breakpoints: { desktop: 1440, tablet: 960 },
			customFonts: [],
			installFonts: [],
			functionalPages: [],
			pageAssignments: {},
			globalStyles: {},
			styleTemplates: [
				{
					slug: 'night',
					name: 'Night',
					_slugManual: true,
					_slugReference: 'dark',
				},
				{ slug: 'light', name: 'Light' },
			],
			contextStyles: [
				{ context: 'single-post', template: 'night' },
				{ context: 'home', template: 'light' },
			],
			coreComponents: { elements: {}, blockStyles: [ first, second ] },
			spacing: {},
		};
		const payload = createConfigSavePayload( state );
		const reloadedTemplates = normalizeRecordCollection(
			payload.style_templates,
			'slug'
		);
		const reloadedContextStyles = normalizeRecordCollection(
			payload.context_styles
		);

		expect( payload.colors ).toBe( colors );
		expect( payload.colors.map( ( color ) => color.slug ) ).toEqual( [
			'secondary',
			'primary',
		] );
		expect( payload.fonts ).toEqual( [
			{ slug: 'second', name: 'Second' },
			{ slug: 'first', name: 'First' },
		] );
		expect( payload.core_components.blockStyles ).toEqual( [
			{ slug: 'first', name: 'First' },
			{ slug: 'second', name: 'Second' },
		] );
		expect( payload.style_templates ).toEqual( [
			{ slug: 'night', name: 'Night' },
			{ slug: 'light', name: 'Light' },
		] );
		expect( payload.context_styles ).toBe( state.contextStyles );
		expect( payload.breakpoints ).toBe( state.breakpoints );
		expect(
			payload.context_styles.map( ( item ) => item.context )
		).toEqual( [ 'single-post', 'home' ] );
		expect( reloadedTemplates ).toEqual( payload.style_templates );
		expect( reloadedContextStyles ).toEqual( payload.context_styles );
	} );
} );
