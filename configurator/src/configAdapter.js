const isRecord = ( value ) =>
	value !== null && typeof value === 'object' && ! Array.isArray( value );

const normalizeRecord = ( value ) => ( isRecord( value ) ? value : {} );

export function normalizeRecordCollection( value, fallbackProperty = '' ) {
	let entries = [];
	if ( Array.isArray( value ) ) {
		entries = value.map( ( item ) => [ '', item ] );
	} else if ( isRecord( value ) ) {
		entries = Object.entries( value );
	}

	return entries.reduce( ( collection, [ fallbackValue, item ] ) => {
		if ( ! isRecord( item ) ) {
			return collection;
		}
		if ( fallbackProperty && fallbackValue && ! item[ fallbackProperty ] ) {
			collection.push( { ...item, [ fallbackProperty ]: fallbackValue } );
		} else {
			collection.push( item );
		}
		return collection;
	}, [] );
}

export function normalizePageAssignments( value, currentLanguage ) {
	return Object.entries( normalizeRecord( value ) ).reduce(
		( assignments, [ slug, languages ] ) => {
			if ( isRecord( languages ) ) {
				assignments[ slug ] = languages;
			} else if (
				typeof languages === 'string' ||
				typeof languages === 'number'
			) {
				assignments[ slug ] = { [ currentLanguage ]: languages };
			}
			return assignments;
		},
		{}
	);
}

const stripUid = ( { _uid, ...item } ) => item;

const stripFunctionalPageState = ( { _autoSlug, fromFilter, ...page } ) => page;

const stripStyleTemplateState = ( {
	_slugManual,
	_slugReference,
	...template
} ) => template;

export function createConfigSavePayload( state ) {
	return {
		revision: state.serverRevision,
		fonts: state.fonts.map( stripUid ),
		colors: state.colors,
		breakpoints: state.breakpoints,
		custom_fonts: state.customFonts,
		install_fonts: state.installFonts,
		functional_pages: state.functionalPages
			.filter( ( page ) => ! page.fromFilter )
			.map( stripFunctionalPageState ),
		page_assignments: state.pageAssignments,
		global_styles: state.globalStyles,
		style_templates: state.styleTemplates.map( stripStyleTemplateState ),
		context_styles: state.contextStyles,
		core_components: {
			elements: state.coreComponents.elements,
			blockStyles: state.coreComponents.blockStyles.map( stripUid ),
		},
		spacing: state.spacing,
	};
}
