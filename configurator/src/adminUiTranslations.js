import { __, sprintf } from '@wordpress/i18n';

const formatHandleLabel = ( itemLabel ) =>
	sprintf(
		/* translators: %s: item label. */
		__( 'Reorder %s', 'corsivo-theme-editor' ),
		itemLabel
	);

const screenReaderInstructions = __(
	'To pick up an item, press space or enter. Use the arrow keys to move it, press space or enter to drop it, or press escape to cancel.',
	'corsivo-theme-editor'
);

const announcements = {
	onDragStart: ( context ) =>
		sprintf(
			/* translators: %s: item label. */
			__( 'Picked up %s.', 'corsivo-theme-editor' ),
			context.itemLabel
		),
	onDragOver: ( context ) =>
		context.overId === null
			? sprintf(
					/* translators: %s: item label. */
					__(
						'%s is no longer over a drop target.',
						'corsivo-theme-editor'
					),
					context.itemLabel
			  )
			: sprintf(
					/* translators: 1: item label, 2: position, 3: total items. */
					__(
						'%1$s is over position %2$d of %3$d.',
						'corsivo-theme-editor'
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
						'corsivo-theme-editor'
					),
					context.itemLabel
			  )
			: sprintf(
					/* translators: 1: item label, 2: position, 3: total items. */
					__(
						'Dropped %1$s at position %2$d of %3$d.',
						'corsivo-theme-editor'
					),
					context.itemLabel,
					context.position,
					context.total
			  ),
	onDragCancel: ( context ) =>
		sprintf(
			/* translators: %s: item label. */
			__( 'Moving %s was cancelled.', 'corsivo-theme-editor' ),
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
			__( 'Expand %s', 'corsivo-theme-editor' ),
			itemLabel
		),
	formatCollapseLabel: ( itemLabel ) =>
		sprintf(
			/* translators: %s: item label. */
			__( 'Collapse %s', 'corsivo-theme-editor' ),
			itemLabel
		),
	formatDuplicateLabel: ( itemLabel ) =>
		sprintf(
			/* translators: %s: item label. */
			__( 'Duplicate %s', 'corsivo-theme-editor' ),
			itemLabel
		),
	formatRemoveLabel: ( itemLabel ) =>
		sprintf(
			/* translators: %s: item label. */
			__( 'Remove %s', 'corsivo-theme-editor' ),
			itemLabel
		),
};

export const colorControlTranslations = {
	colorLabel: __( 'Choose a color', 'corsivo-theme-editor' ),
	customColorLabel: __( 'Choose a custom color', 'corsivo-theme-editor' ),
	emptyColorLabel: __( 'No color', 'corsivo-theme-editor' ),
	clearLabel: __( 'Clear color', 'corsivo-theme-editor' ),
};

export const entitySelectorTranslations = {
	...sortableCollectionTranslations,
	selectedItemsLabel: __( 'Selected items', 'corsivo-theme-editor' ),
	removeItemLabel: __( 'Remove', 'corsivo-theme-editor' ),
	noOptionsMessage: __( 'No results found.', 'corsivo-theme-editor' ),
	loadingMessage: __( 'Loading results…', 'corsivo-theme-editor' ),
	loadErrorMessage: __( 'Unable to load results.', 'corsivo-theme-editor' ),
	minimumQueryMessage: __(
		'Type more characters to search.',
		'corsivo-theme-editor'
	),
	clearLabel: __( 'Clear selection', 'corsivo-theme-editor' ),
};
