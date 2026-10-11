import { useState, useEffect, useMemo } from '@wordpress/element';
import {
	Button,
	TextControl,
	SelectControl,
	Spinner,
	CheckboxControl,
} from '@wordpress/components';
import { useConfig } from '../../store';
import { getThemeFonts, uploadFontFile } from '../../api';
import { scanAllFontFiles } from '../../lib/fontFeatures';
import { initializeInstalledFont, renameInstalledFontWeight } from '../../installedFontAdapter';

function sanitizeSlug(str, trim = true) {
	let slug = str
		.toLowerCase()
		.replace(/[^a-z0-9-]/g, '-')
		.replace(/-+/g, '-');
	if (trim) slug = slug.replace(/^-|-$/g, '');
	return slug;
}

function getReferencedFontUrlKey(fonts) {
	const urls = new Set();
	(fonts || []).forEach((font) => {
		Object.values(font['font-weight'] || {}).forEach((url) => {
			if (typeof url === 'string' && url) urls.add(url);
		});
		Object.values(font['font-style'] || {}).forEach((url) => {
			if (typeof url === 'string' && url) urls.add(url);
		});
	});
	return JSON.stringify(Array.from(urls).sort());
}

/**
 * Dropdown to pick a file from theme/fonts/
 * Files are grouped by folder with an indented display.
 */
function ThemeFontSelect({ value, onSelect, themeFiles }) {
	if (!themeFiles || themeFiles.length === 0) {
		return null;
	}

	// Group by folder
	const grouped = {};
	themeFiles.forEach((f) => {
		const folder = f.folder || '';
		if (!grouped[folder]) grouped[folder] = [];
		grouped[folder].push(f);
	});

	const sortedFolders = Object.keys(grouped).sort((a, b) => {
		if (a === '') return -1;
		if (b === '') return 1;
		return a.localeCompare(b);
	});

	return (
		<SelectControl
			__next40pxDefaultSize
			value={value || ''}
			onChange={onSelect}
			__nextHasNoMarginBottom
		>
			<option value="">-- Select file --</option>
			{sortedFolders.map((folder) => {
				if (folder === '') {
					// Root files
					return grouped[folder].map((f) => (
						<option key={f.url} value={f.url}>
							{f.filename}
						</option>
					));
				}
				// Folder group
				return (
					<optgroup key={folder} label={folder}>
						{grouped[folder].map((f) => (
							<option key={f.url} value={f.url}>
								{f.filename}
							</option>
						))}
					</optgroup>
				);
			})}
		</SelectControl>
	);
}

function WeightField({ weight, onRename }) {
	const [draft, setDraft] = useState(null);

	return (
		<TextControl
			__next40pxDefaultSize
			label="Weight"
			value={draft !== null ? draft : weight}
			onFocus={() => setDraft(weight)}
			onChange={setDraft}
			onBlur={() => {
				if (draft !== null && draft !== weight) {
					onRename(draft);
				}
				setDraft(null);
			}}
			__nextHasNoMarginBottom
		/>
	);
}

function FontUploadButton({ onUploaded }) {
	const [uploading, setUploading] = useState(false);
	const [error, setError] = useState(null);

	const handleUpload = async (e) => {
		const file = e.target.files?.[0];
		if (!file) return;
		setUploading(true);
		setError(null);
		try {
			const result = await uploadFontFile(file);
			onUploaded(result.url, result.filename);
		} catch (err) {
			setError(err.message || 'Upload failed');
		} finally {
			setUploading(false);
			e.target.value = '';
		}
	};

	return (
		<div className="ts-font-upload">
			<label className="button button-secondary button-small ts-font-upload__btn">
				{uploading ? <Spinner /> : 'Upload'}
				<input
					type="file"
					accept=".woff,.woff2,.ttf,.otf"
					onChange={handleUpload}
					style={{ display: 'none' }}
					disabled={uploading}
				/>
			</label>
			{error && <span className="ts-font-upload__error">{error}</span>}
		</div>
	);
}

/**
 * Single installed font editor.
 * Internally converts font-weight object to an array so React keys stay stable.
 */
