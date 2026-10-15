export function sanitizeSlug( value, trim = true ) {
	let slug = String( value || '' )
		.toLowerCase()
		.replace( /[^a-z0-9-]/g, '-' )
		.replace( /-+/g, '-' );

	if ( trim ) {
		slug = slug.replace( /^-|-$/g, '' );
	}

	return slug;
}

export function replaceCollectionItem( items, index, nextItem ) {
	return items.map( ( item, itemIndex ) =>
		itemIndex === index ? nextItem : item
	);
}

export function createPaletteColor( colors ) {
	let suffix = 1;
	while ( colors.some( ( color ) => color.slug === `color-${ suffix }` ) ) {
		suffix += 1;
	}

	return {
		slug: `color-${ suffix }`,
		color: '#cccccc',
		name: `Color ${ suffix }`,
	};
}

export function createSpacingPreset( spacingSizes ) {
	let step = 10;
	while (
		spacingSizes.some( ( preset ) => preset.slug === String( step ) )
	) {
		step += 10;
	}

	const slug = String( step );
	return { slug, name: `Spacing ${ slug }`, size: '1rem' };
}

const hasUniqueSlug = ( items, slug ) =>
	Boolean( slug ) && items.filter( ( item ) => item.slug === slug ).length === 1;

function collectSlugReferenceChanges( items, change ) {
	const referenceChanges = new Map();
	const referenceOrigins = new Map();
	if ( change.type === 'remove' ) {
		const source = change.item._slugReference || change.item.slug;
		if ( source ) {
			referenceChanges.set( source, '' );
		}
	} else if ( change.type === 'update' ) {
		const source = change.previousItem._slugReference || change.previousItem.slug;
		if ( source && source !== change.item.slug && hasUniqueSlug( items, change.item.slug ) ) {
			referenceChanges.set( source, change.item.slug );
		}
	}

	items.forEach( ( item, index ) => {
		const isUpdatedItem = change.type === 'update' &&
			( change.index === index || item === change.item );
		const source = isUpdatedItem
			? change.previousItem._slugReference || change.previousItem.slug
			: item._slugReference;
		if ( ! source ) {
			return;
		}
		referenceOrigins.set( index, source );
		if ( source !== item.slug && hasUniqueSlug( items, item.slug ) ) {
			referenceChanges.set( source, item.slug );
		}
	} );
	return { referenceChanges, referenceOrigins };
}

function stabilizeSlugReferences( items, referenceOrigins, referenceChanges ) {
	let changed = false;
	const nextItems = items.map( ( item, index ) => {
		const source = referenceOrigins.get( index );
		if ( ! source ) {
			return item;
		}
		const resolved = hasUniqueSlug( items, item.slug ) &&
			( source === item.slug || referenceChanges.get( source ) === item.slug );
		if ( resolved ) {
			if ( item._slugReference === undefined ) {
				return item;
			}
			changed = true;
			const { _slugReference, ...nextItem } = item;
			return nextItem;
		}
		if ( item._slugReference === source ) {
			return item;
		}
		changed = true;
		return { ...item, _slugReference: source };
	} );
	return changed ? nextItems : items;
}

function collectPageSlugReferenceChanges( assignments, nextPages, change ) {
	const changes = collectSlugReferenceChanges( nextPages, change );
	let blocked = true;
	while ( blocked ) {
		blocked = false;
		changes.referenceChanges.forEach( ( target, source ) => {
			if (
				target &&
				assignments[ source ] !== undefined &&
				assignments[ target ] !== undefined &&
				! changes.referenceChanges.has( target )
			) {
				changes.referenceChanges.delete( source );
				blocked = true;
			}
		} );
	}
	return changes;
}

export function reconcilePageAssignments( assignments, change, nextPages = [] ) {
	const pages = nextPages.length || change.type !== 'update' ? nextPages : [ change.item ];
	const { referenceChanges } = collectPageSlugReferenceChanges( assignments, pages, change );
	if ( ! Array.from( referenceChanges.keys() ).some( ( source ) => assignments[ source ] !== undefined ) ) {
		return assignments;
	}
	return Object.entries( assignments ).reduce( ( nextAssignments, [ slug, languages ] ) => {
		const nextSlug = referenceChanges.has( slug ) ? referenceChanges.get( slug ) : slug;
		if ( nextSlug ) {
			nextAssignments[ nextSlug ] = languages;
		}
		return nextAssignments;
	}, {} );
}

export function retainPageSlugReference( nextPages, change, assignments ) {
	const { referenceChanges, referenceOrigins } = collectPageSlugReferenceChanges( assignments, nextPages, change );
	return stabilizeSlugReferences( nextPages, referenceOrigins, referenceChanges );
}

