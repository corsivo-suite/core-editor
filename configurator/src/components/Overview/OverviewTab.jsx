import { useState, useMemo } from '@wordpress/element';
import { useConfig } from '../../store';

/* ── Reusable copy-to-clipboard code ── */
function CopyCode({ value, copyKey, isCopied, copy, modifier }) {
	return (
		<button
			type="button"
			className={`ts-overview__copy${modifier ? ` ts-overview__copy--${modifier}` : ''}${isCopied(copyKey) ? ' ts-overview__copy--copied' : ''}`}
			onClick={() => copy(value, copyKey)}
			title="Click to copy"
		>
			<code>{isCopied(copyKey) ? 'Copied!' : value}</code>
		</button>
	);
}

/* ── Variable row ── */
function VarRow({ label, variable, resolved, description, copyKey, isCopied, copy, swatch }) {
	return (
		<div className="ts-overview__var-row">
			{swatch && <span className="ts-overview__swatch" style={{ backgroundColor: swatch }} />}
			<span className="ts-overview__var-label">{label}</span>
			<CopyCode value={variable} copyKey={copyKey} isCopied={isCopied} copy={copy} />
			{resolved && <span className="ts-overview__var-resolved">{resolved}</span>}
			{description && <span className="ts-overview__var-desc">{description}</span>}
		</div>
	);
}

