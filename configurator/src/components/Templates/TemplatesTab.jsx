import { useState } from '@wordpress/element';
import { SelectControl, TextControl } from '@wordpress/components';
import { useConfig } from '../../store';
import {
	ContextAssignments,
	StyleTemplateCollection,
} from './TemplateCollections';

/* ── Built-in WordPress contexts (body classes) ── */
const BUILTIN_CONTEXTS = [
	{ value: 'home', label: 'Homepage' },
	{ value: 'blog', label: 'Blog (Posts page)' },
	{ value: 'archive', label: 'All Archives' },
	{ value: 'single-post', label: 'Single Post' },
	{ value: 'page', label: 'Pages' },
	{ value: 'search', label: 'Search Results' },
	{ value: 'error404', label: '404 Page' },
];

/* ── Helpers ── */

function FontSelect({ label, value, fonts, onChange }) {
	const options = [{ label: '— Inherit —', value: '' }];
	fonts.forEach((f) => {
		options.push({ label: f.name || f.slug, value: f.slug });
	});
	return (
		<SelectControl
			__next40pxDefaultSize
			label={label}
			value={value || ''}
			options={options}
			onChange={onChange}
			__nextHasNoMarginBottom
		/>
	);
}

function ColorSelect({ label, value, colors, onChange }) {
	return (
		<div className="ts-template-color-select">
			<SelectControl
				__next40pxDefaultSize
				label={label}
				value={value || ''}
				onChange={onChange}
				__nextHasNoMarginBottom
			>
				<option value="">— Inherit —</option>
				{colors.map((c) => (
					<option key={c.slug} value={c.slug}>
						{c.name || c.slug} ({c.color})
					</option>
				))}
			</SelectControl>
			{value && (
				<span
					className="ts-template-color-select__dot"
					style={{
						backgroundColor: (colors.find((c) => c.slug === value) || {}).color,
					}}
				/>
			)}
		</div>
	);
}

/* ── Shared template fields (body / heading / link) ── */

function TemplateFields({ data, fonts, colors, onChange, isGlobal = false }) {
	const body = data.body || {};
	const heading = data.heading || {};
	const link = data.link || {};

	const update = (section, field, value) => {
		onChange({
			...data,
			[section]: { ...(data[section] || {}), [field]: value },
		});
	};

	return (
		<div className="ts-style-template__fields">
			<div className="ts-style-template__group">
				<span className="ts-style-template__group-label">Body</span>
				<FontSelect label="Font" value={body.font} fonts={fonts} onChange={(v) => update('body', 'font', v)} />
				<ColorSelect label="Background" value={body.background} colors={colors} onChange={(v) => update('body', 'background', v)} />
				<ColorSelect label="Text" value={body.color} colors={colors} onChange={(v) => update('body', 'color', v)} />
			</div>
			<div className="ts-style-template__group">
				<span className="ts-style-template__group-label">Heading</span>
				<FontSelect label="Font" value={heading.font} fonts={fonts} onChange={(v) => update('heading', 'font', v)} />
				<ColorSelect label="Color" value={heading.color} colors={colors} onChange={(v) => update('heading', 'color', v)} />
				{isGlobal && (
					<TextControl
						__next40pxDefaultSize
						label="Font Weight"
						value={heading.fontWeight || ''}
						onChange={(v) => update('heading', 'fontWeight', v)}
						placeholder="700"
						__nextHasNoMarginBottom
					/>
				)}
			</div>
			<div className="ts-style-template__group">
				<span className="ts-style-template__group-label">Link</span>
				<ColorSelect label="Color" value={link.color} colors={colors} onChange={(v) => update('link', 'color', v)} />
				{isGlobal && (
					<ColorSelect label="Hover Color" value={link.hoverColor} colors={colors} onChange={(v) => update('link', 'hoverColor', v)} />
				)}
			</div>
			<div className="ts-style-template__group">
				<span className="ts-style-template__group-label">Page Title</span>
				<FontSelect
					label="Style"
					value={data.titleStyle || ''}
					fonts={fonts}
					onChange={(v) =>
						onChange({
							...data,
							titleStyle: v,
						})
					}
				/>
			</div>
			<div className="ts-style-template__group">
				<span className="ts-style-template__group-label">Default Blocks</span>
				<FontSelect
					label="Paragraph"
					value={data.defaultParagraphStyle || ''}
					fonts={fonts}
					onChange={(v) =>
						onChange({
							...data,
							defaultParagraphStyle: v,
						})
					}
				/>
				<FontSelect
					label="Heading"
					value={data.defaultHeadingStyle || ''}
					fonts={fonts}
					onChange={(v) =>
						onChange({
							...data,
							defaultHeadingStyle: v,
						})
					}
				/>
			</div>
		</div>
	);
}

