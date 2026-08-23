import { TextControl } from '@wordpress/components';
import { Repeater } from '@corsivo-suite/wp-admin-ui';
import { __ } from '@wordpress/i18n';
import { useConfig } from '../../store';
import { repeaterTranslations } from '../../adminUiTranslations';
import { createSpacingPreset } from '../../collectionAdapters';

function SpacingPresetFields( { preset, onChange } ) {
	return (
		<div className="ts-spacing-tab__preset-fields">
			<TextControl
				__next40pxDefaultSize
				__nextHasNoMarginBottom
				label="Slug"
				value={ preset.slug || '' }
				onChange={ ( slug ) => onChange( { ...preset, slug } ) }
			/>
			<TextControl
				__next40pxDefaultSize
				__nextHasNoMarginBottom
				label="Name"
				value={ preset.name || '' }
				onChange={ ( name ) => onChange( { ...preset, name } ) }
			/>
			<TextControl
				__next40pxDefaultSize
				__nextHasNoMarginBottom
				label="Size"
				value={ preset.size || '' }
				onChange={ ( size ) => onChange( { ...preset, size } ) }
			/>
		</div>
	);
}

export default function SpacingTab() {
	const { state, dispatch } = useConfig();
	const spacing = state.spacing || {};
	const spacingSizes = spacing.spacingSizes || [];

	const update = ( patch ) => {
		dispatch( {
			type: 'SET_SPACING',
			payload: { ...spacing, ...patch },
		} );
	};

	return (
		<div className="ts-spacing-tab">
			<h2>Spacing</h2>

			<h3>Layout</h3>
			<p className="description">
				Global content and wide widths for aligned blocks.
			</p>

			<div className="ts-spacing-tab__layout">
				<TextControl
					__next40pxDefaultSize
					label="Content Size"
					value={ spacing.contentSize || '' }
					onChange={ ( contentSize ) => update( { contentSize } ) }
					placeholder="e.g. 1200px"
					__nextHasNoMarginBottom
				/>
				<TextControl
					__next40pxDefaultSize
					label="Wide Size"
					value={ spacing.wideSize || '' }
					onChange={ ( wideSize ) => update( { wideSize } ) }
					placeholder="e.g. 1400px"
					__nextHasNoMarginBottom
				/>
			</div>

			<hr />

			<h3>Root Padding</h3>
			<p className="description">
				Space between the viewport edge and the main content column.
				Generates <code>--wp--style--root--padding-*</code>.
			</p>

			<div className="ts-spacing-tab__layout">
				<TextControl
					__next40pxDefaultSize
					label="Left"
					value={ spacing.rootPaddingLeft || '' }
					onChange={ ( rootPaddingLeft ) =>
						update( { rootPaddingLeft } )
					}
					placeholder="e.g. 5%"
					__nextHasNoMarginBottom
				/>
				<TextControl
					__next40pxDefaultSize
					label="Right"
					value={ spacing.rootPaddingRight || '' }
					onChange={ ( rootPaddingRight ) =>
						update( { rootPaddingRight } )
					}
					placeholder="e.g. 5%"
					__nextHasNoMarginBottom
				/>
			</div>

			<hr />

			<h3>Spacing Scale</h3>
			<p className="description">
				Define spacing presets available in the editor as{ ' ' }
				<code>var(--wp--preset--spacing--slug)</code>.
			</p>

			<TextControl
				__next40pxDefaultSize
				label="Block Gap"
				value={ spacing.blockGap || '' }
				onChange={ ( blockGap ) => update( { blockGap } ) }
				placeholder="e.g. 1.5rem"
				__nextHasNoMarginBottom
			/>

			<Repeater
				{ ...repeaterTranslations }
				label={ __( 'Spacing presets', 'corsivo-theme-editor' ) }
				items={ spacingSizes }
				onChange={ ( nextSpacingSizes ) =>
					update( { spacingSizes: nextSpacingSizes } )
				}
				itemIdentity="transient"
				createItem={ () => createSpacingPreset( spacingSizes ) }
				getItemLabel={ ( preset ) =>
					preset.name ||
					preset.slug ||
					__( 'Spacing preset', 'corsivo-theme-editor' )
				}
				renderItemHeader={ ( { item } ) =>
					item.name ||
					item.slug ||
					__( 'Spacing preset', 'corsivo-theme-editor' )
				}
				renderItem={ ( { item, onChange } ) => (
					<SpacingPresetFields
						preset={ item }
						onChange={ onChange }
					/>
				) }
				collapsible={ false }
				addLabel={ __( 'Add spacing preset', 'corsivo-theme-editor' ) }
				emptyState={ __(
					'No spacing presets configured.',
					'corsivo-theme-editor'
				) }
			/>
		</div>
	);
}
