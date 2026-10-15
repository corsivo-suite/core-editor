import { configReducer } from './store';
import {
	createConfigSavePayload,
	createTemplateRenames,
	initializeStyleTemplate,
} from './configAdapter';
import { initializeInstalledFont, renameInstalledFontWeight } from './installedFontAdapter';

const createState = ( properties = {} ) => ( {
	fonts: [],
	colors: [],
	breakpoints: {},
	customFonts: [],
	installFonts: [],
	functionalPages: [],
	pageAssignments: {},
	globalStyles: {},
	styleTemplates: [],
	contextStyles: [],
	coreComponents: { elements: {}, blockStyles: [] },
	spacing: {},
	serverRevision: 'initial-revision',
	revision: 0,
	isDirty: false,
	...properties,
} );

const renameTemplate = ( state, index, slug ) => {
	const previousItem = state.styleTemplates[ index ];
	const item = { ...previousItem, slug };
	const nextTemplates = [ ...state.styleTemplates ];
	nextTemplates[ index ] = item;
	return configReducer( state, {
		type: 'SET_STYLE_TEMPLATES',
		payload: nextTemplates,
		change: { type: 'update', index, previousItem, item },
	} );
};

const renamePreset = ( state, collection, index, slug ) => {
	const previousItem = state[ collection ][ index ];
	const item = { ...previousItem, slug };
	const items = [ ...state[ collection ] ];
	items[ index ] = item;
	return configReducer( state, {
		type: collection === 'fonts' ? 'SET_FONTS' : 'SET_COLORS',
		payload: items,
		change: { type: 'update', index, previousItem, item },
	} );
};

