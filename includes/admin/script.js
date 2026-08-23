document.addEventListener( 'DOMContentLoaded', () => {
	const wrapper = document.querySelector( '.corsivo-theme-wrap' );
	if ( ! wrapper ) return;

	const navigation = wrapper.querySelector( '.corsivo-theme-nav' );
	const buttons = [
		...wrapper.querySelectorAll( '.corsivo-theme-nav [data-section]' ),
	];
	const sections = [
		...wrapper.querySelectorAll( '.corsivo-theme-section[data-section]' ),
	];
	const settingsForm = document.getElementById(
		'corsivo-theme-settings-form'
	);
	const formUrl = settingsForm
		? settingsForm.getAttribute( 'action' ) || ''
		: '';
	const availableSections = new Set(
		buttons.map( ( button ) => button.dataset.section )
	);

	sections.forEach( ( section ) => {
		section.id = `corsivo-theme-section-${ section.dataset.section }`;
		section.setAttribute( 'role', 'tabpanel' );
		section.setAttribute(
			'aria-labelledby',
			`corsivo-theme-tab-${ section.dataset.section }`
		);
	} );
	buttons.forEach( ( button ) => {
		button.id = `corsivo-theme-tab-${ button.dataset.section }`;
		button.setAttribute(
			'aria-controls',
			`corsivo-theme-section-${ button.dataset.section }`
		);
	} );

	const keepActiveTabVisible = ( button ) => {
		if ( ! navigation || ! button ) return;

		const navigationRect = navigation.getBoundingClientRect();
		const buttonRect = button.getBoundingClientRect();
		const outsideViewport =
			buttonRect.left < navigationRect.left ||
			buttonRect.right > navigationRect.right;

		if ( outsideViewport ) {
			button.scrollIntoView( { block: 'nearest', inline: 'nearest' } );
		}
	};

	const activateSection = ( requestedSection ) => {
		const section = availableSections.has( requestedSection )
			? requestedSection
			: 'styles';
		wrapper.dataset.active = section;
		let activeButton = null;

		buttons.forEach( ( button ) => {
			const active = button.dataset.section === section;
			if ( active ) activeButton = button;
			button.classList.toggle( 'active', active );
			button.setAttribute( 'aria-selected', active ? 'true' : 'false' );
			button.tabIndex = active ? 0 : -1;
		} );
		sections.forEach( ( panel ) => {
			panel.hidden = panel.dataset.section !== section;
		} );

		if ( settingsForm ) settingsForm.action = `${ formUrl }#${ section }`;
		keepActiveTabVisible( activeButton );
	};

	const selectSection = ( section ) => {
		if ( window.location.hash === `#${ section }` ) {
			activateSection( section );
		} else {
			window.location.hash = section;
		}
	};

	buttons.forEach( ( button ) => {
		button.addEventListener( 'click', () => {
			selectSection( button.dataset.section );
		} );
	} );

	if ( navigation ) {
		navigation.addEventListener( 'keydown', ( event ) => {
			const currentIndex = buttons.indexOf( event.target );
			if ( currentIndex === -1 ) return;

			const rtl = getComputedStyle( navigation ).direction === 'rtl';
			let nextIndex = null;

			switch ( event.key ) {
				case 'ArrowLeft':
					nextIndex = currentIndex + ( rtl ? 1 : -1 );
					break;
				case 'ArrowRight':
					nextIndex = currentIndex + ( rtl ? -1 : 1 );
					break;
				case 'Home':
					nextIndex = 0;
					break;
				case 'End':
					nextIndex = buttons.length - 1;
					break;
				default:
					return;
			}

			event.preventDefault();
			const nextButton =
				buttons[ ( nextIndex + buttons.length ) % buttons.length ];
			nextButton.focus( { preventScroll: true } );
			selectSection( nextButton.dataset.section );
		} );
	}

	window.addEventListener( 'hashchange', () =>
		activateSection( window.location.hash.slice( 1 ) )
	);
	const querySection =
		new URLSearchParams( window.location.search ).get( 'section' ) || '';
	activateSection( window.location.hash.slice( 1 ) || querySection );
} );
