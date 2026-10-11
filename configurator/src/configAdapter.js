import { createCollectionIdentity } from './collectionIdentity';

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

const stripFunctionalPageState = ( { _autoSlug, _slugReference, fromFilter, ...page } ) => page;

const stripInstalledFontState = ( { _uid, _weightIdentities, ...font } ) => font;

const stripStyleTemplateState = ( {
	_slugManual,
	_slugReference,
	_savedSlug,
	_uid,
	...template
} ) => template;

export function createTemplateRenames( templates ) {
	return templates.reduce( ( renames, template ) => {
		if (
			template._savedSlug &&
			template.slug &&
			template.slug !== template._savedSlug &&
			templates.filter( ( candidate ) => candidate.slug === template.slug ).length === 1
		) {
			renames[ template._savedSlug ] = template.slug;
		}
		return renames;
	}, {} );
}

export function initializeStyleTemplate( template, saved = true ) {
	return {
		...template,
		_uid: template._uid || createCollectionIdentity(),
		_savedSlug: saved ? template.slug || '' : '',
		_slugManual: saved && Boolean( template.slug ),
	};
}

export function acknowledgeSavedTemplates( templates, savedTemplates ) {
	return templates.map( ( template ) => {
		const savedTemplate = savedTemplates.find(
			( saved ) => saved._uid && saved._uid === template._uid
		);
		return savedTemplate
			? { ...template, _savedSlug: savedTemplate.slug, _slugManual: true }
			: template;
	} );
}

export function synchronizeSettingsRevision( revision, documentRoot = document ) {
	if ( ! revision ) {
		return;
	}
	documentRoot.querySelectorAll( 'input[name="settings_revision"]' ).forEach(
		( input ) => {
			input.value = String( revision );
		}
	);
}

export function createConfigSavePayload( state ) {
	return {
		revision: state.serverRevision,
		fonts: state.fonts.map( stripUid ),
		colors: state.colors,
		breakpoints: state.breakpoints,
		custom_fonts: state.customFonts,
		install_fonts: state.installFonts.map( stripInstalledFontState ),
		functional_pages: state.functionalPages
			.filter( ( page ) => ! page.fromFilter )
			.map( stripFunctionalPageState ),
		page_assignments: state.pageAssignments,
		global_styles: state.globalStyles,
		style_templates: state.styleTemplates.map( stripStyleTemplateState ),
		template_renames: createTemplateRenames( state.styleTemplates ),
		context_styles: state.contextStyles,
		core_components: {
			elements: state.coreComponents.elements,
			blockStyles: state.coreComponents.blockStyles.map( stripUid ),
		},
		spacing: state.spacing,
	};
}
