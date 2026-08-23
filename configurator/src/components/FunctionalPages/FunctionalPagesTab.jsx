import { SelectControl, TextControl } from '@wordpress/components';
import { useMemo, useState } from '@wordpress/element';
import { EntitySelector, Repeater } from '@corsivo-suite/wp-admin-ui';
import { __ } from '@wordpress/i18n';
import { useConfig } from '../../store';
import {
	entitySelectorTranslations,
	repeaterTranslations,
} from '../../adminUiTranslations';
import {
	reconcilePageAssignments,
	sanitizeSlug,
} from '../../collectionAdapters';

function FunctionalPageFields( {
	page,
	onChange,
	assignment,
	onAssignmentChange,
	pageOptions,
} ) {
	const updateLabel = ( label ) => {
		const autoSlug = ! page.slug || page._autoSlug;
		onChange( {
			...page,
			label,
			...( autoSlug
				? { slug: sanitizeSlug( label ), _autoSlug: true }
				: {} ),
		} );
	};

	const selectedPageIds = assignment ? [ Number( assignment ) ] : [];

	return (
		<div className="ts-functional-pages__fields">
			{ page.fromFilter ? (
				<div className="ts-functional-pages__definition">
					<strong>{ page.label }</strong>
					<code>{ page.slug }</code>
				</div>
			) : (
				<>
					<TextControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label="Label"
						value={ page.label || '' }
						onChange={ updateLabel }
						placeholder="Page name"
					/>
					<TextControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label="Slug"
						value={ page.slug || '' }
						onChange={ ( slug ) =>
							onChange( {
								...page,
								slug: sanitizeSlug( slug, false ),
								_autoSlug: false,
							} )
						}
						onBlur={ () =>
							onChange( {
								...page,
								slug: sanitizeSlug( page.slug ),
							} )
						}
						placeholder="slug"
					/>
				</>
			) }
			<EntitySelector
				{ ...entitySelectorTranslations }
				label={ __( 'Assigned page', 'corsivo-theme-editor' ) }
				value={ selectedPageIds }
				onChange={ ( nextPageIds ) =>
					onAssignmentChange(
						nextPageIds.length
							? nextPageIds[ nextPageIds.length - 1 ]
							: ''
					)
				}
				options={ pageOptions }
				allowReorder={ false }
				placeholder={ __(
					'Search a WordPress page…',
					'corsivo-theme-editor'
				) }
				selectedItemsLabel={ __(
					'Assigned page',
					'corsivo-theme-editor'
				) }
				noOptionsMessage={ __(
					'No pages found.',
					'corsivo-theme-editor'
				) }
			/>
		</div>
	);
}

export default function FunctionalPagesTab() {
	const { state, dispatch } = useConfig();
	const pages = state.functionalPages || [];
	const assignments = state.pageAssignments || {};
	const currentLanguage = window.corsivoThemeEditor?.currentLang || 'default';
	const activeLanguages = window.corsivoThemeEditor?.activeLanguages || [
		{ code: currentLanguage, label: currentLanguage },
	];
	const pagesByLanguage = window.corsivoThemeEditor?.wpPagesByLanguage;
	const [ selectedLanguage, setSelectedLanguage ] =
		useState( currentLanguage );
	const pageOptions = useMemo( () => {
		const wordpressPages =
			pagesByLanguage?.[ selectedLanguage ] ||
			window.corsivoThemeEditor?.wpPages ||
			[];
		return wordpressPages.map( ( page ) => ( {
			value: Number( page.id ),
			label: page.title,
		} ) );
	}, [ pagesByLanguage, selectedLanguage ] );

	const updateAssignment = ( slug, pageId ) => {
		dispatch( {
			type: 'SET_PAGE_ASSIGNMENTS',
			payload: {
				...assignments,
				[ slug ]: {
					...( assignments[ slug ] || {} ),
					[ selectedLanguage ]: pageId ? Number( pageId ) : '',
				},
			},
		} );
	};

	const updatePages = ( nextPages, change ) => {
		dispatch( { type: 'SET_FUNCTIONAL_PAGES', payload: nextPages } );
		const nextAssignments = reconcilePageAssignments( assignments, change );
		if ( nextAssignments !== assignments ) {
			dispatch( {
				type: 'SET_PAGE_ASSIGNMENTS',
				payload: nextAssignments,
			} );
		}
	};

	return (
		<div className="ts-functional-pages">
			<h2>Functional Pages</h2>
			<p className="description">
				Special pages retrievable in the theme via{ ' ' }
				<code>{ "corsivo_theme_get_function_page('slug')" }</code>.
				Pages defined by PHP filters are read-only.
			</p>
			{ activeLanguages.length > 1 ? (
				<SelectControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					label="Language"
					value={ selectedLanguage }
					onChange={ setSelectedLanguage }
					options={ activeLanguages.map( ( language ) => ( {
						label: language.label,
						value: language.code,
					} ) ) }
				/>
			) : (
				<p>
					Language:{ ' ' }
					<strong>
						{ activeLanguages[ 0 ]?.label || selectedLanguage }
					</strong>
				</p>
			) }

			<Repeater
				{ ...repeaterTranslations }
				items={ pages }
				onChange={ updatePages }
				itemIdentity="transient"
				allowReorder={ false }
				createItem={ () => ( {
					slug: '',
					label: '',
					fromFilter: false,
				} ) }
				getItemLabel={ ( page ) =>
					page.label ||
					page.slug ||
					__( 'Functional page', 'corsivo-theme-editor' )
				}
				renderItemHeader={ ( { item } ) => (
					<>
						{ item.label ||
							item.slug ||
							__( 'Functional page', 'corsivo-theme-editor' ) }
						{ item.fromFilter && (
							<span className="ts-functional-pages__source">
								PHP filter
							</span>
						) }
					</>
				) }
				renderItem={ ( { item, onChange } ) => (
					<FunctionalPageFields
						page={ item }
						onChange={ onChange }
						assignment={
							assignments[ item.slug ]?.[ selectedLanguage ]
						}
						onAssignmentChange={ ( pageId ) =>
							updateAssignment( item.slug, pageId )
						}
						pageOptions={ pageOptions }
					/>
				) }
				canRemoveItem={ ( page ) => ! page.fromFilter }
				addLabel={ __( 'Add functional page', 'corsivo-theme-editor' ) }
				emptyState={ __(
					'No functional pages configured.',
					'corsivo-theme-editor'
				) }
				itemsLabel={ __( 'Functional pages', 'corsivo-theme-editor' ) }
			/>
		</div>
	);
}
