import { useState, useMemo } from '@wordpress/element';
import { Button } from '@wordpress/components';
import { useConfig } from '../../store';
import { getFontPreviewStyles } from '../../fontPreviewStyles';

const DEFAULT_BREAKPOINTS = {};

export default function FontPreview({ font }) {
	const { state } = useConfig();
	const breakpointValues = state.breakpoints || DEFAULT_BREAKPOINTS;
	const [activeBreakpoint, setActiveBreakpoint] = useState('desktop');

	const effectiveStyles = useMemo(
		() => getFontPreviewStyles( font, breakpointValues, activeBreakpoint ),
		[ font, breakpointValues, activeBreakpoint ]
	);

	const maxWidth =
		activeBreakpoint !== 'desktop' && breakpointValues[activeBreakpoint]
			? `${breakpointValues[activeBreakpoint]}px`
			: '100%';

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
