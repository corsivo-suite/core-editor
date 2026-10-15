import { useState, useEffect, useMemo, useId } from '@wordpress/element';
import { Button, TextControl } from '@wordpress/components';
import { useConfig } from '../../store';
import parseGoogleFontsUrl from './parseGoogleFontsUrl';

function sanitizeSlug(str, trim = true) {
	let slug = str
		.toLowerCase()
		.replace(/[^a-z0-9-]/g, '-')
		.replace(/-+/g, '-');
	if (trim) slug = slug.replace(/^-|-$/g, '');
	return slug;
}

function isLoadableGoogleFontsUrl(rawUrl) {
	try {
		const parsed = new URL(rawUrl);
		return (
			parsed.hostname === 'fonts.googleapis.com' &&
			parsed.searchParams.getAll('family').some((family) => family.trim() !== '')
		);
	} catch (e) {
		return false;
	}
}

/**
 * Single Google Font family as a collapsible sub-accordion.
 */
function GoogleFontRow({ font, onChange }) {
	const [open, setOpen] = useState(false);
	const panelId = useId();
	return (
		<div className={`ts-font-editor${open ? ' ts-font-editor--expanded' : ''}`}>
			<div className="ts-font-editor__header">
				<button
					type="button"
					className="ts-font-editor__trigger"
					aria-expanded={open}
					aria-controls={panelId}
					onClick={() => setOpen(!open)}
				>
					<span className="ts-font-editor__summary">
						<strong className="ts-font-editor__name">{font.family}</strong>
						{font.weights && (
							<span className="ts-font-editor__badge">{font.weights}</span>
						)}
						{font.italic && (
							<span className="ts-font-editor__badge">italic</span>
						)}
						{font.varname && (
							<code className="ts-font-editor__family">{font.varname}</code>
						)}
					</span>
					<span className="ts-font-editor__toggle" aria-hidden="true">
						{open ? '\u25B2' : '\u25BC'}
					</span>
				</button>
			</div>
			<div className="ts-font-editor__body" id={panelId} hidden={!open}>
				<div className="ts-font-editor__row">
					<TextControl
						__next40pxDefaultSize
						label="Font Family"
						value={font.family}
						disabled
						__nextHasNoMarginBottom
					/>
					<TextControl
						__next40pxDefaultSize
						label="Weights"
						value={font.weights || '—'}
						disabled
						__nextHasNoMarginBottom
					/>
					<TextControl
						__next40pxDefaultSize
						label="Italic"
						value={font.italic ? 'Yes' : 'No'}
						disabled
						__nextHasNoMarginBottom
					/>
				</div>
				<div className="ts-font-editor__row">
					<TextControl
						__next40pxDefaultSize
						label="Variable name"
						value={font.varname || ''}
						onChange={(v) => onChange({ ...font, varname: sanitizeSlug(v, false) })}
						onBlur={() =>
							onChange({ ...font, varname: sanitizeSlug(font.varname || '') })
						}
						placeholder={sanitizeSlug(font.family)}
						__nextHasNoMarginBottom
					/>
				</div>
				{font.varname && (
					<code style={{ display: 'block', padding: '0.25rem 0', opacity: 0.7 }}>
						--wp--preset--font-family--{font.varname}
					</code>
				)}
			</div>
		</div>
	);
}

/**
 * Single URL entry: URL field on top, font accordions below.
 */
function GoogleFontEntry({ entry, onUpdate, onRemove }) {
	const updateUrl = (newUrl) => {
		const parsed = parseGoogleFontsUrl(newUrl);
		const mergedFonts = parsed.map((pf) => {
			const existing = entry.fonts.find((ef) => ef.family === pf.family);
			return existing ? { ...pf, varname: existing.varname } : pf;
		});
		onUpdate({ url: newUrl, fonts: mergedFonts });
	};

	const updateFont = (fontIndex, updatedFont) => {
		const nextFonts = [...entry.fonts];
		nextFonts[fontIndex] = updatedFont;
		onUpdate({ ...entry, fonts: nextFonts });
	};

	return (
		<div className="ts-gfont-entry">
			<div className="ts-google-fonts__item">
				<TextControl
					__next40pxDefaultSize
					value={entry.url}
					onChange={updateUrl}
					placeholder="https://fonts.googleapis.com/css2?family=..."
					__nextHasNoMarginBottom
				/>
				<Button isDestructive variant="tertiary" onClick={onRemove} isSmall aria-label="Remove Google Fonts URL">
					&times;
				</Button>
			</div>

			{entry.fonts.length === 0 && entry.url && (
				<p className="description" style={{ marginTop: '0.5rem' }}>
					No font families detected. Make sure the URL is a valid Google Fonts URL.
				</p>
			)}

			{entry.fonts.map((font, fi) => (
				<GoogleFontRow
					key={fi}
					font={font}
					onChange={(updated) => updateFont(fi, updated)}
				/>
			))}
		</div>
	);
}

export default function GoogleFontsImport() {
	const { state, dispatch } = useConfig();
	const entries = state.customFonts || [];
	const [newUrl, setNewUrl] = useState('');

	const previewUrlsKey = useMemo(() => {
		return entries
			.map((entry) => (entry.url || '').replace(/["']/g, ''))
			.filter(isLoadableGoogleFontsUrl)
			.join('\n');
	}, [entries]);

	// Inject <link> tags for Google Fonts preview
	useEffect(() => {
		const links = previewUrlsKey
			.split('\n')
			.filter(Boolean)
			.map((url) => {
				const link = document.createElement('link');
				link.rel = 'stylesheet';
				link.href = url;
				link.setAttribute('data-ts-gfont', '');
				document.head.appendChild(link);
				return link;
			});
		return () => {
			links.forEach((link) => link.remove());
		};
	}, [previewUrlsKey]);

	const setEntries = (next) => {
		dispatch({ type: 'SET_CUSTOM_FONTS', payload: next });
	};

	const addUrl = () => {
		if (!newUrl.trim()) return;
		const parsed = parseGoogleFontsUrl(newUrl.trim());
		setEntries([...entries, { url: newUrl.trim(), fonts: parsed }]);
		setNewUrl('');
	};

	const updateEntry = (index, updated) => {
		const next = [...entries];
		next[index] = updated;
		setEntries(next);
	};

	const removeEntry = (index) => {
		setEntries(entries.filter((_, i) => i !== index));
	};

	return (
		<div className="ts-google-fonts">
			<h3>Google Fonts Import</h3>
			<p className="description">
				Add Google Fonts URLs. Font families are auto-detected. Set a{' '}
				<strong>variable name</strong> to register a CSS custom property (
				<code>--wp--preset--font-family--name</code>).
			</p>

			{entries.map((entry, index) => (
				<GoogleFontEntry
					key={index}
					entry={entry}
					onUpdate={(updated) => updateEntry(index, updated)}
					onRemove={() => removeEntry(index)}
				/>
			))}

			<div className="ts-google-fonts__add">
				<TextControl
					__next40pxDefaultSize
					value={newUrl}
					onChange={setNewUrl}
					placeholder="https://fonts.googleapis.com/css2?family=..."
					__nextHasNoMarginBottom
				/>
				<Button variant="secondary" onClick={addUrl} isSmall disabled={!newUrl.trim()}>
					Add URL
				</Button>
			</div>
		</div>
	);
}
