import {
	useState,
	useMemo,
	useEffect,
	useRef,
	useCallback,
} from '@wordpress/element';
import {
	Button,
	TextControl,
	CheckboxControl,
	ToggleControl,
	SelectControl,
} from '@wordpress/components';
import { useConfig } from '../../store';
import BreakpointOverrides from '../Typography/BreakpointOverrides';
import FontPreview from '../Typography/FontPreview';

/* ------------------------------------------------------------------ */
/*  Core Components definitions                                        */
/* ------------------------------------------------------------------ */

const ELEMENT_DEFS = {
	/* button: {
		label: 'Button',
		selector: 'button, .wp-element-button',
		pseudoStates: [':hover', ':focus'],
		groups: ['color', 'typography', 'border', 'spacing', 'shadow'],
	},
	caption: {
		label: 'Caption',
		selector: 'figcaption',
		pseudoStates: [],
		groups: ['color', 'typography'],
	},
	cite: {
		label: 'Cite',
		selector: 'cite',
		pseudoStates: [],
		groups: ['color', 'typography'],
	}, */
};

const BLOCKS_REGISTRY = [
	{ key: 'core/button', label: 'Button Block' },
	{ key: 'core/image', label: 'Image' },
	{ key: 'core/quote', label: 'Quote' },
	{ key: 'core/code', label: 'Code' },
	{ key: 'core/separator', label: 'Separator' },
	{ key: 'core/table', label: 'Table' },
	{ key: 'core/navigation', label: 'Navigation' },
	{ key: 'core/search', label: 'Search' },
	{ key: 'core/pullquote', label: 'Pullquote' },
	{ key: 'core/list', label: 'List' },
	{ key: 'core/group', label: 'Group' },
	{ key: 'core/columns', label: 'Columns' },
];

const CSS_TO_THEME_JSON = {
	'background-color': 'color',
	background: 'color',
	color: 'color',
	'font-family': 'typography',
	'font-size': 'typography',
	'font-weight': 'typography',
	'font-style': 'typography',
	'line-height': 'typography',
	'letter-spacing': 'typography',
	'text-transform': 'typography',
	'text-decoration': 'typography',
	'border-radius': 'border',
	'border-style': 'border',
	'border-width': 'border',
	'border-color': 'border',
	border: 'border',
	padding: 'spacing',
	'padding-top': 'spacing',
	'padding-right': 'spacing',
	'padding-bottom': 'spacing',
	'padding-left': 'spacing',
	margin: 'spacing',
	'margin-top': 'spacing',
	'margin-right': 'spacing',
	'margin-bottom': 'spacing',
	'margin-left': 'spacing',
	'box-shadow': 'shadow',
};

/* ------------------------------------------------------------------ */
/*  Blocks with inner elements (CSS targets child, not wrapper)        */
/* ------------------------------------------------------------------ */

const INNER_ELEMENT_MAP = {
	'core/button': '.wp-element-button',
};

/* ------------------------------------------------------------------ */
/*  Helpers                                                            */
/* ------------------------------------------------------------------ */

function createUid() {
	return typeof crypto !== 'undefined' && crypto.randomUUID
		? crypto.randomUUID()
		: 'uid-' + Math.random().toString(36).slice(2) + Date.now().toString(36);
}

function sanitizeSlug(str, trim = true) {
	let slug = str
		.toLowerCase()
		.replace(/[^a-z0-9-]/g, '-')
		.replace(/-+/g, '-');
	if (trim) slug = slug.replace(/^-|-$/g, '');
	return slug;
}

function blockToClass(blockName) {
	return 'wp-block-' + blockName.replace('core/', '');
}

function validateCss(css, elementTarget) {
	if (!css || !elementTarget || !ELEMENT_DEFS[elementTarget]) return [];
	const groups = ELEMENT_DEFS[elementTarget].groups;
	const warnings = [];
	const seen = new Set();
	const regex = /([a-z-]+)\s*:/gi;
	let match;
	while ((match = regex.exec(css)) !== null) {
		const prop = match[1].toLowerCase().trim();
		if (seen.has(prop)) continue;
		seen.add(prop);
		const group = CSS_TO_THEME_JSON[prop];
		if (!group) {
			warnings.push(`"${prop}" has no theme.json mapping`);
		} else if (!groups.includes(group)) {
			warnings.push(
				`"${prop}" (${group}) not supported for ${ELEMENT_DEFS[elementTarget].label}`
			);
		}
	}
	return warnings;
}

/* ------------------------------------------------------------------ */
/*  Auto-resize textarea                                               */
/* ------------------------------------------------------------------ */

function AutoTextarea({ value, onChange, className, ...props }) {
	const ref = useRef(null);

	const resize = useCallback(() => {
		const el = ref.current;
		if (!el) return;
		el.style.height = 'auto';
		el.style.height = el.scrollHeight + 'px';
	}, []);

	useEffect(() => {
		resize();
	}, [value, resize]);

	return (
		<textarea
			ref={ref}
			className={className}
			value={value}
			onChange={onChange}
			spellCheck={false}
			{...props}
		/>
	);
}

/* ------------------------------------------------------------------ */
/*  CSS Editor block (shared)                                          */
/* ------------------------------------------------------------------ */

