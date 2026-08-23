import {
	breakpointItemsToRecord,
	createBreakpointItem,
	createPaletteColor,
	createSpacingPreset,
	reconcileBreakpointItems,
	reconcilePageAssignments,
	reconcileTemplateReferences,
	retainTemplateSlugReference,
	renameBreakpointReferences,
	replaceCollectionItem,
	sanitizeSlug,
	setDefaultStyleTemplate,
} from './collectionAdapters';

describe( 'collection adapters', () => {
	it( 'updates one item without replacing sibling references', () => {
		const first = { slug: 'first' };
		const second = { slug: 'second' };
		const third = { slug: 'third' };
		const replacement = { slug: 'updated' };
		const nextItems = replaceCollectionItem(
			[ first, second, third ],
			1,
			replacement
		);

		expect( nextItems ).toEqual( [ first, replacement, third ] );
		expect( nextItems[ 0 ] ).toBe( first );
		expect( nextItems[ 2 ] ).toBe( third );
	} );

	it( 'keeps reordered items as the original object references', () => {
		const first = { slug: 'first' };
		const second = { slug: 'second' };
		const third = { slug: 'third' };
		const reordered = [ third, first, second ];

		expect( reordered.map( ( item ) => item.slug ) ).toEqual( [
			'third',
			'first',
			'second',
		] );
		expect( reordered[ 0 ] ).toBe( third );
		expect( reordered[ 1 ] ).toBe( first );
		expect( reordered[ 2 ] ).toBe( second );
	} );

	it( 'creates collision-free palette and spacing defaults', () => {
		expect(
			createPaletteColor( [ { slug: 'color-1' }, { slug: 'color-2' } ] )
		).toEqual( {
			slug: 'color-3',
			color: '#cccccc',
			name: 'Color 3',
		} );
		expect(
			createSpacingPreset( [ { slug: '10' }, { slug: '20' } ] )
		).toEqual( { slug: '30', name: 'Spacing 30', size: '1rem' } );
		expect( sanitizeSlug( '  Hero / Primary  ' ) ).toBe( 'hero-primary' );
	} );

	it( 'moves and removes functional-page assignments by slug', () => {
		const assignment = { it: 42 };
		const assignments = { checkout: assignment, protected: { it: 7 } };
		const renamed = reconcilePageAssignments( assignments, {
			type: 'update',
			previousItem: { slug: 'checkout' },
			item: { slug: 'booking-checkout' },
		} );

		expect( renamed ).toEqual( {
			'booking-checkout': assignment,
			protected: { it: 7 },
		} );
		expect( renamed[ 'booking-checkout' ] ).toBe( assignment );

		const removed = reconcilePageAssignments( renamed, {
			type: 'remove',
			item: { slug: 'booking-checkout' },
		} );
		expect( removed ).toEqual( { protected: { it: 7 } } );
	} );

	it( 'preserves breakpoint references and renames font overrides', () => {
		const desktop = { name: 'desktop', value: 1440 };
		const tablet = { name: 'tablet', value: 960 };
		const items = reconcileBreakpointItems(
			{ tablet: 960, desktop: 1440, mobile: 640 },
			[ desktop, tablet ]
		);

		expect( items.map( ( item ) => item.name ) ).toEqual( [
			'desktop',
			'tablet',
			'mobile',
		] );
		expect( items[ 0 ] ).toBe( desktop );
		expect( items[ 1 ] ).toBe( tablet );
		expect( breakpointItemsToRecord( items ) ).toEqual( {
			desktop: 1440,
			tablet: 960,
			mobile: 640,
		} );
		expect( createBreakpointItem( [ { name: 'bp-1' } ] ) ).toEqual( {
			name: 'bp-2',
			value: 768,
		} );

		const untouchedFont = { slug: 'heading', breakpoints: {} };
		const referencedFont = {
			slug: 'body',
			breakpoints: { tablet: { css: 'font-size: 1rem;' } },
		};
		const nextFonts = renameBreakpointReferences(
			[ untouchedFont, referencedFont ],
			'tablet',
			'compact'
		);
		expect( nextFonts[ 0 ] ).toBe( untouchedFont );
		expect( nextFonts[ 1 ] ).toEqual( {
			slug: 'body',
			breakpoints: { compact: { css: 'font-size: 1rem;' } },
		} );
	} );

	it( 'renames and clears template references without cloning siblings', () => {
		const untouchedAssignment = { context: 'home', template: 'light' };
		const renamedAssignment = { context: 'single-post', template: 'dark' };
		const nextTemplates = [ { slug: 'light' }, { slug: 'night' } ];
		const renamed = reconcileTemplateReferences(
			[ untouchedAssignment, renamedAssignment ],
			{
				type: 'update',
				previousItem: { slug: 'dark' },
				item: { slug: 'night' },
			},
			nextTemplates
		);

		expect( renamed[ 0 ] ).toBe( untouchedAssignment );
		expect( renamed[ 1 ] ).toEqual( {
			context: 'single-post',
			template: 'night',
		} );

		const removed = reconcileTemplateReferences(
			renamed,
			{ type: 'remove', item: { slug: 'night' } },
			[ { slug: 'light' } ]
		);
		expect( removed[ 0 ] ).toBe( untouchedAssignment );
		expect( removed[ 1 ] ).toEqual( {
			context: 'single-post',
			template: '',
		} );

		const renamedAfterEmptyDraft = reconcileTemplateReferences(
			[ untouchedAssignment, renamedAssignment ],
			{
				type: 'update',
				previousItem: { slug: '', _slugReference: 'dark' },
				item: { slug: 'midnight' },
			},
			[ { slug: 'light' }, { slug: 'midnight' } ]
		);
		expect( renamedAfterEmptyDraft[ 1 ].template ).toBe( 'midnight' );
	} );

	it( 'updates default templates while retaining unaffected references', () => {
		const first = { slug: 'first', isDefault: true };
		const second = { slug: 'second', isDefault: false };
		const third = { slug: 'third' };
		const nextTemplates = setDefaultStyleTemplate(
			[ first, second, third ],
			1,
			true
		);

		expect( nextTemplates[ 0 ] ).toEqual( {
			slug: 'first',
			isDefault: false,
		} );
		expect( nextTemplates[ 1 ] ).toEqual( {
			slug: 'second',
			isDefault: true,
		} );
		expect( nextTemplates[ 2 ] ).toBe( third );
	} );

	it( 'removes the original context reference after a duplicate slug edit', () => {
		const light = { slug: 'light' };
		const dark = { slug: 'dark' };
		const duplicateChange = {
			type: 'update',
			index: 1,
			previousItem: dark,
			item: { slug: 'light' },
		};
		const duplicateTemplates = retainTemplateSlugReference(
			[ light, duplicateChange.item ],
			duplicateChange
		);

		expect( duplicateTemplates[ 1 ] ).toEqual( {
			slug: 'light',
			_slugReference: 'dark',
		} );

		const contextStyles = [
			{ context: 'home', template: 'light' },
			{ context: 'single-post', template: 'dark' },
		];
		const removed = reconcileTemplateReferences(
			contextStyles,
			{ type: 'remove', item: duplicateTemplates[ 1 ] },
			[ light ]
		);

		expect( removed ).toEqual( [
			contextStyles[ 0 ],
			{ context: 'single-post', template: '' },
		] );
		expect( removed[ 0 ] ).toBe( contextStyles[ 0 ] );
	} );

	it( 'migrates the original context reference after an empty slug draft', () => {
		const dark = { slug: 'dark' };
		const emptyChange = {
			type: 'update',
			index: 0,
			previousItem: dark,
			item: { slug: '' },
		};
		const emptyTemplates = retainTemplateSlugReference(
			[ emptyChange.item ],
			emptyChange
		);
		const renamedChange = {
			type: 'update',
			index: 0,
			previousItem: emptyTemplates[ 0 ],
			item: { ...emptyTemplates[ 0 ], slug: 'night' },
		};
		const renamedTemplates = retainTemplateSlugReference(
			[ renamedChange.item ],
			renamedChange
		);
		const contextStyles = [ { context: 'single-post', template: 'dark' } ];

		expect( renamedTemplates ).toEqual( [ { slug: 'night' } ] );
		expect(
			reconcileTemplateReferences(
				contextStyles,
				renamedChange,
				renamedTemplates
			)
		).toEqual( [ { context: 'single-post', template: 'night' } ] );
	} );
} );
