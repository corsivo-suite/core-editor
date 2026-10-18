document.addEventListener( 'DOMContentLoaded', () => {
	const wrapper = document.querySelector( '.corsivo-theme-wrap' );
	if ( ! wrapper ) {
		return;
	}

	const groupLinks = [
		...wrapper.querySelectorAll( '.corsivo-core-group-nav a[data-group]' ),
	];
	const submenus = [
		...wrapper.querySelectorAll( '.corsivo-core-submenu[data-group]' ),
	];
	const sectionLinks = [
		...wrapper.querySelectorAll( '.corsivo-core-sidebar a[data-section]' ),
	];
	const panels = [
		...wrapper.querySelectorAll( '.corsivo-theme-section[data-section]' ),
	];
	const settingsForm = wrapper.querySelector(
		'#corsivo-theme-settings-form'
	);
	const phpSubmit = wrapper.querySelector( '#corsivo-theme-php-submit' );
	const sectionField = settingsForm?.querySelector(
		'input[name="settings_section"]'
	);
	const formUrl =
		settingsForm?.getAttribute( 'action' )?.split( '#' )[ 0 ] || '';
	const sectionGroups = new Map();
	const groupDefaults = new Map();
	const visitedSections = new Map();

	submenus.forEach( ( submenu ) => {
		const links = submenu.querySelectorAll( 'a[data-section]' );
		if ( ! links.length ) {
			return;
		}

		groupDefaults.set( submenu.dataset.group, links[ 0 ].dataset.section );
		links.forEach( ( link ) => {
			sectionGroups.set( link.dataset.section, submenu.dataset.group );
		} );
	} );

	sectionLinks.forEach( ( link ) => {
		const panel = panels.find(
			( candidate ) => candidate.dataset.section === link.dataset.section
		);
		if ( ! panel ) {
			return;
		}

		panel.id ||= `corsivo-theme-section-${ link.dataset.section }`;
		link.setAttribute( 'aria-controls', panel.id );
	} );

	const activateSection = ( requestedSection ) => {
		const section = sectionGroups.has( requestedSection )
			? requestedSection
			: 'overview';
		const group = sectionGroups.get( section );
		const activePanel = panels.find(
			( panel ) => panel.dataset.section === section
		);
		const phpSection = !! settingsForm?.contains( activePanel );
		visitedSections.set( group, section );
		wrapper.dataset.active = section;

		groupLinks.forEach( ( link ) => {
			const active = link.dataset.group === group;
			const target =
				visitedSections.get( link.dataset.group ) ||
				groupDefaults.get( link.dataset.group );
			link.setAttribute( 'href', `#${ target }` );
			if ( active ) {
				link.setAttribute( 'aria-current', 'true' );
			} else {
				link.removeAttribute( 'aria-current' );
			}
		} );
		submenus.forEach( ( submenu ) => {
			submenu.hidden = submenu.dataset.group !== group;
		} );
		sectionLinks.forEach( ( link ) => {
			if ( link.dataset.section === section ) {
				link.setAttribute( 'aria-current', 'page' );
			} else {
				link.removeAttribute( 'aria-current' );
			}
		} );
		panels.forEach( ( panel ) => {
			panel.hidden = panel !== activePanel;
		} );

		if ( settingsForm ) {
			settingsForm.hidden = ! phpSection;
			settingsForm.action = `${ formUrl }#${ section }`;
		}
		if ( phpSubmit ) {
			phpSubmit.hidden = ! phpSection;
		}
		if ( sectionField ) {
			sectionField.value = section;
		}

		if ( window.location.hash !== `#${ section }` ) {
			const currentUrl = new URL( window.location.href );
			currentUrl.hash = section;
			window.history.replaceState( window.history.state, '', currentUrl );
		}
	};

	const activateCurrentSection = () => {
		const querySection = new URLSearchParams( window.location.search ).get(
			'section'
		);
		activateSection( window.location.hash.slice( 1 ) || querySection );
	};

	window.addEventListener( 'hashchange', activateCurrentSection );
	activateCurrentSection();
} );
