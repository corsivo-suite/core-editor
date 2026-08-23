import { useState, useMemo } from '@wordpress/element';
import { Button } from '@wordpress/components';
import { useConfig } from '../../store';

function parseCssProperties(cssText) {
	const props = {};
	if (!cssText) return props;
	const declarations = cssText.split(';');
	for (const decl of declarations) {
		const colonIdx = decl.indexOf(':');
		if (colonIdx === -1) continue;
		const prop = decl.substring(0, colonIdx).trim();
		const value = decl.substring(colonIdx + 1).trim();
		if (prop && value) {
			props[prop] = value;
		}
	}
	return props;
}

export default function FontPreview({ font }) {
	const { state } = useConfig();
	const breakpointValues = state.breakpoints || {};
	const [activeBreakpoint, setActiveBreakpoint] = useState('desktop');

	const effectiveStyles = useMemo(() => {
		const baseProps = parseCssProperties(font.css);
		const base = {
			fontFamily: baseProps['font-family'] || 'inherit',
			fontSize: baseProps['font-size'] || '1rem',
			lineHeight: baseProps['line-height'] || '1.5',
			letterSpacing: baseProps['letter-spacing'] || 'normal',
			fontWeight: baseProps['font-weight'] || 'normal',
		};

		if (
			activeBreakpoint !== 'desktop' &&
			font.breakpoints?.[activeBreakpoint]
		) {
			const bp = font.breakpoints[activeBreakpoint];
			const bpProps = parseCssProperties(bp.css);
			return {
				fontFamily: bpProps['font-family'] || base.fontFamily,
				fontSize: bpProps['font-size'] || base.fontSize,
				lineHeight: bpProps['line-height'] || base.lineHeight,
				letterSpacing:
					bpProps['letter-spacing'] || base.letterSpacing,
				fontWeight: bpProps['font-weight'] || base.fontWeight,
			};
		}
		return base;
	}, [font, activeBreakpoint]);

	const maxWidth =
		activeBreakpoint !== 'desktop' && breakpointValues[activeBreakpoint]
			? `${breakpointValues[activeBreakpoint]}px`
			: '100%';

	const fontBreakpoints = font.breakpoints
		? Object.keys(font.breakpoints)
		: [];

	return (
		<div className="ts-font-preview">
			<div className="ts-font-preview__breakpoints">
				<Button
					variant={
						activeBreakpoint === 'desktop'
							? 'primary'
							: 'secondary'
					}
					isSmall
					onClick={() => setActiveBreakpoint('desktop')}
				>
					Desktop
				</Button>
				{Object.entries(breakpointValues)
					.filter(([name]) => fontBreakpoints.includes(name))
					.sort((a, b) => b[1] - a[1])
					.map(([name, px]) => (
						<Button
							key={name}
							variant={
								activeBreakpoint === name
									? 'primary'
									: 'secondary'
							}
							isSmall
							onClick={() => setActiveBreakpoint(name)}
						>
							{name} ({px}px)
						</Button>
					))}
			</div>
			<div
				className="ts-font-preview__text"
				style={{
					fontFamily: effectiveStyles.fontFamily,
					fontSize: effectiveStyles.fontSize,
					lineHeight: effectiveStyles.lineHeight,
					letterSpacing: effectiveStyles.letterSpacing,
					fontWeight: effectiveStyles.fontWeight,
					maxWidth,
					transition: 'max-width 0.3s ease',
				}}
			>
				The quick brown fox jumps over the lazy dog. 0123456789
			</div>
			<div className="ts-font-preview__meta">
				{effectiveStyles.fontSize} / {effectiveStyles.lineHeight}
				{effectiveStyles.letterSpacing !== 'normal' &&
					` / ${effectiveStyles.letterSpacing}`}
			</div>
		</div>
	);
}