export default function OverviewTab() {
	const { state } = useConfig();
	const [copiedKey, setCopiedKey] = useState(null);

	const copy = (text, key) => {
		navigator.clipboard.writeText(text).then(() => {
			setCopiedKey(key);
			setTimeout(() => setCopiedKey((current) => (current === key ? null : current)), 1500);
		});
	};

	const isCopied = (key) => copiedKey === key;

	/* ── Resolved theme.json from PHP ── */
	const themeJson = window.corsivoThemeEditor?.resolvedThemeJson || {};
	const tjSettings = themeJson.settings || {};
	const tjStyles = themeJson.styles || {};
	const coreSettings = window.corsivoThemeEditor?.coreThemeJson?.settings || {};

	/* ── Data from store ── */
	const colors = state.colors || [];
	const fonts = state.fonts || [];
	const spacing = state.spacing || {};
	const spacingSizes = spacing.spacingSizes || [];
	const breakpoints = state.breakpoints || {};
	const blockStyles = state.coreComponents?.blockStyles || [];
	const globalStyles = state.globalStyles || {};
	const styleTemplates = state.styleTemplates || [];
	const contextStyles = state.contextStyles || [];

	const body = globalStyles.body || {};
	const heading = globalStyles.heading || {};
	const link = globalStyles.link || {};

	/* ── Root padding from theme.json styles ── */
	const rootPadding = tjStyles.spacing?.padding || {};

	/* ── Layout from theme.json settings ── */
	const layout = tjSettings.layout || {};

	/* ── Resolved block gap from theme.json styles ── */
	const blockGap = tjStyles.spacing?.blockGap || spacing.blockGap || '';

	/* ── WP default colors from core theme.json ── */
	const wpDefaultColors = coreSettings.color?.palette?.default || [];

	/* ── WP default font sizes ── */
	const wpFontSizes = coreSettings.typography?.fontSizes?.default || [];

	/* ── Font sources (Google + Local) ── */
	const fontItems = useMemo(() => {
		const items = [];
		const seen = new Set();
		(state.customFonts || []).forEach((entry) => {
			const urlStr = entry && entry.url ? entry.url : '';
			const parsedFonts = entry && Array.isArray(entry.fonts) ? entry.fonts : [];
			if (parsedFonts.length > 0) {
				parsedFonts.forEach((f) => {
					if (f.family && !seen.has(f.family.toLowerCase())) {
						seen.add(f.family.toLowerCase());
						items.push({ name: f.family, source: 'Google' });
					}
				});
			} else {
				const matches = urlStr.match(/family=([^&:]+)/g);
				if (matches) {
					matches.forEach((m) => {
						const name = decodeURIComponent(m.replace('family=', '')).replace(/\+/g, ' ');
						if (!seen.has(name.toLowerCase())) {
							seen.add(name.toLowerCase());
							items.push({ name, source: 'Google' });
						}
					});
				}
			}
		});
		(state.installFonts || []).forEach((f) => {
			const name = f['font-family'];
			if (name && !seen.has(name.toLowerCase())) {
				seen.add(name.toLowerCase());
				items.push({ name, source: 'Local' });
			}
		});
		return items;
	}, [state.customFonts, state.installFonts]);

	/* ── Font-family presets from theme.json ── */
	const fontPresets = useMemo(() => {
		const families = tjSettings.typography?.fontFamilies || [];
		return families.map((f) => ({
			slug: f.slug,
			name: f.name || f.slug,
			fontFamily: f.fontFamily || '',
		}));
	}, [tjSettings.typography?.fontFamilies]);

	const bpEntries = Object.entries(breakpoints).sort((a, b) => b[1] - a[1]);

	/* ── Helpers ── */
	const fontName = (slug) => {
		if (!slug) return '—';
		const f = fonts.find((x) => x.slug === slug);
		return f ? (f.name || f.slug) : slug;
	};
	const colorHex = (slug) => {
		if (!slug) return '';
		const c = colors.find((x) => x.slug === slug);
		return c ? c.color : '';
	};

	return (
		<div className="ts-overview-tab">
			<h2>Overview</h2>
			<p className="description">
				Specchietto completo delle variabili CSS, preset e stili registrati.
				Clicca su qualsiasi <code>var()</code> o classe per copiare.
			</p>

			{/* ================================================================
			    1. GLOBAL STYLES (body, heading, link)
			    ================================================================ */}
			{(body.font || body.background || body.color || heading.font || heading.color || heading.fontWeight || globalStyles.titleStyle || globalStyles.defaultParagraphStyle || globalStyles.defaultHeadingStyle || link.color || link.hoverColor) && (
				<section className="ts-overview__section">
					<h3>Global Styles <span className="ts-overview__badge">theme.json → styles</span></h3>
					<p className="description">Stili root del sito impostati nel Global template.</p>

					{(body.font || body.background || body.color) && (
						<div className="ts-overview__group">
							<h4>Body</h4>
							{body.font && (
								<VarRow label="Font Family" variable={`var(--wp--preset--font-family--${body.font})`}
									resolved={fontName(body.font)} copyKey="gs:body:font" isCopied={isCopied} copy={copy} />
							)}
							{body.background && (
								<VarRow label="Background" variable={`var(--wp--preset--color--${body.background})`}
									resolved={colorHex(body.background)} copyKey="gs:body:bg" isCopied={isCopied} copy={copy}
									swatch={colorHex(body.background)} />
							)}
							{body.color && (
								<VarRow label="Text" variable={`var(--wp--preset--color--${body.color})`}
									resolved={colorHex(body.color)} copyKey="gs:body:color" isCopied={isCopied} copy={copy}
									swatch={colorHex(body.color)} />
							)}
						</div>
					)}

					{(heading.font || heading.color || heading.fontWeight) && (
						<div className="ts-overview__group">
							<h4>Headings (h1–h6)</h4>
							{heading.font && (
								<VarRow label="Font Family" variable={`var(--wp--preset--font-family--${heading.font})`}
									resolved={fontName(heading.font)} copyKey="gs:h:font" isCopied={isCopied} copy={copy} />
							)}
							{heading.color && (
								<VarRow label="Color" variable={`var(--wp--preset--color--${heading.color})`}
									resolved={colorHex(heading.color)} copyKey="gs:h:color" isCopied={isCopied} copy={copy}
									swatch={colorHex(heading.color)} />
							)}
							{heading.fontWeight && (
								<div className="ts-overview__var-row">
									<span className="ts-overview__var-label">Font Weight</span>
									<span className="ts-overview__var-resolved">{heading.fontWeight}</span>
								</div>
							)}
						</div>
					)}

					{globalStyles.titleStyle && (
						<div className="ts-overview__group">
							<h4>Page Title</h4>
							<VarRow
								label="Style"
								variable={`var(--wp--preset--font-family--${globalStyles.titleStyle})`}
								resolved={fontName(globalStyles.titleStyle)}
								copyKey="gs:title:font"
								isCopied={isCopied}
								copy={copy}
							/>
						</div>
					)}

					{(globalStyles.defaultParagraphStyle || globalStyles.defaultHeadingStyle) && (
						<div className="ts-overview__group">
							<h4>Default Blocks</h4>
							{globalStyles.defaultParagraphStyle && (
								<VarRow
									label="Paragraph"
									variable={`var(--wp--preset--font-family--${globalStyles.defaultParagraphStyle})`}
									resolved={fontName(globalStyles.defaultParagraphStyle)}
									copyKey="gs:default:p"
									isCopied={isCopied}
									copy={copy}
								/>
							)}
							{globalStyles.defaultHeadingStyle && (
								<VarRow
									label="Heading"
									variable={`var(--wp--preset--font-family--${globalStyles.defaultHeadingStyle})`}
									resolved={fontName(globalStyles.defaultHeadingStyle)}
									copyKey="gs:default:h"
									isCopied={isCopied}
									copy={copy}
								/>
							)}
						</div>
					)}

					{(link.color || link.hoverColor) && (
						<div className="ts-overview__group">
							<h4>Link</h4>
							{link.color && (
								<VarRow label="Color" variable={`var(--wp--preset--color--${link.color})`}
									resolved={colorHex(link.color)} copyKey="gs:link:color" isCopied={isCopied} copy={copy}
									swatch={colorHex(link.color)} />
							)}
							{link.hoverColor && (
								<VarRow label=":hover" variable={`var(--wp--preset--color--${link.hoverColor})`}
									resolved={colorHex(link.hoverColor)} copyKey="gs:link:hover" isCopied={isCopied} copy={copy}
									swatch={colorHex(link.hoverColor)} />
							)}
						</div>
					)}
				</section>
			)}

			{/* ================================================================
			    2. ROOT PADDING & LAYOUT — spiegazione completa
			    ================================================================ */}
			<section className="ts-overview__section">
				<h3>Root Padding & Layout</h3>
				<p className="description">
					Queste variabili controllano il layout globale del sito.
					Vengono settate in <code>styles.spacing.padding</code> e <code>settings.layout</code> nel theme.json.
				</p>

				<div className="ts-overview__group">
					<h4>Root Padding</h4>
					<p className="description">
						Il "root padding" è il margine interno tra il bordo della finestra del browser e il contenuto principale
						(<code>.wp-site-blocks</code>). Quando <code>useRootPaddingAwareAlignments</code> è attivo,
						i blocchi <code>alignfull</code> possono uscire da questo padding e toccare i bordi della finestra.
					</p>
					{[
						{ side: 'top', desc: 'Spazio sopra il primo blocco del sito' },
						{ side: 'right', desc: 'Margine destro tra contenuto e bordo finestra' },
						{ side: 'bottom', desc: 'Spazio sotto l\'ultimo blocco del sito' },
						{ side: 'left', desc: 'Margine sinistro tra contenuto e bordo finestra' },
					].map(({ side, desc }) => (
						<VarRow
							key={`rp:${side}`}
							label={`padding-${side}`}
							variable={`var(--wp--style--root--padding-${side})`}
							resolved={rootPadding[side] || '—'}
							description={desc}
							copyKey={`rp:${side}`}
							isCopied={isCopied} copy={copy}
						/>
					))}
				</div>

				<div className="ts-overview__group">
					<h4>Content Size</h4>
					<p className="description">
						La larghezza massima dei blocchi "normali" (non wide, non full). Quando un blocco è dentro
						<code>.is-layout-constrained</code>, il suo <code>max-width</code> sarà questo valore.
						Es: se è <code>1200px</code>, un paragrafo non sarà mai più largo di 1200px.
					</p>
					<VarRow
						label="content-size"
						variable="var(--wp--style--global--content-size)"
						resolved={layout.contentSize || spacing.contentSize || '—'}
						copyKey="layout:cs"
						isCopied={isCopied} copy={copy}
					/>
				</div>

				<div className="ts-overview__group">
					<h4>Wide Size</h4>
					<p className="description">
						La larghezza massima dei blocchi con <code>alignwide</code>. Più largo del content-size
						ma non full-width. Se è <code>100%</code>, i blocchi wide occuperanno tutta la larghezza
						disponibile (dentro il root padding).
					</p>
					<VarRow
						label="wide-size"
						variable="var(--wp--style--global--wide-size)"
						resolved={layout.wideSize || spacing.wideSize || '—'}
						copyKey="layout:ws"
						isCopied={isCopied} copy={copy}
					/>
				</div>

				<div className="ts-overview__group">
					<h4>Block Gap</h4>
					<p className="description">
						Lo spazio verticale tra un blocco e il successivo. WordPress lo applica come
						<code>margin-block-start</code> su ogni blocco dentro <code>.is-layout-flow</code> e
						<code>.is-layout-constrained</code>, e come <code>gap</code> per
						<code>.is-layout-flex</code> e <code>.is-layout-grid</code>.
					</p>
					<VarRow
						label="block-gap"
						variable="var(--wp--style--block-gap)"
						resolved={blockGap || '—'}
						copyKey="layout:bg"
						isCopied={isCopied} copy={copy}
					/>
				</div>
			</section>

			{/* ================================================================
			    3. COLOR PRESETS (custom)
			    ================================================================ */}
			{colors.length > 0 && (
				<section className="ts-overview__section">
					<h3>Colori Custom <span className="ts-overview__badge">--wp--preset--color--*</span></h3>
					<div className="ts-overview__colors">
						{colors.map((color) => {
							const varStr = `var(--wp--preset--color--${color.slug})`;
							const key = `color:${color.slug}`;
							return (
								<div key={key} className="ts-overview__color-item">
									<span className="ts-overview__swatch" style={{ backgroundColor: color.color }} />
									<span className="ts-overview__label">{color.name || color.slug}</span>
									<CopyCode value={varStr} copyKey={key} isCopied={isCopied} copy={copy} />
									<span className="ts-overview__var-resolved">{color.color}</span>
								</div>
							);
						})}
					</div>
				</section>
			)}

			{/* ================================================================
			    3b. WP DEFAULT COLORS
			    ================================================================ */}
			{wpDefaultColors.length > 0 && (
				<section className="ts-overview__section">
					<h3>Colori Default WP <span className="ts-overview__badge">WordPress core</span></h3>
					<p className="description">Palette predefinita di WordPress. Sempre disponibili anche senza il plugin.</p>
					<div className="ts-overview__colors">
						{wpDefaultColors.map((color) => {
							const varStr = `var(--wp--preset--color--${color.slug})`;
							const key = `wpcolor:${color.slug}`;
							return (
								<div key={key} className="ts-overview__color-item ts-overview__color-item--wp">
									<span className="ts-overview__swatch" style={{ backgroundColor: color.color }} />
									<span className="ts-overview__label">{color.name || color.slug}</span>
									<CopyCode value={varStr} copyKey={key} isCopied={isCopied} copy={copy} />
									<span className="ts-overview__var-resolved">{color.color}</span>
								</div>
							);
						})}
					</div>
				</section>
			)}

			{/* ================================================================
			    4. FONT SOURCES
			    ================================================================ */}
			{fontItems.length > 0 && (
				<section className="ts-overview__section">
					<h3>Font Sources <span className="ts-overview__badge">@font-face</span></h3>
					<p className="description">Font family caricate (Google Fonts o file locali woff2).</p>
					<div className="ts-overview__fonts">
						{fontItems.map((item, idx) => {
							const decl = `font-family: "${item.name}", sans-serif;`;
							const key = `fontsrc:${idx}`;
							return (
								<div key={key} className="ts-overview__font-item">
									<span className="ts-overview__font-preview" style={{ fontFamily: `"${item.name}", sans-serif` }}>
										{item.name}
									</span>
									<CopyCode value={decl} copyKey={key} isCopied={isCopied} copy={copy} />
									<span className="ts-overview__badge">{item.source}</span>
								</div>
							);
						})}
					</div>
				</section>
			)}

			{/* ================================================================
			    5. FONT-FAMILY PRESETS (from theme.json)
			    ================================================================ */}
			{fontPresets.length > 0 && (
				<section className="ts-overview__section">
					<h3>Font-Family Presets <span className="ts-overview__badge">--wp--preset--font-family--*</span></h3>
					<p className="description">
						Ogni stile tipografico genera un preset font-family nel theme.json.
						Usa queste variabili nel tuo SCSS invece di scrivere i nomi font a mano.
					</p>
					<div className="ts-overview__font-presets">
						{fontPresets.map((fp) => {
							const varStr = `var(--wp--preset--font-family--${fp.slug})`;
							const key = `fp:${fp.slug}`;
							return (
								<VarRow
									key={key}
									label={fp.name}
									variable={varStr}
									resolved={fp.fontFamily}
									copyKey={key}
									isCopied={isCopied} copy={copy}
								/>
							);
						})}
					</div>
				</section>
			)}

			{/* ================================================================
			    5b. WP DEFAULT FONT SIZES
			    ================================================================ */}
			{wpFontSizes.length > 0 && (
				<section className="ts-overview__section">
					<h3>Font Size Presets <span className="ts-overview__badge">--wp--preset--font-size--*</span></h3>
					<p className="description">
						Dimensioni font predefinite di WordPress. Selezionabili nell'editor e utilizzabili con
						la classe <code>.has-{'<slug>'}-font-size</code>.
					</p>
					<div className="ts-overview__font-presets">
						{wpFontSizes.map((fs) => {
							const varStr = `var(--wp--preset--font-size--${fs.slug})`;
							const key = `wpfs:${fs.slug}`;
							return (
								<VarRow
									key={key}
									label={fs.name || fs.slug}
									variable={varStr}
									resolved={fs.size}
									copyKey={key}
									isCopied={isCopied} copy={copy}
								/>
							);
						})}
					</div>
				</section>
			)}

			{/* ================================================================
			    6. SPACING PRESETS
			    ================================================================ */}
			{spacingSizes.length > 0 && (
				<section className="ts-overview__section">
					<h3>Spacing Presets <span className="ts-overview__badge">--wp--preset--spacing--*</span></h3>
					<p className="description">
						Valori di spaziatura selezionabili nell'editor Gutenberg per margini e padding.
					</p>
					<div className="ts-overview__spacing-sizes">
						{spacingSizes.map((preset) => {
							const varStr = `var(--wp--preset--spacing--${preset.slug})`;
							const key = `sp:${preset.slug}`;
							return (
								<div key={key} className="ts-overview__spacing-item">
									<span className="ts-overview__label">{preset.name || preset.slug}</span>
									<span className="ts-overview__spacing-bar-wrap">
										<span className="ts-overview__spacing-bar" style={{ width: preset.size }} />
									</span>
									<span className="ts-overview__spacing-size">{preset.size}</span>
									<CopyCode value={varStr} copyKey={key} isCopied={isCopied} copy={copy} />
								</div>
							);
						})}
					</div>
				</section>
			)}

			{/* ================================================================
			    7. BREAKPOINTS
			    ================================================================ */}
			{bpEntries.length > 0 && (
				<section className="ts-overview__section">
					<h3>Breakpoints <span className="ts-overview__badge">SCSS @media</span></h3>
					<table className="ts-overview__table">
						<thead>
							<tr><th>Name</th><th>Max-width</th><th>Media query</th></tr>
						</thead>
						<tbody>
							{bpEntries.map(([name, value]) => {
								const mq = `@media (max-width: ${value}px)`;
								const key = `bp:${name}`;
								return (
									<tr key={name}>
										<td><strong>{name}</strong></td>
										<td>{value}px</td>
										<td><CopyCode value={mq} copyKey={key} isCopied={isCopied} copy={copy} /></td>
									</tr>
								);
							})}
						</tbody>
					</table>
				</section>
			)}

			{/* ================================================================
			    8. TYPOGRAPHY STYLES
			    ================================================================ */}
			{fonts.length > 0 && (
				<section className="ts-overview__section">
					<h3>Typography Styles <span className="ts-overview__badge">.is-style-font-*</span></h3>
					<p className="description">
						Variazioni di stile registrate su heading e paragrafi.
						Ogni stile crea anche un preset font-family (vedi sopra).
					</p>
					<div className="ts-overview__styles-list">
						{fonts.map((font, idx) => {
							if (!font.slug) return null;
							const cls = `.is-style-font-${font.slug}`;
							const key = `ts:${idx}`;
							return (
								<div key={key} className="ts-overview__style-item">
									<CopyCode value={cls} copyKey={key} isCopied={isCopied} copy={copy} modifier="class" />
									{font.name && <span className="ts-overview__label">{font.name}</span>}
									{font.css && <pre className="ts-overview__css-preview">{font.css}</pre>}
								</div>
							);
						})}
					</div>
				</section>
			)}

			{/* ================================================================
			    9. BLOCK STYLES
			    ================================================================ */}
			{blockStyles.length > 0 && (
				<section className="ts-overview__section">
					<h3>Block Styles <span className="ts-overview__badge">.is-style-*</span></h3>
					<p className="description">
						Variazioni stile su blocchi core. Selezionabili in Gutenberg e richiamabili da PHP con
						<code>render_core_button($text, $url, ['style' =&gt; 'slug'])</code>.
					</p>
					<div className="ts-overview__styles-list">
						{blockStyles.map((bs, idx) => {
							if (!bs.slug || !bs.target) return null;
							const cls = `.is-style-${bs.slug}`;
							const key = `bs:${idx}`;
							return (
								<div key={key} className="ts-overview__style-item">
									<CopyCode value={cls} copyKey={key} isCopied={isCopied} copy={copy} modifier="class" />
									<span className="ts-overview__badge">{bs.target.replace('core/', '')}</span>
									{bs.name && <span className="ts-overview__label">{bs.name}</span>}
									{bs.isDefault && <span className="ts-overview__badge ts-overview__badge--default">default</span>}
									{bs.inherits && (
										<span className="ts-overview__badge ts-overview__badge--inherits">
											← {bs.inherits.replace('bs:', '').replace('::', ' / ')}
										</span>
									)}
									{bs.css && <pre className="ts-overview__css-preview">{bs.css}</pre>}
									{bs[':hover'] && (
										<pre className="ts-overview__css-preview ts-overview__css-preview--hover">
											{`:hover { ${bs[':hover']} }`}
										</pre>
									)}
								</div>
							);
						})}
					</div>
				</section>
			)}

			{/* ================================================================
			    10. STYLE TEMPLATES
			    ================================================================ */}
			{styleTemplates.length > 0 && (
				<section className="ts-overview__section">
					<h3>Style Templates</h3>
					<p className="description">
						Set di stili assegnabili ai contesti pagina. Ogni template sovrascrive body/heading/link per le pagine che lo usano.
					</p>
					<div className="ts-overview__templates-list">
						{styleTemplates.map((tpl, idx) => {
							if (!tpl.slug) return null;
							const tBody = tpl.body || {};
							const tHead = tpl.heading || {};
							const tLink = tpl.link || {};
							const tTitleStyle = tpl.titleStyle || '';
							const tDefaultParagraphStyle = tpl.defaultParagraphStyle || '';
							const tDefaultHeadingStyle = tpl.defaultHeadingStyle || '';
							return (
								<div key={`tpl:${idx}`} className="ts-overview__template-item">
									<strong>{tpl.name || tpl.slug}</strong>
									<span className="ts-overview__badge">{tpl.slug}</span>
									{tpl.isDefault && (
										<span className="ts-overview__badge ts-overview__badge--default">default</span>
									)}
									<div className="ts-overview__template-detail">
										{tBody.font && <span>Body: <em>{fontName(tBody.font)}</em></span>}
										{tBody.background && <span> bg: <code>{tBody.background}</code></span>}
										{tBody.color && <span> text: <code>{tBody.color}</code></span>}
										{tHead.font && <span> | H: <em>{fontName(tHead.font)}</em></span>}
										{tHead.color && <span> color: <code>{tHead.color}</code></span>}
										{tTitleStyle && <span> | Title: <em>{fontName(tTitleStyle)}</em></span>}
										{tDefaultParagraphStyle && <span> | Default P: <em>{fontName(tDefaultParagraphStyle)}</em></span>}
										{tDefaultHeadingStyle && <span> | Default H: <em>{fontName(tDefaultHeadingStyle)}</em></span>}
										{tLink.color && <span> | Link: <code>{tLink.color}</code></span>}
										{tLink.hoverColor && <span> hover: <code>{tLink.hoverColor}</code></span>}
									</div>
								</div>
							);
						})}
					</div>
				</section>
			)}

			{/* ================================================================
			    11. CONTEXT ASSIGNMENTS
			    ================================================================ */}
			{contextStyles.length > 0 && (
				<section className="ts-overview__section">
					<h3>Context → Template</h3>
					<table className="ts-overview__table">
						<thead>
							<tr><th>Context (body class)</th><th>Template</th></tr>
						</thead>
						<tbody>
							{contextStyles.map((cs, idx) => {
								if (!cs.context || !cs.template) return null;
								const tpl = styleTemplates.find((t) => t.slug === cs.template);
								return (
									<tr key={`ctx:${idx}`}>
										<td><code>{cs.context}</code></td>
										<td>{tpl ? (tpl.name || tpl.slug) : cs.template}</td>
									</tr>
								);
							})}
						</tbody>
					</table>
				</section>
			)}

			{/* ================================================================
			    12. QUICK REFERENCE — WP Selectors & Patterns
			    ================================================================ */}
			<section className="ts-overview__section ts-overview__section--ref">
				<h3>Quick Reference <span className="ts-overview__badge">CSS Selectors</span></h3>
				<p className="description">
					Selettori CSS utili generati da WordPress. Copiabili per uso in SCSS.
				</p>

				<div className="ts-overview__group">
					<h4>Layout Containers</h4>
					{[
						['.wp-site-blocks', 'Contenitore principale del sito. Riceve il root padding top/bottom.'],
						['.has-global-padding', 'Elementi con root padding left/right. I figli alignfull escono con margin negativo.'],
						['.is-layout-constrained', 'Layout con max-width = content-size. I blocchi sono centrati con margin auto.'],
						['.is-layout-flow', 'Layout flow standard. I blocchi hanno block-gap come margin-block-start.'],
						['.is-layout-flex', 'Layout flex. Usa gap invece di margin per gli spazi.'],
						['.is-layout-grid', 'Layout grid. Usa gap come flex.'],
					].map(([sel, desc]) => (
						<div key={sel} className="ts-overview__var-row">
							<CopyCode value={sel} copyKey={`sel:${sel}`} isCopied={isCopied} copy={copy} modifier="class" />
							<span className="ts-overview__var-desc">{desc}</span>
						</div>
					))}
				</div>

				<div className="ts-overview__group">
					<h4>Alignment</h4>
					{[
						['.alignwide', `max-width: var(--wp--style--global--wide-size) → ${layout.wideSize || spacing.wideSize || '?'}`],
						['.alignfull', 'Larghezza 100%. Esce dal root padding con margin negativo.'],
						['.aligncenter', 'Centrato con margin-left/right: auto.'],
						['.alignleft', 'Float left con margin-right: 2em.'],
						['.alignright', 'Float right con margin-left: 2em.'],
					].map(([sel, desc]) => (
						<div key={sel} className="ts-overview__var-row">
							<CopyCode value={sel} copyKey={`sel:${sel}`} isCopied={isCopied} copy={copy} modifier="class" />
							<span className="ts-overview__var-desc">{desc}</span>
						</div>
					))}
				</div>

				<div className="ts-overview__group">
					<h4>Elementi Blocco</h4>
					{[
						['.wp-element-button', 'Elemento interno del button block. Target per styling bottoni.'],
						['.wp-block-button__link', 'Classe WordPress alternativa a .wp-element-button.'],
						['.wp-block-heading', 'Tutti i blocchi heading (h1-h6).'],
						['a:where(:not(.wp-element-button))', 'Link normali (esclude bottoni). Target per link styles.'],
						['.wp-element-caption', 'Didascalie di immagini, video, tabelle.'],
					].map(([sel, desc]) => (
						<div key={sel} className="ts-overview__var-row">
							<CopyCode value={sel} copyKey={`sel:${sel}`} isCopied={isCopied} copy={copy} modifier="class" />
							<span className="ts-overview__var-desc">{desc}</span>
						</div>
					))}
				</div>

				<div className="ts-overview__group">
					<h4>Color Utility Classes</h4>
					<p className="description">
						WordPress genera automaticamente classi utility per ogni colore registrato. Pattern:
					</p>
					{[
						['.has-{slug}-color', 'color: var(--wp--preset--color--{slug}) !important'],
						['.has-{slug}-background-color', 'background-color: var(--wp--preset--color--{slug}) !important'],
						['.has-{slug}-border-color', 'border-color: var(--wp--preset--color--{slug}) !important'],
					].map(([sel, desc]) => (
						<div key={sel} className="ts-overview__var-row">
							<CopyCode value={sel} copyKey={`sel:${sel}`} isCopied={isCopied} copy={copy} modifier="class" />
							<span className="ts-overview__var-desc">{desc}</span>
						</div>
					))}
				</div>

				<div className="ts-overview__group">
					<h4>Font Utility Classes</h4>
					{[
						['.has-{slug}-font-family', 'font-family: var(--wp--preset--font-family--{slug}) !important'],
						['.has-{slug}-font-size', 'font-size: var(--wp--preset--font-size--{slug}) !important'],
					].map(([sel, desc]) => (
						<div key={sel} className="ts-overview__var-row">
							<CopyCode value={sel} copyKey={`sel:${sel}`} isCopied={isCopied} copy={copy} modifier="class" />
							<span className="ts-overview__var-desc">{desc}</span>
						</div>
					))}
				</div>
			</section>
		</div>
	);
}