function CssEditor({ selector, css, onCssChange, pseudoStates, item, onChange }) {
	const [openPseudos, setOpenPseudos] = useState(() =>
		pseudoStates.filter((ps) => item[ps])
	);

	const addablePseudos = pseudoStates.filter(
		(ps) => !openPseudos.includes(ps)
	);

	return (
		<div className="ts-font-editor__css-block">
			<div className="ts-font-editor__css-context">
				<code className="ts-font-editor__css-selector">
					{selector}
				</code>
				<span className="ts-font-editor__css-brace">
					{' {'}
				</span>
				<AutoTextarea
					className="ts-font-editor__css-textarea"
					value={css}
					onChange={(e) => onCssChange(e.target.value)}
					rows={1}
				/>
				<span className="ts-font-editor__css-brace">
					{'}'}
				</span>
			</div>

			{openPseudos.map((pseudo) => (
				<div
					key={pseudo}
					className="ts-font-editor__css-context ts-font-editor__css-context--bp"
				>
					<code className="ts-font-editor__css-selector ts-font-editor__css-selector--media">
						{selector}
						{pseudo}
					</code>
					<span className="ts-font-editor__css-brace">
						{' {'}
					</span>
					<AutoTextarea
						className="ts-font-editor__css-textarea"
						value={item[pseudo] || ''}
						onChange={(e) =>
							onChange({ [pseudo]: e.target.value })
						}
						rows={1}
					/>
					<span className="ts-font-editor__css-brace">
						{'}'}
					</span>
				</div>
			))}

			{addablePseudos.length > 0 && (
				<div className="ts-cc-pseudo-buttons">
					{addablePseudos.map((ps) => (
						<Button
							key={ps}
							variant="tertiary"
							className="ts-cc-pseudo-buttons__btn"
							onClick={() =>
								setOpenPseudos((prev) => [
									...prev,
									ps,
								])
							}
						>
							+ Add {ps}
						</Button>
					))}
				</div>
			)}
		</div>
	);
}

/* ------------------------------------------------------------------ */
/*  FontCard (typography styles)                                       */
/* ------------------------------------------------------------------ */

