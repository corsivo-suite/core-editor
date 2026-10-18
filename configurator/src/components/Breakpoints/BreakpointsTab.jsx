import { TextControl } from '@wordpress/components';
import { useEffect, useMemo, useRef, useState } from '@wordpress/element';
import { Repeater } from '@corsivo-suite/wp-admin-ui';
import { __ } from '@wordpress/i18n';
import { useConfig } from '../../store';
import { repeaterTranslations } from '../../adminUiTranslations';
import {
	breakpointItemsToRecord,
	createBreakpointItem,
	reconcileBreakpointItems,
	renameBreakpointReferences,
	replaceCollectionItem,
} from '../../collectionAdapters';

function sanitizeBreakpointName( value ) {
	return String( value || '' )
		.toLowerCase()
		.replace( /[^a-z0-9-]/g, '' );
}

function BreakpointFields( { item, onRename, onValueChange } ) {
	const [ draftName, setDraftName ] = useState( item.name );
	const [ draftValue, setDraftValue ] = useState( String( item.value ) );

	useEffect( () => {
		setDraftName( item.name );
	}, [ item.name ] );

	useEffect( () => {
		setDraftValue( String( item.value ) );
	}, [ item.value ] );

	const commitName = () => {
		if ( ! onRename( item.name, draftName ) ) {
			setDraftName( item.name );
		}
	};

	const commitValue = () => {
		const parsedValue = Number.parseInt( draftValue, 10 );
		if ( ! Number.isInteger( parsedValue ) || parsedValue <= 0 ) {
			setDraftValue( String( item.value ) );
			return;
		}
		setDraftValue( String( parsedValue ) );
		if ( parsedValue !== item.value ) {
			onValueChange( parsedValue );
		}
	};

	return (
		<div className="ts-breakpoints-tab__fields">
			<TextControl
				__next40pxDefaultSize
				__nextHasNoMarginBottom
				label="Name"
				value={ draftName }
				onChange={ ( name ) =>
					setDraftName( sanitizeBreakpointName( name ) )
				}
				onBlur={ commitName }
			/>
			<TextControl
				__next40pxDefaultSize
				__nextHasNoMarginBottom
				label="Max-width"
				type="number"
				value={ draftValue }
				onChange={ setDraftValue }
				onBlur={ commitValue }
				suffix="px"
			/>
		</div>
	);
}

export default function BreakpointsTab() {
	const { state, dispatch } = useConfig();
	const breakpoints = state.breakpoints;
	const breakpointItemsRef = useRef( [] );
	const breakpointItems = useMemo(
		() =>
			reconcileBreakpointItems( breakpoints, breakpointItemsRef.current ),
		[ breakpoints ]
	);

	useEffect( () => {
		breakpointItemsRef.current = breakpointItems;
	}, [ breakpointItems ] );

	const commitBreakpointItems = ( nextItems ) => {
		breakpointItemsRef.current = nextItems;
		dispatch( {
			type: 'SET_BREAKPOINTS',
			payload: breakpointItemsToRecord( nextItems ),
		} );
	};

	const renameBreakpoint = ( oldName, newName ) => {
		if ( oldName === newName ) {
			return true;
		}
		if (
			! newName ||
			breakpointItems.some( ( item ) => item.name === newName )
		) {
			return false;
		}

		const itemIndex = breakpointItems.findIndex(
			( item ) => item.name === oldName
		);
		const nextItems = replaceCollectionItem( breakpointItems, itemIndex, {
			...breakpointItems[ itemIndex ],
			name: newName,
		} );
		commitBreakpointItems( nextItems );

		const nextFonts = renameBreakpointReferences(
			state.fonts,
			oldName,
			newName
		);
		if (
			nextFonts.some( ( font, index ) => font !== state.fonts[ index ] )
		) {
			dispatch( { type: 'SET_FONTS', payload: nextFonts } );
		}
		return true;
	};

	const setBreakpointValue = ( itemIndex, value ) => {
		commitBreakpointItems(
			replaceCollectionItem( breakpointItems, itemIndex, {
				...breakpointItems[ itemIndex ],
				value,
			} )
		);
	};

	const isUsedByFonts = ( name ) =>
		state.fonts.some(
			( font ) =>
				font.breakpoints && font.breakpoints[ name ] !== undefined
		);

	return (
		<div className="ts-breakpoints-tab">
			<h2>Breakpoints</h2>
			<p className="description">
				Define responsive breakpoints. Fonts can have specific overrides
				for each breakpoint.
			</p>

			<Repeater
				{ ...repeaterTranslations }
				items={ breakpointItems }
				onChange={ commitBreakpointItems }
				itemIdentity="transient"
				allowReorder={ false }
				collapsible={ false }
				createItem={ () => createBreakpointItem( breakpointItems ) }
				getItemLabel={ ( item ) => item.name }
				renderItemHeader={ ( { item } ) => item.name }
				renderItem={ ( { item, index } ) => (
					<BreakpointFields
						item={ item }
						onRename={ renameBreakpoint }
						onValueChange={ ( value ) =>
							setBreakpointValue( index, value )
						}
					/>
				) }
				canRemoveItem={ ( item ) => ! isUsedByFonts( item.name ) }
				addLabel={ __( 'Add breakpoint', 'corsivo-core-editor' ) }
				emptyState={ __(
					'No breakpoints configured.',
					'corsivo-core-editor'
				) }
				itemsLabel={ __(
					'Responsive breakpoints',
					'corsivo-core-editor'
				) }
			/>
		</div>
	);
}