export function reconcileBreakpointItems( breakpoints, previousItems = [] ) {
	const previousByName = new Map(
		previousItems.map( ( item ) => [ item.name, item ] )
	);

	return Object.entries( breakpoints || {} )
		.map( ( [ name, value ] ) => {
			const previousItem = previousByName.get( name );
			if ( previousItem && previousItem.value === value ) {
				return previousItem;
			}
			return { name, value };
		} )
		.sort( ( first, second ) => second.value - first.value );
}

export function breakpointItemsToRecord( items ) {
	return items.reduce( ( breakpoints, item ) => {
		breakpoints[ item.name ] = item.value;
		return breakpoints;
	}, {} );
}

export function createBreakpointItem( items ) {
	let suffix = 1;
	while ( items.some( ( item ) => item.name === `bp-${ suffix }` ) ) {
		suffix += 1;
	}
	return { name: `bp-${ suffix }`, value: 768 };
}

export function renameBreakpointReferences( fonts, oldName, newName ) {
	return fonts.map( ( font ) => {
		if ( ! font.breakpoints || font.breakpoints[ oldName ] === undefined ) {
			return font;
		}

		const nextBreakpoints = {
			...font.breakpoints,
			[ newName ]: font.breakpoints[ oldName ],
		};
		delete nextBreakpoints[ oldName ];
		return { ...font, breakpoints: nextBreakpoints };
	} );
}

export function reconcileTemplateReferences(
	contextStyles,
	change,
	nextTemplates
) {
	const { referenceChanges } = collectSlugReferenceChanges( nextTemplates, change );
	let changed = false;
	const nextContextStyles = contextStyles.map( ( assignment ) => {
		if ( ! referenceChanges.has( assignment.template ) ) {
			return assignment;
		}
		changed = true;
		return { ...assignment, template: referenceChanges.get( assignment.template ) };
	} );

	return changed ? nextContextStyles : contextStyles;
}

export function retainTemplateSlugReference( nextTemplates, change ) {
	const { referenceChanges, referenceOrigins } = collectSlugReferenceChanges( nextTemplates, change );
	return stabilizeSlugReferences( nextTemplates, referenceOrigins, referenceChanges );
}

function reconcileStyleFields( style, paths, referenceChanges ) {
	return paths.reduce( ( nextStyle, path ) => {
		const [ section, field ] = path;
		const value = field ? nextStyle[ section ]?.[ field ] : nextStyle[ section ];
		if ( ! referenceChanges.has( value ) ) {
			return nextStyle;
		}
		const replacement = referenceChanges.get( value );
		return {
			...nextStyle,
			[ section ]: field
				? { ...nextStyle[ section ], [ field ]: replacement }
				: replacement,
		};
	}, style );
}

function reconcileInheritedFont( item, referenceChanges ) {
	if ( ! item.inherits?.startsWith( 'font:' ) ) {
		return item;
	}
	const slug = item.inherits.slice( 5 );
	if ( ! referenceChanges.has( slug ) ) {
		return item;
	}
	const replacement = referenceChanges.get( slug );
	return { ...item, inherits: replacement ? `font:${ replacement }` : '' };
}

export function reconcileStylePresetChange( state, collection, items, change ) {
	const { referenceChanges, referenceOrigins } = collectSlugReferenceChanges( items, change );
	const presets = stabilizeSlugReferences( items, referenceOrigins, referenceChanges );
	if ( ! referenceChanges.size ) {
		return { [ collection ]: presets };
	}
	const paths = collection === 'fonts'
		? [ [ 'body', 'font' ], [ 'heading', 'font' ], [ 'titleStyle' ], [ 'defaultParagraphStyle' ], [ 'defaultHeadingStyle' ] ]
		: [ [ 'body', 'background' ], [ 'body', 'color' ], [ 'heading', 'color' ], [ 'link', 'color' ], [ 'link', 'hoverColor' ] ];
	const globalStyles = reconcileStyleFields( state.globalStyles, paths, referenceChanges );
	const styleTemplates = state.styleTemplates.map( ( template ) => reconcileStyleFields( template, paths, referenceChanges ) );
	const updates = { [ collection ]: presets, globalStyles, styleTemplates };
	if ( collection === 'fonts' ) {
		const components = state.coreComponents;
		updates.coreComponents = {
			...components,
			elements: Object.fromEntries( Object.entries( components.elements || {} ).map( ( [ name, item ] ) => [ name, reconcileInheritedFont( item, referenceChanges ) ] ) ),
			blockStyles: ( components.blockStyles || [] ).map( ( item ) => reconcileInheritedFont( item, referenceChanges ) ),
		};
	}
	return updates;
}

export function setDefaultStyleTemplate( templates, index, enabled ) {
	return templates.map( ( template, templateIndex ) => {
		let isDefault = Boolean( template.isDefault );
		if ( enabled ) {
			isDefault = templateIndex === index;
		} else if ( templateIndex === index ) {
			isDefault = false;
		}
		if ( Boolean( template.isDefault ) === isDefault ) {
			return template;
		}
		return { ...template, isDefault };
	} );
}