function InstalledFontEditor({
	font,
	expanded,
	onToggle,
	onChange,
	onRemove,
	themeFiles,
	onFileUploaded,
	onWeightUrlChange,
	fileFeatures,
}) {
	// Convert object {weight: url} → array [{weight, url}] for stable rendering
	const weightObj = font['font-weight'] || {};
	const weightEntries = Object.entries(weightObj).map(([w, url]) => ({
		weight: w,
		url: url,
		_uid: font._weightIdentities[w],
	}));

	const updateField = (field, value) => {
		onChange({ ...font, [field]: value });
	};

	// Collect features available from all files used by this font (union)
	const availableFeatures = useMemo(() => {
		const merged = new Map();
		const urls = new Set();
		Object.values(font['font-weight'] || {}).forEach((u) => {
			if (typeof u === 'string' && u) urls.add(u);
		});
		Object.values(font['font-style'] || {}).forEach((u) => {
			if (typeof u === 'string' && u) urls.add(u);
		});
		urls.forEach((url) => {
			const list = (fileFeatures && fileFeatures[url]) || [];
			list.forEach((f) => {
				if (!merged.has(f.tag)) merged.set(f.tag, f);
			});
		});
		return Array.from(merged.values()).sort((a, b) =>
			a.tag.localeCompare(b.tag)
		);
	}, [font['font-weight'], font['font-style'], fileFeatures]);

	const activeFeatures = font.features || [];

	const toggleFeature = (tag, checked) => {
		const next = new Set(activeFeatures);
		if (checked) next.add(tag);
		else next.delete(tag);
		const arr = Array.from(next).sort();
		updateField('features', arr.length ? arr : undefined);
	};

	const saveWeights = (entries) => {
		const obj = {};
		entries.forEach(({ weight, url }) => {
			obj[weight] = url;
		});
		onChange(initializeInstalledFont({ ...font, 'font-weight': obj }));
	};

	const addWeight = () => {
		const existing = new Set(weightEntries.map((e) => e.weight));
		let newWeight = '';
		const candidates = ['400', '700', '300', '500', '600', '800', '900', '100', '200'];
		for (const c of candidates) {
			if (!existing.has(c)) {
				newWeight = c;
				break;
			}
		}
		if (!newWeight) return;
		saveWeights([...weightEntries, { weight: newWeight, url: '' }]);
	};

	const renameWeight = (index, newWeight) => {
		const renamedFont = renameInstalledFontWeight(font, weightEntries[index].weight, newWeight);
		if (renamedFont === font) return false;
		onChange(renamedFont);
		return true;
	};

	const removeWeight = (index) => {
		const next = weightEntries.filter((_, i) => i !== index);
		saveWeights(next);
	};

	const weightCount = weightEntries.length;
	const name = font['font-family'] || 'Untitled font';

	return (
		<div className={`ts-font-editor${expanded ? ' ts-font-editor--expanded' : ''}`}>
			<div className="ts-font-editor__header" onClick={onToggle}>
				<div className="ts-font-editor__summary">
					<strong className="ts-font-editor__name">{name}</strong>
					{font.format && (
						<code className="ts-font-editor__family">{font.format}</code>
					)}
					{weightCount > 0 && (
						<span className="ts-font-editor__badge">
							{weightCount} weight{weightCount !== 1 ? 's' : ''}
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
						{expanded ? '\u25B2' : '\u25BC'}
					</span>
				</div>
			</div>

			{expanded && (
				<div className="ts-font-editor__body">
					<div className="ts-font-editor__row">
						<TextControl
							__next40pxDefaultSize
							label="Font Family"
							value={font['font-family'] || ''}
							onChange={(v) => updateField('font-family', v)}
							placeholder="Monospace"
							__nextHasNoMarginBottom
						/>
						<SelectControl
							__next40pxDefaultSize
							label="Format"
							value={font.format || 'woff2'}
							options={[
								{ label: 'woff2', value: 'woff2' },
								{ label: 'woff', value: 'woff' },
								{ label: 'truetype', value: 'truetype' },
							]}
							onChange={(v) => updateField('format', v)}
							__nextHasNoMarginBottom
						/>
						<TextControl
							__next40pxDefaultSize
							label="Font Display"
							value={font['font-display'] || ''}
							onChange={(v) => updateField('font-display', v)}
							placeholder="swap"
							__nextHasNoMarginBottom
						/>
						<TextControl
							__next40pxDefaultSize
							label="Variable name"
							value={font.varname || ''}
							onChange={(v) => updateField('varname', sanitizeSlug(v, false))}
							onBlur={() =>
								updateField('varname', sanitizeSlug(font.varname || ''))
							}
							placeholder={font['font-family'] ? sanitizeSlug(font['font-family']) : ''}
							__nextHasNoMarginBottom
						/>
					</div>
					{font.varname && (
						<code style={{ display: 'block', padding: '0.25rem 0', opacity: 0.7 }}>
							--wp--preset--font-family--{font.varname}
						</code>
					)}

					<h4 style={{ margin: '1rem 0 0.5rem' }}>Font Weights</h4>
					{weightEntries.map((entry, index) => (
						<div key={entry._uid} className="ts-installed-font__weight">
							<WeightField
								weight={entry.weight}
								onRename={(newWeight) => renameWeight(index, newWeight)}
							/>
							<div className="ts-installed-font__file-select">
								<ThemeFontSelect
									value={entry.url}
									onSelect={(newUrl) => onWeightUrlChange(entry._uid, newUrl)}
									themeFiles={themeFiles}
								/>
							</div>
							<FontUploadButton
								onUploaded={(uploadedUrl) => {
									onWeightUrlChange(entry._uid, uploadedUrl);
									onFileUploaded();
								}}
							/>
							<Button
								isDestructive
								variant="tertiary"
								isSmall
								onClick={() => removeWeight(index)}
							>
								&times;
							</Button>
						</div>
					))}
					<Button variant="tertiary" isSmall onClick={addWeight}>
						+ Add weight
					</Button>

					{availableFeatures.length > 0 && (
						<div className="ts-font-editor__features">
							<span className="ts-font-editor__label">
								OpenType features (always on)
							</span>
							<div className="ts-font-editor__features-list">
								{availableFeatures.map((f) => (
									<CheckboxControl
										key={f.tag}
										label={
											f.name
												? f.tag + ' — ' + f.name
												: f.tag
										}
										checked={activeFeatures.includes(f.tag)}
										onChange={(v) =>
											toggleFeature(f.tag, v)
										}
										__nextHasNoMarginBottom
									/>
								))}
							</div>
						</div>
					)}

					<div className="ts-font-editor__actions">
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

export default function InstalledFontsTab() {
	const { state, dispatch } = useConfig();
	const fonts = state.installFonts || [];
	const [themeFiles, setThemeFiles] = useState([]);
	const [loadingFiles, setLoadingFiles] = useState(false);
	const [expandedIdentity, setExpandedIdentity] = useState(null);
	const referencedFontUrlKey = getReferencedFontUrlKey(fonts);

	const loadThemeFiles = async () => {
		setLoadingFiles(true);
		try {
			const files = await getThemeFonts();
			setThemeFiles(files);
			dispatch({ type: 'SET_THEME_FONT_FILES', payload: files });
		} catch (err) {
			// ignore
		}
		setLoadingFiles(false);
	};

	useEffect(() => {
		loadThemeFiles();
	}, []);

	useEffect(() => {
		if (!themeFiles.length) return;

		const referencedUrls = new Set(JSON.parse(referencedFontUrlKey));
		const referencedFiles = themeFiles.filter((file) =>
			referencedUrls.has(file.url)
		);
		let cancelled = false;

		if (!referencedFiles.length) {
			dispatch({ type: 'SET_THEME_FONTS_SCANNING', payload: false });
			return;
		}

		dispatch({ type: 'SET_THEME_FONTS_SCANNING', payload: true });
		scanAllFontFiles(referencedFiles, (url, features) => {
			if (cancelled) return;
			dispatch({
				type: 'SET_THEME_FONT_FEATURES',
				payload: { url, features },
			});
		}).finally(() => {
			if (cancelled) return;
			dispatch({
				type: 'SET_THEME_FONTS_SCANNING',
				payload: false,
			});
		});

		return () => {
			cancelled = true;
		};
	}, [themeFiles, referencedFontUrlKey, dispatch]);

	const updateFont = (index, updated) => {
		const next = [...fonts];
		next[index] = updated;
		dispatch({ type: 'SET_INSTALL_FONTS', payload: next });
	};

	const removeFont = (index) => {
		const next = fonts.filter((_, i) => i !== index);
		dispatch({ type: 'SET_INSTALL_FONTS', payload: next });
		if (expandedIdentity === fonts[index]._uid) setExpandedIdentity(null);
	};

	const addFont = () => {
		const newFont = initializeInstalledFont({
			'font-family': '',
			'font-weight': {},
			format: 'woff2',
		});
		dispatch({
			type: 'SET_INSTALL_FONTS',
			payload: [
				...fonts,
				newFont,
			],
		});
		setExpandedIdentity(newFont._uid);
	};

	return (
		<div className="ts-installed-fonts">
			<h3>Self-Hosted Fonts (@font-face)</h3>
			<p className="description">
				Fonts loaded from the theme folder (
				<code>/fonts/</code>). They generate{' '}
				<code>@font-face</code> rules automatically.
			</p>

			{loadingFiles && (
				<p>
					<Spinner /> Loading font files...
				</p>
			)}

			{fonts.map((font, index) => (
				<InstalledFontEditor
					key={font._uid}
					font={font}
					expanded={expandedIdentity === font._uid}
					onToggle={() =>
						setExpandedIdentity(expandedIdentity === font._uid ? null : font._uid)
					}
					onChange={(updated) => updateFont(index, updated)}
					onRemove={() => removeFont(index)}
					themeFiles={themeFiles}
					onFileUploaded={loadThemeFiles}
					onWeightUrlChange={(weightIdentity, url) =>
						dispatch({ type: 'SET_INSTALLED_FONT_WEIGHT_URL', fontIdentity: font._uid, weightIdentity, url })
					}
					fileFeatures={state.themeFontFeatures || {}}
				/>
			))}

			<Button variant="secondary" style={{ marginTop: '0.5rem' }} onClick={addFont}>
				+ Add self-hosted font
			</Button>
		</div>
	);
}
