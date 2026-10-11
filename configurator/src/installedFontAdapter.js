import { createCollectionIdentity } from './collectionIdentity';

export function initializeInstalledFont( font ) {
	const weightIdentities = Object.keys( font[ 'font-weight' ] || {} ).reduce(
		( identities, weight ) => {
			identities[ weight ] =
				font._weightIdentities?.[ weight ] || createCollectionIdentity();
			return identities;
		},
		{}
	);
	return {
		...font,
		_uid: font._uid || createCollectionIdentity(),
		_weightIdentities: weightIdentities,
	};
}

export function renameInstalledFontWeight( font, previousWeight, nextWeight ) {
	const weights = font[ 'font-weight' ] || {};
	if (
		! nextWeight ||
		previousWeight === nextWeight ||
		weights[ previousWeight ] === undefined ||
		weights[ nextWeight ] !== undefined
	) {
		return font;
	}

	const nextWeights = {};
	const nextIdentities = {};
	Object.entries( weights ).forEach( ( [ weight, url ] ) => {
		const name = weight === previousWeight ? nextWeight : weight;
		nextWeights[ name ] = url;
		nextIdentities[ name ] = font._weightIdentities[ weight ];
	} );
	return {
		...font,
		'font-weight': nextWeights,
		_weightIdentities: nextIdentities,
	};
}

export function updateInstalledFontWeightUrl( fonts, fontIdentity, weightIdentity, url ) {
	const fontIndex = fonts.findIndex( ( font ) => font._uid === fontIdentity );
	if ( fontIndex === -1 ) {
		return fonts;
	}
	const font = fonts[ fontIndex ];
	const weight = Object.keys( font[ 'font-weight' ] || {} ).find(
		( name ) => font._weightIdentities?.[ name ] === weightIdentity
	);
	if ( weight === undefined || font[ 'font-weight' ][ weight ] === url ) {
		return fonts;
	}
	return fonts.map( ( currentFont, index ) =>
		index === fontIndex
			? {
				...currentFont,
				'font-weight': { ...currentFont[ 'font-weight' ], [ weight ]: url },
			}
			: currentFont
	);
}