/* ── Main component ── */

export default function TemplatesTab() {
	const { state, dispatch } = useConfig();
	const globalStyles = state.globalStyles || {};
	const styleTemplates = state.styleTemplates || [];
	const contextStyles = state.contextStyles || [];
	const fonts = state.fonts || [];
	const colors = state.colors || [];

	// Accordion state — 'global' is expanded by default
	const [expanded, setExpanded] = useState(new Set(['global']));

	const toggleExpand = (key) => {
		setExpanded((prev) => {
			const next = new Set(prev);
			if (next.has(key)) next.delete(key);
			else next.add(key);
			return next;
		});
	};

	// ── Contexts ──
	const cptContexts = window.corsivoThemeEditor?.cptContexts || [];
	const templateContexts = window.corsivoThemeEditor?.templateContexts || [];
	const integrationContexts = window.corsivoThemeEditor?.availableContexts || [];
	const availableContexts = [...BUILTIN_CONTEXTS, ...cptContexts, ...templateContexts, ...integrationContexts];

	// Template options for assignment dropdown
	const templateOptions = [
		{ label: '— None —', value: '' },
		...styleTemplates
			.filter((t) => t.slug)
			.map((t) => ({ label: t.name || t.slug, value: t.slug })),
	];

	return (
		<div className="ts-templates-tab">
			<h2>Templates</h2>

			{/* ═══════════ Context Assignments ═══════════ */}
			<section className="ts-templates-tab__section">
				<h3>Context Assignments</h3>
				<p className="description">
					Assign a style template to each page context. Uses WordPress body
					classes (e.g. <code>.home</code>, <code>.single-post</code>).
					The style is also previewed in the Gutenberg editor.
				</p>

				<ContextAssignments
					items={contextStyles}
					onChange={(nextContextStyles) =>
						dispatch({
							type: 'SET_CONTEXT_STYLES',
							payload: nextContextStyles,
						})
					}
					availableContexts={availableContexts}
					templateOptions={templateOptions}
				/>
			</section>

			<hr />

			{/* ═══════════ Style Templates ═══════════ */}
			<section className="ts-templates-tab__section">
				<h3>Style Templates</h3>

				{/* ── Global (fixed, non-deletable, yellow) ── */}
				<div className={`ts-style-template ts-style-template--global${expanded.has('global') ? ' ts-style-template--expanded' : ''}`}>
					<div
						className="ts-style-template__header"
						onClick={() => toggleExpand('global')}
					>
						<div className="ts-style-template__summary">
							<span className="ts-style-template__name">Global</span>
							<span className="ts-style-template__badge">theme.json</span>
						</div>
						<span className="ts-style-template__toggle">
							{expanded.has('global') ? '▲' : '▼'}
						</span>
					</div>
					{expanded.has('global') && (
						<div className="ts-style-template__body">
							<p className="description">
								Default styles applied globally via <code>theme.json</code>.
								Affects both frontend and Gutenberg editor.
							</p>
							<TemplateFields
								data={globalStyles}
								fonts={fonts}
								colors={colors}
								onChange={(updated) => dispatch({ type: 'SET_GLOBAL_STYLES', payload: updated })}
								isGlobal
							/>
						</div>
					)}
				</div>

				<StyleTemplateCollection
					items={styleTemplates}
					onChange={(nextTemplates, change) =>
						dispatch({
							type: 'SET_STYLE_TEMPLATES',
							payload: nextTemplates,
							change,
						})
					}
					renderFields={(template, updateTemplate) => (
						<TemplateFields
							data={template}
							fonts={fonts}
							colors={colors}
							onChange={updateTemplate}
						/>
					)}
				/>
			</section>
		</div>
	);
}