function FontCard({ font, expanded, onToggle, onChange, onRemove, onDuplicate }) {
	const { state } = useConfig();

	const update = useCallback(
		(field, value) => {
			onChange({ ...font, [field]: value });
		},
		[font, onChange]
	);

	// Parse scope
	const hasHeading = (() => {
		if (font.scope === true || font.scope === undefined) return true;
		if (font.scope === 'heading') return true;
		if (Array.isArray(font.scope) && font.scope.includes('heading'))
			return true;
		return false;
	})();

	const hasParagraph = (() => {
		if (font.scope === true || font.scope === undefined) return true;
		if (font.scope === 'paragraph') return true;
		if (Array.isArray(font.scope) && font.scope.includes('paragraph'))
			return true;
		return false;
	})();

	const handleScopeChange = (type, checked) => {
		let newH = type === 'heading' ? checked : hasHeading;
		let newP = type === 'paragraph' ? checked : hasParagraph;
		if (newH && newP) update('scope', true);
		else if (newH) update('scope', 'heading');
		else if (newP) update('scope', 'paragraph');
		else update('scope', true);
	};

	const getScopeLabel = () => {
		if (hasHeading && hasParagraph) return 'H + P';
		if (hasHeading) return 'H';
		if (hasParagraph) return 'P';
		return 'H + P';
	};

	const bpCount = font.breakpoints
		? Object.keys(font.breakpoints).length
		: 0;

	// Font picker
	const installedFontOptions = useMemo(() => {
		const options = [];
		if (state.installFonts) {
			state.installFonts.forEach((f) => {
				if (f['font-family']) {
					options.push({
						label: f['font-family'],
						value: f['font-family'],
					});
				}
			});
		}
		return options;
	}, [state.installFonts]);

	const [showFontPicker, setShowFontPicker] = useState(false);

	const handleFontPick = (family) => {
		if (!family) return;
		const declaration = `font-family: "${family}", sans-serif;`;
		const current = font.css || '';
		if (/font-family\s*:/.test(current)) {
			const updated = current.replace(
				/font-family\s*:[^;]*;?/,
				declaration
			);
			update('css', updated);
		} else {
			update('css', declaration + (current ? '\n' + current : ''));
		}
		setShowFontPicker(false);
	};

	// Extract summary
	const cssSummary = useMemo(() => {
		const css = font.css || '';
		const familyMatch = css.match(/font-family\s*:\s*([^;]+)/);
		const sizeMatch = css.match(/font-size\s*:\s*([^;]+)/);
		return {
			family: familyMatch ? familyMatch[1].trim() : '',
			size: sizeMatch ? sizeMatch[1].trim() : '',
		};
	}, [font.css]);

	const slug = font.slug || 'slug';

	return (
		<div
			className={`ts-font-editor ${expanded ? 'ts-font-editor--expanded' : ''}`}
		>
			<div className="ts-font-editor__header" onClick={onToggle}>
				<div className="ts-font-editor__summary">
					<strong className="ts-font-editor__name">
						{font.name || font.slug}
					</strong>
					{cssSummary.family && (
						<code className="ts-font-editor__family">
							{cssSummary.family}
						</code>
					)}
					{cssSummary.size && (
						<span className="ts-font-editor__size">
							{cssSummary.size}
						</span>
					)}
					<span className="ts-font-editor__scope">
						{getScopeLabel()}
					</span>
					{bpCount > 0 && (
						<span className="ts-font-editor__badge">
							{bpCount} bp
						</span>
					)}
				</div>
				<div>
					<button
						className="ts-font-editor__delete"
						onClick={(e) => {
							e.stopPropagation();
							onRemove();
						}}
						title="Remove"
					>
						&#10005;
					</button>
					<span className="ts-font-editor__toggle">
						{expanded ? '▲' : '▼'}
					</span>
				</div>
			</div>

			{expanded && (
				<div className="ts-font-editor__body">
					<div className="ts-font-editor__fields">
						<div className="ts-font-editor__row">
							<TextControl
								__next40pxDefaultSize
								label="Name"
								value={font.name || ''}
								onChange={(v) => update('name', v)}
								__nextHasNoMarginBottom
							/>
							<TextControl
								__next40pxDefaultSize
								label="Slug"
								value={font.slug || ''}
								onChange={(v) =>
									update('slug', sanitizeSlug(v, false))
								}
								onBlur={() =>
									update(
										'slug',
										sanitizeSlug(font.slug || '')
									)
								}
								__nextHasNoMarginBottom
							/>
						</div>

						<div className="ts-font-editor__row ts-font-editor__options">
							<div className="ts-font-editor__scope-checks">
								<span className="ts-font-editor__label">
									Scope
								</span>
								<CheckboxControl
									label="Heading"
									checked={hasHeading}
									onChange={(v) =>
										handleScopeChange('heading', v)
									}
									__nextHasNoMarginBottom
								/>
								<CheckboxControl
									label="Paragraph"
									checked={hasParagraph}
									onChange={(v) =>
										handleScopeChange('paragraph', v)
									}
									__nextHasNoMarginBottom
								/>
							</div>
							<ToggleControl
								label="Hide in editor"
								checked={!!font.hide}
								onChange={(v) => update('hide', v)}
								__nextHasNoMarginBottom
							/>
						</div>

						{/* CSS code block */}
						<div className="ts-font-editor__css-block">
							{installedFontOptions.length > 0 && (
								<div className="ts-font-editor__font-picker">
									{showFontPicker ? (
										<div className="ts-font-editor__font-picker-row">
											<SelectControl
												__next40pxDefaultSize
												value=""
												options={[
													{
														label: '— Pick a font —',
														value: '',
													},
													...installedFontOptions,
												]}
												onChange={handleFontPick}
												__nextHasNoMarginBottom
											/>
											<Button
												variant="tertiary"
												isSmall
												onClick={() =>
													setShowFontPicker(false)
												}
											>
												Cancel
											</Button>
										</div>
									) : (
										<Button
											variant="tertiary"
											isSmall
											onClick={() =>
												setShowFontPicker(true)
											}
										>
											Pick installed font
										</Button>
									)}
								</div>
							)}
							<div className="ts-font-editor__css-context">
								<code className="ts-font-editor__css-selector">
									.{slug}
									{', '}
									.is-style-font-{slug}
								</code>
								<span className="ts-font-editor__css-brace">
									{' {'}
								</span>
								<AutoTextarea
									className="ts-font-editor__css-textarea"
									value={font.css || ''}
									onChange={(e) =>
										update('css', e.target.value)
									}
									rows={1}
								/>
								<span className="ts-font-editor__css-brace">
									{'}'}
								</span>
							</div>
						</div>
					</div>

					<BreakpointOverrides
						fontSlug={font.slug}
						breakpoints={font.breakpoints || {}}
						onChange={(bp) => update('breakpoints', bp)}
					/>

					<FontPreview font={font} />

					<div className="ts-font-editor__actions">
						<Button
							variant="tertiary"
							onClick={onDuplicate}
						>
							Duplicate
						</Button>
						<Button
							isDestructive
							variant="tertiary"
							onClick={onRemove}
						>
							Remove font
						</Button>
					</div>
				</div>
			)}
		</div>
	);
}

/* ------------------------------------------------------------------ */
/*  Custom StyleCard (elements + custom block styles)                  */
/* ------------------------------------------------------------------ */