describe( 'font and color preset references', () => {
	it( 'renames structured typography references without changing CSS or other inheritance', () => {
		const untouchedTemplate = { slug: 'untouched', body: { font: 'secondary' } };
		const state = renamePreset( createState( {
			fonts: [ { slug: 'primary' }, { slug: 'secondary' } ],
			globalStyles: {
				body: { font: 'primary', color: 'primary' },
				heading: { font: 'primary' },
				titleStyle: 'primary',
				defaultParagraphStyle: 'primary',
				defaultHeadingStyle: 'primary',
			},
			styleTemplates: [ { slug: 'night', body: { font: 'primary' }, titleStyle: 'primary' }, untouchedTemplate ],
			coreComponents: {
				elements: { button: { inherits: 'font:primary' } },
				blockStyles: [ { inherits: 'font:primary', css: 'font-family: var(--wp--preset--font-family--primary);' }, { inherits: 'bs:core/button::primary' } ],
			},
		} ), 'fonts', 0, 'brand' );
		expect( state.globalStyles ).toEqual( {
			body: { font: 'brand', color: 'primary' },
			heading: { font: 'brand' },
			titleStyle: 'brand',
			defaultParagraphStyle: 'brand',
			defaultHeadingStyle: 'brand',
		} );
		expect( state.styleTemplates[ 0 ] ).toEqual( { slug: 'night', body: { font: 'brand' }, titleStyle: 'brand' } );
		expect( state.styleTemplates[ 1 ] ).toBe( untouchedTemplate );
		expect( state.coreComponents.elements.button.inherits ).toBe( 'font:brand' );
		expect( state.coreComponents.blockStyles ).toEqual( [
			{ inherits: 'font:brand', css: 'font-family: var(--wp--preset--font-family--primary);' },
			{ inherits: 'bs:core/button::primary' },
		] );
	} );

	it( 'renames every palette field in global styles and templates', () => {
		const fields = {
			body: { background: 'primary', color: 'primary', font: 'primary' },
			heading: { color: 'primary' },
			link: { color: 'primary', hoverColor: 'primary' },
		};
		const state = renamePreset( createState( {
			colors: [ { slug: 'primary', color: '#123456' } ],
			globalStyles: fields,
			styleTemplates: [ { slug: 'night', ...fields } ],
		} ), 'colors', 0, 'brand' );
		const renamedFields = {
			body: { background: 'brand', color: 'brand', font: 'primary' },
			heading: { color: 'brand' },
			link: { color: 'brand', hoverColor: 'brand' },
		};
		expect( state.globalStyles ).toEqual( renamedFields );
		expect( state.styleTemplates ).toEqual( [ { slug: 'night', ...renamedFields } ] );
	} );

	it.each( [ 'fonts', 'colors' ] )( 'retains %s references through empty drafts and resolves a slug swap', ( collection ) => {
		const field = collection === 'fonts' ? 'font' : 'color';
		let state = createState( {
			[ collection ]: [ { slug: 'primary' }, { slug: 'secondary' } ],
			globalStyles: { body: { [ field ]: 'primary' }, heading: { [ field ]: 'secondary' } },
		} );
		state = renamePreset( state, collection, 0, '' );
		state = renamePreset( state, collection, 0, 'secondary' );
		expect( state.globalStyles.body[ field ] ).toBe( 'primary' );
		state = renamePreset( state, collection, 1, 'primary' );
		expect( state.globalStyles ).toEqual( { body: { [ field ]: 'secondary' }, heading: { [ field ]: 'primary' } } );
		expect( state[ collection ] ).toEqual( [ { slug: 'secondary' }, { slug: 'primary' } ] );
	} );

	it.each( [ 'fonts', 'colors' ] )( 'clears removed %s references and resolves a pending collision', ( collection ) => {
		const field = collection === 'fonts' ? 'font' : 'color';
		let state = createState( {
			[ collection ]: [ { slug: 'primary' }, { slug: 'secondary' } ],
			globalStyles: { body: { [ field ]: 'primary' }, heading: { [ field ]: 'secondary' } },
		} );
		state = renamePreset( state, collection, 0, 'secondary' );
		state = configReducer( state, {
			type: collection === 'fonts' ? 'SET_FONTS' : 'SET_COLORS',
			payload: [ state[ collection ][ 0 ] ],
			change: { type: 'remove', index: 1, item: state[ collection ][ 1 ] },
		} );
		expect( state.globalStyles ).toEqual( { body: { [ field ]: 'secondary' }, heading: { [ field ]: '' } } );
		expect( state[ collection ] ).toEqual( [ { slug: 'secondary' } ] );
	} );

	it( 'clears font inheritance on deletion and omits draft metadata from saves', () => {
		const font = { slug: '', _slugReference: 'primary', _uid: 'font-1' };
		let state = createState( {
			fonts: [ font ],
			colors: [ { slug: 'brand', _slugReference: 'primary' } ],
			globalStyles: { titleStyle: 'primary' },
			coreComponents: { elements: { button: { inherits: 'font:primary' } }, blockStyles: [ { inherits: 'font:primary' } ] },
		} );
		expect( createConfigSavePayload( state ).fonts ).toEqual( [ { slug: '' } ] );
		expect( createConfigSavePayload( state ).colors ).toEqual( [ { slug: 'brand' } ] );
		state = configReducer( state, { type: 'SET_FONTS', payload: [], change: { type: 'remove', index: 0, item: font } } );
		expect( state.globalStyles.titleStyle ).toBe( '' );
		expect( state.coreComponents.elements.button.inherits ).toBe( '' );
		expect( state.coreComponents.blockStyles[ 0 ].inherits ).toBe( '' );
	} );

	it( 'does not infer renames from reordered palette items', () => {
		const colors = [ { slug: 'primary' }, { slug: 'secondary' } ];
		const globalStyles = { body: { color: 'primary' } };
		const state = configReducer( createState( { colors, globalStyles } ), { type: 'SET_COLORS', payload: [ colors[ 1 ], colors[ 0 ] ] } );
		expect( state.globalStyles ).toBe( globalStyles );
	} );
} );

