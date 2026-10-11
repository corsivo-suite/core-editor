import { useState } from '@wordpress/element';
import { Button, SelectControl } from '@wordpress/components';
import { useConfig } from '../../store';

export default function BreakpointOverrides({
	fontSlug,
	breakpoints = {},
	onChange,
}) {
	const { state } = useConfig();
	const [showPicker, setShowPicker] = useState(false);

	const configuredBreakpoints = Object.entries(state.breakpoints || {}).sort(
		(a, b) => b[1] - a[1]
	);

	if (configuredBreakpoints.length === 0) {
		return (
			<p className="description">
				No breakpoints configured. Go to the Breakpoints tab to add
				some.
			</p>
		);
	}

	const activeBreakpoints = Object.keys(breakpoints);

	const availableBreakpoints = configuredBreakpoints.filter(
		([name]) => !activeBreakpoints.includes(name)
	);

	const addBreakpoint = (bpName) => {
		if (!bpName) return;
		const next = { ...breakpoints, [bpName]: { css: '' } };
		onChange(next);
		setShowPicker(false);
	};

	const removeBreakpoint = (bpName) => {
		const next = { ...breakpoints };
		delete next[bpName];
		onChange(next);
	};

	const updateCss = (bpName, css) => {
		const next = { ...breakpoints };
		next[bpName] = { ...next[bpName], css };
		onChange(next);
	};

	const slug = fontSlug || 'slug';

	return (
		<div className="ts-breakpoint-overrides">
			{/* Existing breakpoint rules */}
			{configuredBreakpoints
				.filter(([name]) => activeBreakpoints.includes(name))
				.map(([name, px]) => (
					<div
						key={name}
						className="ts-breakpoint-overrides__item"
					>
						<div className="ts-breakpoint-overrides__item-header">
							<strong>{name}</strong>
							<span className="description">
								max-width: {px}px
							</span>
							<Button
								isDestructive
								variant="tertiary"
								isSmall
								onClick={() => removeBreakpoint(name)}
							>
								Remove
							</Button>
						</div>
						<div className="ts-font-editor__css-context ts-font-editor__css-context--bp">
							<code className="ts-font-editor__css-selector ts-font-editor__css-selector--media">
								@media screen and (max-width: {px}px)
							</code>
							<span className="ts-font-editor__css-brace">
								{' {'}
							</span>
							<div className="ts-font-editor__css-nested">
								<code className="ts-font-editor__css-selector">
									.{slug}
									{', '}
									.is-style-font-{slug}
								</code>
								<span className="ts-font-editor__css-brace">
									{' {'}
								</span>
								<textarea
									className="ts-font-editor__css-textarea"
									value={breakpoints[name]?.css || ''}
									onChange={(e) =>
										updateCss(name, e.target.value)
									}
									placeholder={'font-size: 1rem;\nline-height: 1.3;'}
									rows={3}
									spellCheck={false}
								/>
								<span className="ts-font-editor__css-brace">
									{'}'}
								</span>
							</div>
							<span className="ts-font-editor__css-brace">
								{'}'}
							</span>
						</div>
					</div>
				))}

			{/* Add breakpoint button */}
			{availableBreakpoints.length > 0 && (
				<div className="ts-breakpoint-overrides__add">
					{showPicker ? (
						<div className="ts-breakpoint-overrides__picker">
							<SelectControl
								__next40pxDefaultSize
								value=""
								options={[
									{
										label: '— Select breakpoint —',
										value: '',
									},
									...availableBreakpoints.map(
										([name, px]) => ({
											label: `${name} (${px}px)`,
											value: name,
										})
									),
								]}
								onChange={addBreakpoint}
								__nextHasNoMarginBottom
							/>
							<Button
								variant="tertiary"
								isSmall
								onClick={() => setShowPicker(false)}
							>
								Cancel
							</Button>
						</div>
					) : (
						<Button
							variant="secondary"
							isSmall
							onClick={() => setShowPicker(true)}
						>
							+ Add breakpoint rule
						</Button>
					)}
				</div>
			)}
		</div>
	);
}