function CustomCard({
	item,
	expanded,
	onToggle,
	onChange,
	onTargetChange,
	onRemove,
	onDuplicate,
	existingElements,
	inheritOptions,
}) {
	const isElement = item._src === 'element';
	const pseudoStates = isElement
		? ELEMENT_DEFS[item.target]?.pseudoStates || []
		: [':hover'];

	let selector;
	if (isElement) {
		selector = ELEMENT_DEFS[item.target]?.selector || item.target;
	} else {
		const wrapper = `.${blockToClass(item.target || 'core/block')}.is-style-${item.slug || 'slug'}`;
		const inner = INNER_ELEMENT_MAP[item.target];
		selector = inner ? `${wrapper} ${inner}` : wrapper;
	}

	const allCss = [item.css || '']
		.concat(pseudoStates.map((ps) => item[ps] || ''))
		.join('\n');
	const warnings = isElement ? validateCss(allCss, item.target) : [];

	const headerName = isElement
		? ELEMENT_DEFS[item.target]?.label || item.target
		: item.name || item.slug || 'Untitled';
	const headerSub =
		!isElement &&
		(BLOCKS_REGISTRY.find((b) => b.key === item.target)?.label ||
			item.target);

	// Inheritance label for header badge
	const inheritsLabel = useMemo(() => {
		if (!item.inherits) return null;
		const opt = (inheritOptions || []).find(
			(o) => o.value === item.inherits
		);
		return opt ? opt.label : item.inherits;
	}, [item.inherits, inheritOptions]);

	const targetVal = isElement
		? 'element:' + item.target
		: 'block:' + item.target;

	const targetOptions = useMemo(() => {
		const opts = [{ label: '— Select target —', value: '' }];
		Object.entries(ELEMENT_DEFS).forEach(([key, def]) => {
			if (
				'element:' + key === targetVal ||
				!existingElements.includes(key)
			) {
				opts.push({
					label: def.label + ' (element)',
					value: 'element:' + key,
				});
			}
		});
		BLOCKS_REGISTRY.forEach((b) => {
			opts.push({
				label: b.label + ' (block style)',
				value: 'block:' + b.key,
			});
		});
		return opts;
	}, [targetVal, existingElements]);

	return (
		<div
			className={`ts-font-editor${expanded ? ' ts-font-editor--expanded' : ''}`}
		>
			<div className="ts-font-editor__header" onClick={onToggle}>
				<div className="ts-font-editor__summary">
					<strong className="ts-font-editor__name">
						{headerName}
					</strong>
					{headerSub && (
						<code className="ts-font-editor__family">
							{headerSub}
						</code>
					)}
					{isElement && (
						<span className="ts-font-editor__badge">
							element
						</span>
					)}
					{inheritsLabel && (
						<span className="ts-font-editor__badge">
							&#8592; {inheritsLabel}
						</span>
					)}
					{warnings.length > 0 && (
						<span className="ts-font-editor__badge ts-font-editor__badge--warn">
							{warnings.length}
							{warnings.length > 1 ? ' warnings' : ' warning'}
						</span>
					)}
				</div>
				<div>
					<button
						className="ts-font-editor__delete"
						onClick={(e) => {
							e.stopPropagation();
							onRemove();
						}}
						title="Remove"
					>
						&#10005;
					</button>
					<span className="ts-font-editor__toggle">
						{expanded ? '▲' : '▼'}
					</span>
				</div>
			</div>

			{expanded && (
				<div className="ts-font-editor__body">
					<div className="ts-cc-row ts-cc-row--target">
						<SelectControl
							__next40pxDefaultSize
							label="Target"
							value={targetVal}
							options={targetOptions}
							onChange={onTargetChange}
							__nextHasNoMarginBottom
						/>
						{!isElement && (
							<>
								<CheckboxControl
									label="Default"
									checked={!!item.isDefault}
									onChange={(v) =>
										onChange({ isDefault: v })
									}
									__nextHasNoMarginBottom
								/>
								<CheckboxControl
									label="Hide in editor"
									checked={!!item.hideInEditor}
									onChange={(v) =>
										onChange({ hideInEditor: v })
									}
									__nextHasNoMarginBottom
								/>
							</>
						)}
					</div>

					{!isElement && (
						<div className="ts-cc-row">
							<TextControl
								__next40pxDefaultSize
								label="Name"
								value={item.name || ''}
								onChange={(v) => onChange({ name: v })}
								__nextHasNoMarginBottom
							/>
							<TextControl
								__next40pxDefaultSize
								label="Slug"
								value={item.slug || ''}
								onChange={(v) =>
									onChange({ slug: sanitizeSlug(v, false) })
								}
								onBlur={(e) =>
									onChange({ slug: sanitizeSlug(e.target.value) })
								}
								__nextHasNoMarginBottom
							/>
						</div>
					)}

					{!isElement && inheritOptions && inheritOptions.length > 1 && (
						<div className="ts-cc-row">
							<SelectControl
								__next40pxDefaultSize
								label="Base style (inherit CSS)"
								value={item.inherits || ''}
								options={inheritOptions}
								onChange={(v) =>
									onChange({ inherits: v || '' })
								}
								__nextHasNoMarginBottom
							/>
						</div>
					)}

					<CssEditor
						selector={selector}
						css={item.css || ''}
						onCssChange={(v) => onChange({ css: v })}
						pseudoStates={pseudoStates}
						item={item}
						onChange={onChange}
					/>

					{warnings.length > 0 && (
						<div className="ts-cc-warnings">
							{warnings.map((w, i) => (
								<div
									key={i}
									className="ts-cc-warnings__item"
								>
									&#9888; {w}
								</div>
							))}
						</div>
					)}

					<div className="ts-font-editor__actions">
						{!isElement && (
							<Button
								variant="tertiary"
								onClick={onDuplicate}
							>
								Duplicate
							</Button>
						)}
						<Button
							isDestructive
							variant="tertiary"
							onClick={onRemove}
						>
							Remove
						</Button>
					</div>
				</div>
			)}
		</div>
	);
}

/* ------------------------------------------------------------------ */
/*  Core StyleCard (registered block styles)                           */
/* ------------------------------------------------------------------ */