describe( 'configurator save and rename state', () => {
	it( 'preserves persisted template origins across empty and repeated edits', () => {
		let state = createState( {
			styleTemplates: [ initializeStyleTemplate( { slug: 'dark' } ) ],
			contextStyles: [ { context: 'home', template: 'dark' } ],
		} );
		state = renameTemplate( state, 0, '' );
		state = renameTemplate( state, 0, 'night' );
		state = renameTemplate( state, 0, 'midnight' );
		expect( createConfigSavePayload( state ).template_renames ).toEqual( {
			dark: 'midnight',
		} );
		expect( state.contextStyles ).toEqual( [ { context: 'home', template: 'midnight' } ] );
		state = configReducer( state, { type: 'ERROR', payload: 'Save failed' } );
		expect( createTemplateRenames( state.styleTemplates ) ).toEqual( { dark: 'midnight' } );
		state = renameTemplate( state, 0, 'dark' );
		expect( createTemplateRenames( state.styleTemplates ) ).toEqual( {} );
	} );

	it( 'supports swapped template slugs and drops renames for deleted templates', () => {
		let state = createState( {
			styleTemplates: [
				initializeStyleTemplate( { slug: 'light' } ),
				initializeStyleTemplate( { slug: 'dark' } ),
			],
			contextStyles: [ { context: 'home', template: 'light' }, { context: 'single', template: 'dark' } ],
		} );
		state = renameTemplate( state, 0, 'draft-light' );
		state = renameTemplate( state, 1, 'light' );
		state = renameTemplate( state, 0, 'dark' );
		expect( createTemplateRenames( state.styleTemplates ) ).toEqual( { light: 'dark', dark: 'light' } );
		expect( state.contextStyles.map( ( assignment ) => assignment.template ) ).toEqual( [ 'dark', 'light' ] );
		state = configReducer( state, {
			type: 'SET_STYLE_TEMPLATES',
			payload: [ state.styleTemplates[ 1 ] ],
			change: { type: 'remove', item: state.styleTemplates[ 0 ], index: 0 },
		} );
		expect( createTemplateRenames( state.styleTemplates ) ).toEqual( { dark: 'light' } );
	} );

	it( 'rebases pending template edits only after a successful save', () => {
		let state = createState( {
			styleTemplates: [ initializeStyleTemplate( { slug: 'dark' } ) ],
		} );
		state = renameTemplate( state, 0, 'night' );
		const savedTemplates = state.styleTemplates;
		const savedRevision = state.revision;
		state = renameTemplate( state, 0, 'midnight' );
		const newTemplate = initializeStyleTemplate( { slug: 'new' }, false );
		state = configReducer( state, { type: 'SET_STYLE_TEMPLATES', payload: [ ...state.styleTemplates, newTemplate ] } );
		state = configReducer( state, {
			type: 'SAVED',
			revision: savedRevision,
			styleTemplates: savedTemplates,
			serverRevision: 'next-revision',
		} );
		expect( state.isDirty ).toBe( true );
		expect( state.serverRevision ).toBe( 'next-revision' );
		expect( createTemplateRenames( state.styleTemplates ) ).toEqual( { night: 'midnight' } );
		expect( state.styleTemplates[ 1 ] ).toBe( newTemplate );
		state = configReducer( state, { type: 'SAVED', revision: state.revision, styleTemplates: state.styleTemplates } );
		expect( state.isDirty ).toBe( false );
		expect( createTemplateRenames( state.styleTemplates ) ).toEqual( {} );
	} );

	it( 'tracks edits to a newly created template while its first save is pending', () => {
		let state = createState( {
			styleTemplates: [ initializeStyleTemplate( { slug: 'first' }, false ) ],
			isDirty: true,
		} );
		const savedTemplates = state.styleTemplates;
		state = renameTemplate( state, 0, 'second' );
		expect( createTemplateRenames( state.styleTemplates ) ).toEqual( {} );
		state = configReducer( state, { type: 'SAVED', revision: 0, styleTemplates: savedTemplates } );
		expect( createTemplateRenames( state.styleTemplates ) ).toEqual( { first: 'second' } );
	} );

	it( 'retains functional-page assignments through empty and duplicate slugs', () => {
		let state = createState( {
			functionalPages: [ { slug: 'checkout' }, { slug: 'account' } ],
			pageAssignments: { checkout: { it: 42, en: 43 }, account: { it: 7 } },
		} );
		for ( const slug of [ '', 'account', 'booking' ] ) {
			const previousItem = state.functionalPages[ 0 ];
			const item = { ...previousItem, slug };
			state = configReducer( state, {
				type: 'SET_FUNCTIONAL_PAGES',
				payload: [ item, state.functionalPages[ 1 ] ],
				change: { type: 'update', index: 0, previousItem, item },
			} );
		}
		expect( state.pageAssignments ).toEqual( { booking: { it: 42, en: 43 }, account: { it: 7 } } );
		expect( state.functionalPages[ 0 ] ).toEqual( { slug: 'booking' } );
	} );

	it( 'removes the original functional-page assignment after a duplicate draft', () => {
		const checkout = { slug: 'account', _slugReference: 'checkout' };
		const account = { slug: 'account' };
		const state = createState( {
			functionalPages: [ checkout, account ],
			pageAssignments: { checkout: { it: 42 }, account: { it: 7 } },
		} );
		const nextState = configReducer( state, {
			type: 'SET_FUNCTIONAL_PAGES',
			payload: [ account ],
			change: { type: 'remove', item: checkout, index: 0 },
		} );
		expect( nextState.pageAssignments ).toEqual( { account: { it: 7 } } );
	} );

	it( 'resolves a pending functional-page rename when the conflicting page is removed', () => {
		let state = createState( {
			functionalPages: [ { slug: 'checkout' }, { slug: 'account' } ],
			pageAssignments: { checkout: { it: 42 }, account: { it: 7 } },
		} );
		const previousItem = state.functionalPages[ 0 ];
		const item = { ...previousItem, slug: 'account' };
		state = configReducer( state, {
			type: 'SET_FUNCTIONAL_PAGES',
			payload: [ item, state.functionalPages[ 1 ] ],
			change: { type: 'update', index: 0, previousItem, item },
		} );
		const removedPage = state.functionalPages[ 1 ];
		state = configReducer( state, {
			type: 'SET_FUNCTIONAL_PAGES',
			payload: [ state.functionalPages[ 0 ] ],
			change: { type: 'remove', index: 1, item: removedPage },
		} );
		expect( state.functionalPages ).toEqual( [ { slug: 'account' } ] );
		expect( state.pageAssignments ).toEqual( { account: { it: 42 } } );
	} );

	it( 'resolves a pending template rename when the conflicting template is removed', () => {
		let state = createState( {
			styleTemplates: [ initializeStyleTemplate( { slug: 'dark' } ), initializeStyleTemplate( { slug: 'light' } ) ],
			contextStyles: [ { context: 'home', template: 'dark' }, { context: 'single', template: 'light' } ],
		} );
		state = renameTemplate( state, 0, 'light' );
		const removedTemplate = state.styleTemplates[ 1 ];
		state = configReducer( state, {
			type: 'SET_STYLE_TEMPLATES',
			payload: [ state.styleTemplates[ 0 ] ],
			change: { type: 'remove', index: 1, item: removedTemplate },
		} );
		expect( state.styleTemplates[ 0 ]._slugReference ).toBeUndefined();
		expect( state.contextStyles ).toEqual( [ { context: 'home', template: 'light' }, { context: 'single', template: '' } ] );
		expect( createTemplateRenames( state.styleTemplates ) ).toEqual( { dark: 'light' } );
	} );

	it( 'applies both template migrations once when an edit resolves a slug collision', () => {
		let state = createState( {
			styleTemplates: [ initializeStyleTemplate( { slug: 'dark' } ), initializeStyleTemplate( { slug: 'light' } ) ],
			contextStyles: [ { context: 'home', template: 'dark' }, { context: 'single', template: 'light' } ],
		} );
		state = renameTemplate( state, 0, 'light' );
		state = renameTemplate( state, 1, 'dark' );
		expect( state.contextStyles.map( ( assignment ) => assignment.template ) ).toEqual( [ 'light', 'dark' ] );
		expect( state.styleTemplates.every( ( template ) => template._slugReference === undefined ) ).toBe( true );
		expect( createTemplateRenames( state.styleTemplates ) ).toEqual( { dark: 'light', light: 'dark' } );
	} );

	it( 'applies both functional-page migrations once when an edit resolves a slug collision', () => {
		let state = createState( {
			functionalPages: [ { slug: 'checkout' }, { slug: 'account' } ],
			pageAssignments: { checkout: { it: 42 }, account: { it: 7 } },
		} );
		for ( const [ index, slug ] of [ [ 0, 'account' ], [ 1, 'checkout' ] ] ) {
			const previousItem = state.functionalPages[ index ];
			const item = { ...previousItem, slug };
			const nextPages = [ ...state.functionalPages ];
			nextPages[ index ] = item;
			state = configReducer( state, {
				type: 'SET_FUNCTIONAL_PAGES',
				payload: nextPages,
				change: { type: 'update', index, previousItem, item },
			} );
		}
		expect( state.functionalPages ).toEqual( [ { slug: 'account' }, { slug: 'checkout' } ] );
		expect( state.pageAssignments ).toEqual( { account: { it: 42 }, checkout: { it: 7 } } );
	} );
} );

