import {
	SelectControl,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import { Repeater } from '@corsivo-suite/wp-admin-ui';
import { __ } from '@wordpress/i18n';
import { repeaterTranslations } from '../../adminUiTranslations';
import {
	reconcileTemplateReferences,
	retainTemplateSlugReference,
	sanitizeSlug,
	setDefaultStyleTemplate,
} from '../../collectionAdapters';

const createStyleTemplate = () => ( {
	slug: '',
	name: '',
	body: {},
	heading: {},
	link: {},
	titleStyle: '',
	defaultParagraphStyle: '',
	defaultHeadingStyle: '',
	isDefault: false,
} );

const updateTemplateSlug = ( item, slug, properties = {} ) => {
	const { _slugReference, ...template } = item;
	const referenceSlug = _slugReference || item.slug || '';

	return {
		...template,
		...properties,
		slug,
		...( ! slug && referenceSlug ? { _slugReference: referenceSlug } : {} ),
	};
};

export function ContextAssignments( {
	items,
	onChange,
	availableContexts,
	templateOptions,
} ) {
	return (
		<Repeater
			{ ...repeaterTranslations }
			items={ items }
			onChange={ onChange }
			itemIdentity="transient"
			collapsible={ false }
			createItem={ () => ( { context: '', template: '' } ) }
			getItemLabel={ ( item ) =>
				item.context ||
				__( 'Context assignment', 'corsivo-theme-editor' )
			}
			renderItemHeader={ ( { item } ) =>
				item.context ||
				__( 'Context assignment', 'corsivo-theme-editor' )
			}
			renderItem={ ( { item, onChange: updateItem } ) => (
				<div className="ts-context-assignments__fields">
					<SelectControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label="Context"
						value={ item.context || '' }
						onChange={ ( context ) =>
							updateItem( { ...item, context } )
						}
					>
						<option value="">— Select —</option>
						{ availableContexts.map( ( context ) => (
							<option
								key={ context.value }
								value={ context.value }
							>
								{ context.label } (.{ context.value })
							</option>
						) ) }
					</SelectControl>
					<SelectControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label="Template"
						value={ item.template || '' }
						options={ templateOptions }
						onChange={ ( template ) =>
							updateItem( { ...item, template } )
						}
					/>
				</div>
			) }
			addLabel={ __( 'Add assignment', 'corsivo-theme-editor' ) }
			emptyState={ __(
				'No context assignments configured.',
				'corsivo-theme-editor'
			) }
			itemsLabel={ __( 'Context assignments', 'corsivo-theme-editor' ) }
		/>
	);
}

export function StyleTemplateCollection( {
	items,
	onChange,
	contextStyles,
	onContextStylesChange,
	renderFields,
} ) {
	const commitCollectionChange = ( nextItems, change ) => {
		const stabilizedItems = retainTemplateSlugReference(
			nextItems,
			change
		);
		onChange( stabilizedItems );
		const nextContextStyles = reconcileTemplateReferences(
			contextStyles,
			change,
			stabilizedItems
		);
		if ( nextContextStyles !== contextStyles ) {
			onContextStylesChange( nextContextStyles );
		}
	};

	const setDefaultTemplate = ( itemIndex, enabled ) => {
		onChange( setDefaultStyleTemplate( items, itemIndex, enabled ) );
	};

	return (
		<Repeater
			{ ...repeaterTranslations }
			items={ items }
			onChange={ commitCollectionChange }
			itemIdentity="transient"
			createItem={ createStyleTemplate }
			getItemLabel={ ( item ) =>
				item.name ||
				item.slug ||
				__( 'Style template', 'corsivo-theme-editor' )
			}
			renderItemHeader={ ( { item } ) => (
				<div className="ts-style-template__summary">
					<span className="ts-style-template__name">
						{ item.name ||
							__( 'Untitled', 'corsivo-theme-editor' ) }
					</span>
					{ item.slug && (
						<span className="ts-style-template__slug">
							{ item.slug }
						</span>
					) }
					{ item.isDefault && (
						<span className="ts-style-template__badge">
							default
						</span>
					) }
				</div>
			) }
			renderItem={ ( { item, index, onChange: updateItem } ) => (
				<div className="ts-style-template__body">
					<div className="ts-style-template__meta-row">
						<TextControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							label="Name"
							value={ item.name || '' }
							onChange={ ( name ) => {
								if ( item._slugManual ) {
									updateItem( { ...item, name } );
									return;
								}
								updateItem(
									updateTemplateSlug(
										item,
										sanitizeSlug( name ),
										{ name }
									)
								);
							} }
							placeholder="e.g. Dark Theme"
						/>
						<TextControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							label="Slug"
							value={ item.slug || '' }
							onChange={ ( slug ) =>
								updateItem(
									updateTemplateSlug(
										item,
										sanitizeSlug( slug, false ),
										{ _slugManual: true }
									)
								)
							}
							onBlur={ () =>
								updateItem(
									updateTemplateSlug(
										item,
										sanitizeSlug( item.slug ),
										{ _slugManual: true }
									)
								)
							}
							placeholder="auto-generated"
						/>
					</div>
					<ToggleControl
						__nextHasNoMarginBottom
						label="Default template"
						checked={ Boolean( item.isDefault ) }
						onChange={ ( enabled ) =>
							setDefaultTemplate( index, enabled )
						}
						help="Applied automatically when no context or per-page override matches."
					/>
					{ renderFields( item, updateItem ) }
				</div>
			) }
			addLabel={ __( 'Add template', 'corsivo-theme-editor' ) }
			emptyState={ __(
				'No style templates configured.',
				'corsivo-theme-editor'
			) }
			itemsLabel={ __( 'Style templates', 'corsivo-theme-editor' ) }
		/>
	);
}
