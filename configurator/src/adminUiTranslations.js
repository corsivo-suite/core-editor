import { __, sprintf } from '@wordpress/i18n';

const formatHandleLabel = ( itemLabel ) =>
	sprintf(
		/* translators: %s: item label. */
		__( 'Reorder %s', 'corsivo-core-editor' ),
		itemLabel
	);

const screenReaderInstructions = __(
	'To pick up an item, press space or enter. Use the arrow keys to move it, press space or enter to drop it, or press escape to cancel.',
	'corsivo-core-editor'
);

const announcements = {
	onDragStart: ( context ) =>
		sprintf(
			/* translators: %s: item label. */
			__( 'Picked up %s.', 'corsivo-core-editor' ),
			context.itemLabel
		),
	onDragOver: ( context ) =>
		context.overId === null
			? sprintf(
					/* translators: %s: item label. */
					__(
						'%s is no longer over a drop target.',
						'corsivo-core-editor'
					),
					context.itemLabel
			  )
			: sprintf(
					/* translators: 1: item label, 2: position, 3: total items. */
					__(
						'%1$s is over position %2$d of %3$d.',
						'corsivo-core-editor'
					),
					context.itemLabel,
					context.position,
					context.total
			  ),
	onDragEnd: ( context ) =>
		context.overId === null
			? sprintf(
					/* translators: %s: item label. */
					__(
						'%s was dropped without moving.',
						'corsivo-core-editor'
					),
					context.itemLabel
			  )
			: sprintf(
					/* translators: 1: item label, 2: position, 3: total items. */
					__(
						'Dropped %1$s at position %2$d of %3$d.',
						'corsivo-core-editor'
					),
					context.itemLabel,
					context.position,
					context.total
			  ),
	onDragCancel: ( context ) =>
		sprintf(
			/* translators: %s: item label. */
			__( 'Moving %s was cancelled.', 'corsivo-core-editor' ),
			context.itemLabel
		),
};

export const sortableCollectionTranslations = {
	formatHandleLabel,
	screenReaderInstructions,
	announcements,
};

export const repeaterTranslations = {
	...sortableCollectionTranslations,
	formatExpandLabel: ( itemLabel ) =>
		sprintf(
			/* translators: %s: item label. */
			__( 'Expand %s', 'corsivo-core-editor' ),
			itemLabel
		),
	formatCollapseLabel: ( itemLabel ) =>
		sprintf(
			/* translators: %s: item label. */
			__( 'Collapse %s', 'corsivo-core-editor' ),
			itemLabel
		),
	formatDuplicateLabel: ( itemLabel ) =>
		sprintf(
			/* translators: %s: item label. */
			__( 'Duplicate %s', 'corsivo-core-editor' ),
			itemLabel
		),
	formatRemoveLabel: ( itemLabel ) =>
		sprintf(
			/* translators: %s: item label. */
			__( 'Remove %s', 'corsivo-core-editor' ),
			itemLabel
		),
};

export const colorControlTranslations = {
	colorLabel: __( 'Choose a color', 'corsivo-core-editor' ),
	customColorLabel: __( 'Choose a custom color', 'corsivo-core-editor' ),
	emptyColorLabel: __( 'No color', 'corsivo-core-editor' ),
	clearLabel: __( 'Clear color', 'corsivo-core-editor' ),
};

export const entitySelectorTranslations = {
	...sortableCollectionTranslations,
	selectedItemsLabel: __( 'Selected items', 'corsivo-core-editor' ),
	removeItemLabel: __( 'Remove', 'corsivo-core-editor' ),
	noOptionsMessage: __( 'No results found.', 'corsivo-core-editor' ),
	loadingMessage: __( 'Loading results…', 'corsivo-core-editor' ),
	loadErrorMessage: __( 'Unable to load results.', 'corsivo-core-editor' ),
	minimumQueryMessage: __(
		'Type more characters to search.',
		'corsivo-core-editor'
	),
	clearLabel: __( 'Clear selection', 'corsivo-core-editor' ),
};