describe( 'asynchronous font uploads', () => {
	it( 'patches the current family and renamed weight after an upload completes', async () => {
		const font = initializeInstalledFont( { 'font-family': 'Original', 'font-weight': { 400: '', 700: '' } } );
		const weightIdentity = font._weightIdentities[ '400' ];
		let completeUpload;
		const upload = new Promise( ( resolve ) => { completeUpload = resolve; } );
		let state = createState( { installFonts: [ font ] } );
		const pendingUpdate = upload.then( ( url ) => {
			state = configReducer( state, { type: 'SET_INSTALLED_FONT_WEIGHT_URL', fontIdentity: font._uid, weightIdentity, url } );
		} );
		const editedFont = renameInstalledFontWeight( { ...font, 'font-family': 'Renamed', 'font-display': 'swap' }, '400', '500' );
		state = configReducer( state, { type: 'SET_INSTALL_FONTS', payload: [ editedFont ] } );
		completeUpload( '/fonts/uploaded.woff2' );
		await pendingUpdate;
		expect( state.installFonts[ 0 ] ).toMatchObject( {
			'font-family': 'Renamed',
			'font-display': 'swap',
			'font-weight': { 500: '/fonts/uploaded.woff2', 700: '' },
		} );
	} );

	it( 'does not resurrect a deleted family or overwrite a replacement family', () => {
		const removedFont = initializeInstalledFont( { 'font-weight': { 400: '' } } );
		const replacement = initializeInstalledFont( { 'font-family': 'Replacement', 'font-weight': { 400: 'existing.woff2' } } );
		const state = createState( { installFonts: [ replacement ] } );
		expect( configReducer( state, { type: 'SET_INSTALLED_FONT_WEIGHT_URL', fontIdentity: removedFont._uid, weightIdentity: removedFont._weightIdentities[ 400 ], url: 'uploaded.woff2' } ) ).toBe( state );
	} );

	it( 'ignores uploads for removed weights even when the same weight is re-added', () => {
		const font = initializeInstalledFont( { 'font-weight': { 400: '', 700: '' } } );
		const withoutWeight = initializeInstalledFont( { ...font, 'font-weight': { 700: '' } } );
		const replacement = initializeInstalledFont( { ...withoutWeight, 'font-weight': { 400: 'replacement.woff2', 700: '' } } );
		const state = createState( { installFonts: [ replacement ] } );
		expect( configReducer( state, { type: 'SET_INSTALLED_FONT_WEIGHT_URL', fontIdentity: font._uid, weightIdentity: font._weightIdentities[ 400 ], url: 'uploaded.woff2' } ) ).toBe( state );
	} );
} );
