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

export function reconcilePageAssignments( assignments, change ) {
	if ( change.type === 'remove' ) {
		const nextAssignments = { ...assignments };
		delete nextAssignments[ change.item.slug ];
		return nextAssignments;
	}

	if ( change.type !== 'update' ) {
		return assignments;
	}

	const oldSlug = change.previousItem.slug;
	const newSlug = change.item.slug;
	if (
		oldSlug === newSlug ||
		! newSlug ||
		assignments[ oldSlug ] === undefined ||
		assignments[ newSlug ] !== undefined
	) {
		return assignments;
	}

	const nextAssignments = {
		...assignments,
		[ newSlug ]: assignments[ oldSlug ],
	};
	delete nextAssignments[ oldSlug ];
	return nextAssignments;
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
	let oldSlug = '';
	let newSlug = '';

	if ( change.type === 'remove' ) {
		oldSlug = change.item._slugReference || change.item.slug || '';
	} else if ( change.type === 'update' ) {
		oldSlug =
			change.previousItem._slugReference ||
			change.previousItem.slug ||
			'';
		newSlug = change.item.slug || '';
		const isUnique =
			nextTemplates.filter( ( template ) => template.slug === newSlug )
				.length === 1;
		if ( oldSlug === newSlug || ! newSlug || ! isUnique ) {
			return contextStyles;
		}
	} else {
		return contextStyles;
	}

	if ( ! oldSlug ) {
		return contextStyles;
	}

	let changed = false;
	const nextContextStyles = contextStyles.map( ( assignment ) => {
		if ( assignment.template !== oldSlug ) {
			return assignment;
		}
		changed = true;
		return { ...assignment, template: newSlug };
	} );

	return changed ? nextContextStyles : contextStyles;
}

export function retainTemplateSlugReference( nextTemplates, change ) {
	if ( change.type !== 'update' ) {
		return nextTemplates;
	}

	const referenceSlug =
		change.previousItem._slugReference || change.previousItem.slug || '';
	const nextSlug = change.item.slug || '';
	const isUnique =
		Boolean( nextSlug ) &&
		nextTemplates.filter( ( template ) => template.slug === nextSlug )
			.length === 1;
	const shouldRetainReference = Boolean( referenceSlug ) && ! isUnique;

	if ( shouldRetainReference ) {
		if ( change.item._slugReference === referenceSlug ) {
			return nextTemplates;
		}
		return replaceCollectionItem( nextTemplates, change.index, {
			...change.item,
			_slugReference: referenceSlug,
		} );
	}

	if ( change.item._slugReference === undefined ) {
		return nextTemplates;
	}

	const { _slugReference, ...nextItem } = change.item;
	return replaceCollectionItem( nextTemplates, change.index, nextItem );
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