function CoreCard({ item, expanded, onToggle, onChange }) {
	const wrapper = `.${blockToClass(item.target)}.is-style-${item.slug}`;
	const inner = INNER_ELEMENT_MAP[item.target];
	const selector = inner ? `${wrapper} ${inner}` : wrapper;
	const blockLabel =
		BLOCKS_REGISTRY.find((b) => b.key === item.target)?.label ||
		item.target;

	return (
		<div
			className={`ts-font-editor${expanded ? ' ts-font-editor--expanded' : ''}${item.hideInEditor ? ' ts-font-editor--hidden' : ''}`}
		>
			<div className="ts-font-editor__header" onClick={onToggle}>
				<div className="ts-font-editor__summary">
					<strong className="ts-font-editor__name">
						{item.name || item.slug}
					</strong>
					<code className="ts-font-editor__family">
						{blockLabel}
					</code>
					{item.isDefault && (
						<span className="ts-font-editor__badge">
							Default
						</span>
					)}
					{item.hideInEditor && (
						<span className="ts-font-editor__badge ts-font-editor__badge--destructive">
							Hidden
						</span>
					)}
				</div>
				<div>
					<button
						className={`ts-font-editor__hide-btn${item.hideInEditor ? ' ts-font-editor__hide-btn--active' : ''}`}
						onClick={(e) => {
							e.stopPropagation();
							onChange({ hideInEditor: !item.hideInEditor });
						}}
						title={item.hideInEditor ? 'Show in editor' : 'Hide from editor'}
					>
						{item.hideInEditor ? 'Show' : 'Hide'}
					</button>
					<span className="ts-font-editor__toggle">
						{expanded ? '▲' : '▼'}
					</span>
				</div>
			</div>

			{expanded && (
				<div className="ts-font-editor__body">
					<div className="ts-cc-row ts-cc-row--target">
						<CheckboxControl
							label="Default"
							checked={!!item.isDefault}
							onChange={(v) => onChange({ isDefault: v })}
							__nextHasNoMarginBottom
						/>
						<CheckboxControl
							label="Hide in editor"
							checked={!!item.hideInEditor}
							onChange={(v) => onChange({ hideInEditor: v })}
							__nextHasNoMarginBottom
						/>
					</div>

					<CssEditor
						selector={selector}
						css={item.css || ''}
						onCssChange={(v) => onChange({ css: v })}
						pseudoStates={[':hover']}
						item={item}
						onChange={onChange}
					/>
				</div>
			)}
		</div>
	);
}

/* ================================================================== */
/*  Main StylesTab component                                           */
/* ================================================================== */

