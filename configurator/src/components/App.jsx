import { Component, createPortal } from '@wordpress/element';
import { ConfigProvider, useConfig } from '../store';
import StylesTab from './Styles/StylesTab';
import ColorsTab from './Colors/ColorsTab';
import BreakpointsTab from './Breakpoints/BreakpointsTab';
import FunctionalPagesTab from './FunctionalPages/FunctionalPagesTab';
import TemplatesTab from './Templates/TemplatesTab';
import FontsTab from './Fonts/FontsTab';
import SpacingTab from './Spacing/SpacingTab';
import OverviewTab from './Overview/OverviewTab';
import { Button, Notice } from '@wordpress/components';
import {
	normalizePageAssignments,
	normalizeRecordCollection,
} from '../configAdapter';

const SECTIONS = [
	'overview',
	'fonts',
	'styles',
	'colors',
	'breakpoints',
	'spacing',
	'functional-pages',
	'templates',
];

const SECTION_LABELS = {
	overview: 'Overview',
	fonts: 'Fonts',
	styles: 'Styles',
	colors: 'Colors',
	breakpoints: 'Breakpoints',
	spacing: 'Spacing',
	'functional-pages': 'Functional Pages',
	templates: 'Templates',
};

const isRecord = (value) =>
	value !== null && typeof value === 'object' && !Array.isArray(value);

const normalizeRecord = (value) => (isRecord(value) ? value : {});

const normalizeFunctionalPage = (value, fallbackSlug = '') => {
	if (isRecord(value)) {
		const slug = String(value.slug ?? fallbackSlug);
		const label = String(
			value.label ??
				value.name ??
				(fallbackSlug !== '' ? fallbackSlug : slug)
		);
		return { ...value, slug, label };
	}

	if (typeof value === 'string' || typeof value === 'number') {
		const slug = fallbackSlug || String(value);
		return { slug, label: String(value) };
	}

	if (fallbackSlug) {
		return { slug: fallbackSlug, label: fallbackSlug };
	}

	return null;
};

const normalizeFunctionalPages = (value, fromFilter) => {
	let entries = [];

	if (Array.isArray(value)) {
		entries = value.map((item) => ['', item]);
	} else if (isRecord(value)) {
		entries =
			'slug' in value || 'label' in value
				? [['', value]]
				: Object.entries(value);
	}

	return entries.reduce((pages, [fallbackSlug, item]) => {
		const page = normalizeFunctionalPage(item, fallbackSlug);
		if (page) pages.push({ ...page, fromFilter });
		return pages;
	}, []);
};

const normalizeStyle = (value) => {
	const style = normalizeRecord(value);
	return {
		...style,
		body: normalizeRecord(style.body),
		heading: normalizeRecord(style.heading),
		link: normalizeRecord(style.link),
	};
};

const normalizeInitialData = (localizedData) => {
	const source = normalizeRecord(localizedData);
	const config = normalizeRecord(source.configData);
	const currentLanguage =
		typeof source.currentLang === 'string' && source.currentLang
			? source.currentLang
			: 'default';
	const functionalPages = normalizeFunctionalPages(source.filterPages, true);

	normalizeFunctionalPages(config.functional_pages, false).forEach((page) => {
		const filterIndex = functionalPages.findIndex(
			(filterPage) => filterPage.slug === page.slug
		);
		if (filterIndex === -1) {
			functionalPages.push(page);
		} else {
			functionalPages[filterIndex] = page;
		}
	});

	const fonts = normalizeRecordCollection(config.fonts, 'slug').map((font) =>
		font.breakpoints === undefined
			? font
			: { ...font, breakpoints: normalizeRecord(font.breakpoints) }
	);
	const customFonts = normalizeRecordCollection(config.custom_fonts).map(
		(entry) => ({
			...entry,
			fonts: normalizeRecordCollection(entry.fonts, 'family'),
		})
	);
	const installFonts = normalizeRecordCollection(config.install_fonts).map(
		(font) => ({
			...font,
			features: Array.isArray(font.features) ? font.features : [],
		})
	);
	const coreComponents = normalizeRecord(config.core_components);
	const spacing = normalizeRecord(config.spacing);
	const revision = source.configRevision;

	return {
		serverRevision:
			typeof revision === 'string' || typeof revision === 'number'
				? String(revision)
				: '',
		fonts,
		colors: normalizeRecordCollection(config.colors, 'slug'),
		breakpoints: normalizeRecord(config.breakpoints),
		customFonts,
		installFonts,
		functionalPages,
		pageAssignments: normalizePageAssignments(
			source.pageAssignments,
			currentLanguage
		),
		globalStyles: normalizeStyle(config.global_styles),
		styleTemplates: normalizeRecordCollection(
			config.style_templates,
			'slug'
		).map(normalizeStyle),
		contextStyles: normalizeRecordCollection(
			config.context_styles,
			'context'
		),
		coreComponents: {
			elements: normalizeRecord(coreComponents.elements),
			blockStyles: normalizeRecordCollection(
				coreComponents.blockStyles,
				'slug'
			),
		},
		spacing: {
			...spacing,
			spacingSizes: normalizeRecordCollection(
				spacing.spacingSizes,
				'slug'
			),
		},
	};
};

