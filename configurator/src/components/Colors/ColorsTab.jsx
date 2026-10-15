import { Button, TextControl } from '@wordpress/components';
import { ColorControl, SortableCollection } from '@corsivo-suite/wp-admin-ui';
import { rectSortingStrategy } from '@dnd-kit/sortable';
import { __ } from '@wordpress/i18n';
import { useConfig } from '../../store';
import {
	colorControlTranslations,
	sortableCollectionTranslations,
} from '../../adminUiTranslations';
import {
	createPaletteColor,
	replaceCollectionItem,
	sanitizeSlug,
} from '../../collectionAdapters';

function ColorCard( { color, palette, onChange, onRemove } ) {
	return (
		<div className="ts-color-swatch">
			<ColorControl
				{ ...colorControlTranslations }
				label={ __( 'Color', 'corsivo-theme-editor' ) }
				value={ color.color || '' }
				onChange={ ( nextColor ) =>
					onChange( { ...color, color: nextColor } )
				}
				palette={ palette }
				allowCustom
				clearable={ false }
				compact
			/>
			<div className="ts-color-swatch__fields">
				<TextControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					label="Name"
					value={ color.name || '' }
					onChange={ ( name ) => onChange( { ...color, name } ) }
				/>
				<TextControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					label="Slug"
					help="Updates configuration references. Existing content and custom CSS keep the previous slug."
					value={ color.slug || '' }
					onChange={ ( slug ) =>
						onChange( {
							...color,
							slug: sanitizeSlug( slug, false ),
						} )
					}
					onBlur={ () =>
						onChange( {
							...color,
							slug: sanitizeSlug( color.slug ),
						} )
					}
				/>
			</div>
			<Button isDestructive variant="tertiary" onClick={ onRemove }>
				Remove color
			</Button>
		</div>
	);
}

export default function ColorsTab() {
	const { state, dispatch } = useConfig();
	const colors = state.colors;
	const palette = colors.map( ( color ) => ( {
		name: color.name,
		slug: color.slug,
		color: color.color,
	} ) );

	const setColors = ( nextColors, change ) => {
		dispatch( { type: 'SET_COLORS', payload: nextColors, change } );
	};

	return (
		<div className="ts-colors-tab">
			<h2>Colors</h2>
			<p className="description">
				Colors appear in the Gutenberg palette and generate CSS utility
				classes (<code>.slug-bkg</code>, <code>.slug-txt</code>,{ ' ' }
				<code>.has-slug-color</code>, etc.). Drag to reorder.
			</p>

			<SortableCollection
				{ ...sortableCollectionTranslations }
				items={ colors }
				onReorder={ setColors }
				itemIdentity="transient"
				getItemLabel={ ( color ) =>
					color.name ||
					color.slug ||
					__( 'Color', 'corsivo-theme-editor' )
				}
				ariaLabel={ __(
					'Theme color palette',
					'corsivo-theme-editor'
				) }
				className="ts-colors-grid"
				strategy={ rectSortingStrategy }
				renderItem={ ( { item: color, index } ) => (
					<ColorCard
						color={ color }
						palette={ palette }
						onChange={ ( nextColor ) =>
							setColors(
								replaceCollectionItem(
									colors,
									index,
									nextColor
								),
								{ type: 'update', index, previousItem: color, item: nextColor }
							)
						}
						onRemove={ () =>
							setColors(
								colors.filter(
									( candidate, candidateIndex ) =>
										candidateIndex !== index
								),
								{ type: 'remove', index, item: color }
							)
						}
					/>
				) }
			/>

			<Button
				className="ts-colors-tab__add"
				variant="secondary"
				onClick={ () =>
					setColors( [ ...colors, createPaletteColor( colors ) ] )
				}
			>
				Add color
			</Button>
		</div>
	);
}