export default function StylesTab() {
	const { state, dispatch } = useConfig();

	/* ---------- Typography state ---------- */
	const fonts = state.fonts;

	useEffect(() => {
		if (!fonts || fonts.length === 0) return;
		const needsUid = fonts.some((f) => !f._uid);
		if (!needsUid) return;
		const next = fonts.map((f) => (f._uid ? f : { ...f, _uid: createUid() }));
		dispatch({ type: 'SET_FONTS', payload: next, silent: true });
	}, [fonts]);

	/* ---------- Core Components state ---------- */
	const cc = state.coreComponents || {};
	const elements = cc.elements || {};
	const blockStyles = cc.blockStyles || [];
	const registeredBlockStyles =
		window.corsivoThemeEditor?.registeredBlockStyles || {};

	useEffect(() => {
		if (!blockStyles.some((bs) => !bs._uid)) return;
		dispatch({
			type: 'SET_CORE_COMPONENTS',
			payload: {
				...cc,
				blockStyles: blockStyles.map((bs) =>
					bs._uid ? bs : { ...bs, _uid: createUid() }
				),
			},
			silent: true,
		});
	}, [blockStyles]);

	/* ---------- Shared accordion state ---------- */
	const [expandedKey, setExpandedKey] = useState(null);
	const [newCardOpen, setNewCardOpen] = useState(false);
	const expandAfterRef = useRef(null);

	/* ---------- Typography helpers ---------- */

	const updateFont = (index, updatedFont) => {
		const next = [...fonts];
		next[index] = updatedFont;
		dispatch({ type: 'SET_FONTS', payload: next });
	};

	const removeFont = (index) => {
		const next = fonts.filter((_, i) => i !== index);
		dispatch({ type: 'SET_FONTS', payload: next });
		setExpandedKey(null);
	};

	const duplicateFont = (index) => {
		const source = fonts[index];
		const existingSlugs = new Set(fonts.map((f) => f.slug));
		let slug = source.slug + '-copy';
		let copySuffix = 2;
		while (existingSlugs.has(slug)) {
			slug = source.slug + '-copy-' + copySuffix;
			copySuffix += 1;
		}
		const copy = {
			...JSON.parse(JSON.stringify(source)),
			_uid: createUid(),
			slug,
			name: (source.name || source.slug) + ' (copy)',
			default: false,
		};
		const next = [...fonts];
		next.splice(index + 1, 0, copy);
		dispatch({ type: 'SET_FONTS', payload: next });
		setExpandedKey('font:' + copy._uid);
	};

	const addFont = () => {
		const existingSlugs = new Set(fonts.map((f) => f.slug));
		let fontNumber = 1;
		while (existingSlugs.has(`font-${fontNumber}`)) fontNumber += 1;
		const newFont = {
			_uid: createUid(),
			slug: `font-${fontNumber}`,
			name: `Font ${fontNumber}`,
			css: 'font-family: inherit;\nfont-size: 1rem;\nline-height: 1.5;\nfont-weight: normal;',
			scope: true,
			default: false,
		};
		dispatch({ type: 'SET_FONTS', payload: [...fonts, newFont] });
		setExpandedKey('font:' + newFont._uid);
	};

	/* ---------- Build Core Components display lists ---------- */

	const { customList, coreList } = useMemo(() => {
		const custom = [];
		const core = [];

		for (const [key, data] of Object.entries(elements)) {
			if (ELEMENT_DEFS[key]) {
				custom.push({
					_src: 'element',
					_elKey: key,
					_key: 'el:' + key,
					target: key,
					css: data?.css || '',
					':hover': data?.[':hover'] || '',
					':focus': data?.[':focus'] || '',
				});
			}
		}

		const processed = new Set();
		for (const [blockName, styles] of Object.entries(
			registeredBlockStyles
		)) {
			if (
				blockName === 'core/paragraph' ||
				blockName === 'core/heading'
			)
				continue;
			for (const rs of styles) {
				const cKey = blockName + '::' + rs.name;
				processed.add(cKey);
				const saved = blockStyles.find(
					(bs) =>
						bs.target === blockName && bs.slug === rs.name
				);
				core.push({
					_src: 'block',
					_key: 'core:' + cKey,
					target: blockName,
					name: saved?.name || rs.label || rs.name,
					slug: rs.name,
					isDefault: saved
						? !!saved.isDefault
						: !!rs.isDefault,
					hideInEditor: saved
						? !!saved.hideInEditor
						: false,
					css: saved?.css || '',
					':hover': saved?.[':hover'] || '',
					fromCore: true,
				});
			}
		}

		blockStyles.forEach((bs, bsIdx) => {
			const cKey = bs.target + '::' + bs.slug;
			if (!processed.has(cKey)) {
				custom.push({
					_src: 'block',
					_key: 'bs:' + (bs._uid || cKey),
					_bsIndex: bsIdx,
					...bs,
					fromCore: false,
				});
			}
		});

		return { customList: custom, coreList: core };
	}, [elements, blockStyles, registeredBlockStyles]);

	/* ---------- Auto-expand after add ---------- */

	useEffect(() => {
		if (expandAfterRef.current) {
			const t = expandAfterRef.current;
			const all = [...customList, ...coreList];
			const found = all.find((item) => {
				if (t.src === 'element')
					return item._src === 'element' && item.target === t.key;
				return (
					item._src === 'block' &&
					item.target === t.target &&
					item.slug === t.slug
				);
			});
			if (found) setExpandedKey(found._key);
			expandAfterRef.current = null;
		}
	}, [customList, coreList]);

	const existingElements = Object.keys(elements);

	/* ---------- Core Components store helpers ---------- */

	const setElements = (next) =>
		dispatch({
			type: 'SET_CORE_COMPONENTS',
			payload: { ...cc, elements: next },
		});

	const setBlockStyles = (next) =>
		dispatch({
			type: 'SET_CORE_COMPONENTS',
			payload: { ...cc, blockStyles: next },
		});

	const handleCustomChange = (item, patch) => {
		if (item._src === 'element') {
			const cur = elements[item._elKey] || {};
			setElements({
				...elements,
				[item._elKey]: { ...cur, ...patch },
			});
		} else {
			const merged = { ...item, ...patch };
			const { _src, _key, _bsIndex, ...cleanData } = merged;

			if (
				typeof item._bsIndex === 'number' &&
				blockStyles[item._bsIndex]
			) {
				const next = [...blockStyles];
				next[item._bsIndex] = cleanData;
				setBlockStyles(next);
			} else {
				setBlockStyles([...blockStyles, cleanData]);
			}
		}
	};

	const handleCoreChange = (item, patch) => {
		const bsIdx = blockStyles.findIndex(
			(bs) => bs.target === item.target && bs.slug === item.slug
		);
		const merged = { ...item, ...patch };
		const { _src, _key, ...cleanData } = merged;

		if (bsIdx >= 0) {
			const next = [...blockStyles];
			next[bsIdx] = cleanData;
			setBlockStyles(next);
		} else {
			setBlockStyles([...blockStyles, cleanData]);
		}
	};

	const handleTargetChange = (item, newVal) => {
		if (!newVal) return;
		const isNewElement = newVal.startsWith('element:');
		const newKey = newVal.replace(/^(element|block):/, '');
		const wasElement = item._src === 'element';
		const css = item.css || '';
		const hover = item[':hover'] || '';
		const focus = item[':focus'] || '';

		if (wasElement && isNewElement) {
			const next = { ...elements };
			delete next[item._elKey];
			next[newKey] = { css, ':hover': hover, ':focus': focus };
			setElements(next);
			expandAfterRef.current = { src: 'element', key: newKey };
		} else if (wasElement && !isNewElement) {
			const nextEl = { ...elements };
			delete nextEl[item._elKey];
			const slug = 'new-style-' + Date.now();
			dispatch({
				type: 'SET_CORE_COMPONENTS',
				payload: {
					...cc,
					elements: nextEl,
					blockStyles: [
						...blockStyles,
						{
							_uid: createUid(),
							target: newKey,
							name: '',
							slug,
							isDefault: false,
							hideInEditor: false,
							css,
							':hover': hover,
							':focus': focus,
							fromCore: false,
						},
					],
				},
			});
			expandAfterRef.current = {
				src: 'block',
				target: newKey,
				slug,
			};
		} else if (!wasElement && isNewElement) {
			const nextBS = blockStyles.filter(
				(_, idx) => idx !== item._bsIndex
			);
			dispatch({
				type: 'SET_CORE_COMPONENTS',
				payload: {
					...cc,
					elements: {
						...elements,
						[newKey]: { css, ':hover': hover, ':focus': focus },
					},
					blockStyles: nextBS,
				},
			});
			expandAfterRef.current = { src: 'element', key: newKey };
		} else {
			handleCustomChange(item, { target: newKey });
		}
	};

	const handleRemove = (item) => {
		if (item._src === 'element') {
			const next = { ...elements };
			delete next[item._elKey];
			setElements(next);
		} else {
			setBlockStyles(
				blockStyles.filter((_, idx) => idx !== item._bsIndex)
			);
		}
		if (expandedKey === item._key) setExpandedKey(null);
	};

	const handleDuplicate = (item) => {
		let slug = item.slug + '-copy';
		let copySuffix = 2;
		while (
			blockStyles.some(
				(bs) => bs.target === item.target && bs.slug === slug
			)
		) {
			slug = item.slug + '-copy-' + copySuffix;
			copySuffix += 1;
		}
		const dup = {
			_uid: createUid(),
			target: item.target,
			name: (item.name || item.slug) + ' (copy)',
			slug,
			isDefault: false,
			hideInEditor: false,
			css: item.css || '',
			':hover': item[':hover'] || '',
			fromCore: false,
		};
		if (item.inherits) dup.inherits = item.inherits;
		setBlockStyles([...blockStyles, dup]);
		expandAfterRef.current = {
			src: 'block',
			target: item.target,
			slug,
		};
	};

	const commitNewCard = (targetVal) => {
		if (!targetVal) return;
		const isElement = targetVal.startsWith('element:');
		const key = targetVal.replace(/^(element|block):/, '');

		if (isElement) {
			setElements({
				...elements,
				[key]: { css: '', ':hover': '', ':focus': '' },
			});
			expandAfterRef.current = { src: 'element', key };
		} else {
			const slug = 'new-style-' + Date.now();
			setBlockStyles([
				...blockStyles,
				{
					_uid: createUid(),
					target: key,
					name: '',
					slug,
					isDefault: false,
					hideInEditor: false,
					css: '',
					':hover': '',
					fromCore: false,
				},
			]);
			expandAfterRef.current = {
				src: 'block',
				target: key,
				slug,
			};
		}
		setNewCardOpen(false);
	};

	const newCardOptions = useMemo(() => {
		const opts = [{ label: '— Select target —', value: '' }];
		Object.entries(ELEMENT_DEFS).forEach(([key, def]) => {
			if (!existingElements.includes(key)) {
				opts.push({
					label: def.label + ' (element)',
					value: 'element:' + key,
				});
			}
		});
		BLOCKS_REGISTRY.forEach((b) => {
			opts.push({
				label: b.label + ' (block style)',
				value: 'block:' + b.key,
			});
		});
		return opts;
	}, [existingElements]);

	/* ---------- Build inherit options ---------- */

	const buildInheritOptions = useCallback(
		(currentItem) => {
			const opts = [{ label: '— None —', value: '' }];

			// Font styles
			fonts.forEach((f) => {
				if (!f.slug) return;
				opts.push({
					label: f.name || f.slug,
					value: 'font:' + f.slug,
				});
			});

			// Block styles (custom only, not the current item)
			blockStyles.forEach((bs) => {
				if (!bs.slug || !bs.target) return;
				// Skip self
				if (
					currentItem &&
					currentItem._src === 'block' &&
					currentItem.target === bs.target &&
					currentItem.slug === bs.slug
				)
					return;
				opts.push({
					label: (bs.name || bs.slug) + ' (' + (bs.target || '') + ')',
					value: 'bs:' + bs.target + '::' + bs.slug,
				});
			});

			return opts;
		},
		[fonts, blockStyles]
	);

	/* ---------- Font showcase data ---------- */

	const fontShowcaseItems = useMemo(() => {
		const items = [];
		const seen = new Set();

		// 1. Google Fonts (extract family names from URLs or parsed objects)
		(state.customFonts || []).forEach((entry) => {
			const urlStr = entry && entry.url ? entry.url : '';
			// Use parsed fonts if available
			const parsedFonts = entry && Array.isArray(entry.fonts) ? entry.fonts : [];
			if (parsedFonts.length > 0) {
				parsedFonts.forEach((f) => {
					if (f.family && !seen.has(f.family.toLowerCase())) {
						seen.add(f.family.toLowerCase());
						items.push({
							name: f.family,
							declaration: `font-family: "${f.family}", sans-serif;`,
							source: 'Google',
						});
					}
				});
			} else {
				const matches = urlStr.match(/family=([^&:]+)/g);
				if (matches) {
					matches.forEach((m) => {
						const name = decodeURIComponent(m.replace('family=', '')).replace(/\+/g, ' ');
						if (!seen.has(name.toLowerCase())) {
							seen.add(name.toLowerCase());
							items.push({
								name,
								declaration: `font-family: "${name}", sans-serif;`,
								source: 'Google',
							});
						}
					});
				}
			}
		});

		// 2. Local installed fonts
		(state.installFonts || []).forEach((f) => {
			const name = f['font-family'];
			if (name && !seen.has(name.toLowerCase())) {
				seen.add(name.toLowerCase());
				items.push({
					name,
					declaration: `font-family: "${name}", sans-serif;`,
					source: 'Local',
				});
			}
		});

		return items;
	}, [state.customFonts, state.installFonts]);

	const [copiedIdx, setCopiedIdx] = useState(null);
	const handleCopyDeclaration = (text, idx) => {
		navigator.clipboard.writeText(text).then(() => {
			setCopiedIdx(idx);
			setTimeout(() => setCopiedIdx(null), 1500);
		});
	};

	/* ================================================================ */
	/*  Render                                                           */
	/* ================================================================ */

	return (
		<div className="ts-styles-tab">
			<h2>Styles</h2>

			{/* ============ TYPOGRAPHY ============ */}
			<h3>Typography</h3>

			{fontShowcaseItems.length > 0 && (
				<div className="ts-font-showcase">
					<h4>Available Fonts</h4>
					<p className="description">
						Click the declaration to copy it.
					</p>
					<div className="ts-font-showcase__list">
						{fontShowcaseItems.map((item, idx) => (
							<div key={idx} className="ts-font-showcase__item">
								<span
									className="ts-font-showcase__preview"
									style={{ fontFamily: `"${item.name}", sans-serif` }}
								>
									{item.name}
								</span>
								<code
									className={`ts-font-showcase__declaration${copiedIdx === idx ? ' ts-font-showcase__declaration--copied' : ''}`}
									onClick={() => handleCopyDeclaration(item.declaration, idx)}
									title="Click to copy"
								>
									{copiedIdx === idx ? 'Copied!' : item.declaration}
								</code>
								<span className="ts-font-showcase__source">
									{item.source}
								</span>
							</div>
						))}
					</div>
				</div>
			)}
			<p className="description">
				Font styles for Heading and Paragraph blocks. Each font generates
				CSS classes (<code>.slug</code>,{' '}
				<code>.is-style-font-slug</code>) and registers as a block style
				variation in the editor.
			</p>

			<div className="ts-typography-tab__fonts">
				{fonts.map((font, index) => (
					<FontCard
						key={font._uid || 'font-idx:' + index}
						font={font}
						expanded={expandedKey === 'font:' + (font._uid || index)}
						onToggle={() => {
							const k = 'font:' + (font._uid || index);
							setExpandedKey(expandedKey === k ? null : k);
						}}
						onChange={(updated) => updateFont(index, updated)}
						onRemove={() => removeFont(index)}
						onDuplicate={() => duplicateFont(index)}
					/>
				))}
			</div>

			<Button
				variant="secondary"
				onClick={addFont}
				className="ts-typography-tab__add"
			>
				+ Add font style
			</Button>

			<hr />

			{/* ============ CUSTOM STYLES ============ */}
			<h3>Custom Styles</h3>
			<p className="description">
				Style WordPress elements and block style variations. Elements
				are parsed into <code>theme.json</code>; block styles register
				CSS class variations.
			</p>

			{customList.length === 0 && !newCardOpen && (
				<p className="description" style={{ fontStyle: 'italic' }}>
					No custom styles yet. Click "Add new style" to create one.
				</p>
			)}

			{customList.map((item) => (
				<CustomCard
					key={item._key}
					item={item}
					expanded={expandedKey === item._key}
					onToggle={() =>
						setExpandedKey(
							expandedKey === item._key ? null : item._key
						)
					}
					onChange={(patch) =>
						handleCustomChange(item, patch)
					}
					onTargetChange={(v) =>
						handleTargetChange(item, v)
					}
					onRemove={() => handleRemove(item)}
					onDuplicate={() => handleDuplicate(item)}
					existingElements={existingElements}
					inheritOptions={buildInheritOptions(item)}
				/>
			))}

			{newCardOpen && (
				<div className="ts-font-editor ts-font-editor--expanded">
					<div
						className="ts-font-editor__header"
						onClick={() => setNewCardOpen(false)}
					>
						<div className="ts-font-editor__summary">
							<strong className="ts-font-editor__name">
								New style
							</strong>
						</div>
						<span className="ts-font-editor__toggle">
							&#10005;
						</span>
					</div>
					<div className="ts-font-editor__body">
						<SelectControl
							__next40pxDefaultSize
							label="Target"
							value=""
							options={newCardOptions}
							onChange={commitNewCard}
							__nextHasNoMarginBottom
						/>
						<p
							className="description"
							style={{ marginTop: '8px' }}
						>
							Select an element or block to start styling.
						</p>
					</div>
				</div>
			)}

			{!newCardOpen && (
				<Button
					variant="secondary"
					onClick={() => {
						setNewCardOpen(true);
						setExpandedKey(null);
					}}
					style={{ marginTop: '0.5rem' }}
				>
					+ Add new style
				</Button>
			)}

			{/* ============ DEFAULT BLOCK STYLES ============ */}
			{coreList.length > 0 && (
				<>
					<h3 style={{ marginTop: '2rem' }}>
						Default Block Styles
					</h3>
					<p className="description">
						Block styles registered by WordPress core and your
						theme. Override their CSS or hide them from the editor.
					</p>

					{coreList.map((item) => (
						<CoreCard
							key={item._key}
							item={item}
							expanded={expandedKey === item._key}
							onToggle={() =>
								setExpandedKey(
									expandedKey === item._key
										? null
										: item._key
								)
							}
							onChange={(patch) =>
								handleCoreChange(item, patch)
							}
						/>
					))}
				</>
			)}
		</div>
	);
}