class TabErrorBoundary extends Component {
	constructor(props) {
		super(props);
		this.state = { error: null };
	}

	static getDerivedStateFromError(error) {
		return { error };
	}

	render() {
		if (!this.state.error) return this.props.children;

		const sectionLabel = SECTION_LABELS[this.props.section] || 'This panel';
		return (
			<div className="ts-configurator">
				<Notice status="error" isDismissible={false}>
					<strong>{sectionLabel} could not be loaded.</strong>{' '}
					The other panels are still available. Reload the page and, if
					the error persists, check the browser console. Error:{' '}
					{this.state.error.message || 'Unknown rendering error'}
				</Notice>
			</div>
		);
	}
}

function renderTab(section) {
	switch (section) {
		case 'styles':
			return <StylesTab />;
		case 'colors':
			return <ColorsTab />;
		case 'breakpoints':
			return <BreakpointsTab />;
		case 'functional-pages':
			return <FunctionalPagesTab />;
		case 'templates':
			return <TemplatesTab />;
		case 'fonts':
			return <FontsTab />;
		case 'spacing':
			return <SpacingTab />;
		case 'overview':
			return <OverviewTab />;
		default:
			return null;
	}
}

/**
 * Content for a single tab — rendered via portal into its PHP container.
 * All TabContent instances share the same ConfigProvider (and state).
 */
function TabContent({ section }) {
	const { state, save } = useConfig();

	// Overview is read-only — no save button needed
	if (section === 'overview') {
		return (
			<div className="ts-configurator">
				{renderTab(section)}
			</div>
		);
	}

	return (
		<div className="ts-configurator">
			{renderTab(section)}

			<div className="ts-configurator__actions">
				<div aria-live="polite">
					{state.saveStatus === 'success' && (
						<Notice status={state.saveWarning ? 'warning' : 'success'} isDismissible={false}>
							{state.saveMessage}
						</Notice>
					)}
					{state.saveStatus === 'error' && (
						<Notice status="error" isDismissible={false}>
							Error: {state.error}
						</Notice>
					)}
				</div>
				{state.isDirty && (
					<span className="ts-configurator__dirty-indicator">
						Unsaved changes
					</span>
				)}
				<Button
					variant="primary"
					onClick={save}
					isBusy={state.isSaving}
					disabled={!state.isDirty || state.isSaving}
				>
					{state.isSaving ? 'Saving...' : 'Save changes'}
				</Button>
			</div>
		</div>
	);
}

/**
 * Single App root — one ConfigProvider wrapping all tabs via portals.
 * This ensures all tabs share the same state, so saving from any tab
 * sends the complete, up-to-date data without overwriting other tabs' changes.
 */
export default function App() {
	const initialData = normalizeInitialData(window.corsivoThemeEditor);

	// Collect portal targets — only render into containers that exist in the DOM
	const portals = [];
	SECTIONS.forEach((section) => {
		const container = document.getElementById(`corsivo-theme-configurator-${section}`);
		if (container) {
			portals.push({ section, container });
		}
	});

	return (
		<ConfigProvider initialData={initialData}>
			{portals.map(({ section, container }) =>
				createPortal(
					<TabErrorBoundary section={section}>
						<TabContent section={section} />
					</TabErrorBoundary>,
					container,
					section
				)
			)}
		</ConfigProvider>
	);
}
