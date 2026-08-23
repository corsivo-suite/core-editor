/**
 * Parse a Google Fonts CSS2 URL and extract font family info.
 *
 * Handles formats:
 *   ?family=Font+Name:wght@400;700
 *   ?family=Font+Name:ital,wght@0,400;1,400
 *   ?family=Font+Name:wght@200..800
 *   ?family=Font+One&family=Font+Two
 *
 * @param {string} url
 * @returns {Array<{family: string, weights: string, italic: boolean}>}
 */
export default function parseGoogleFontsUrl(url) {
	if (!url || !url.includes('fonts.googleapis.com')) return [];

	const fonts = [];

	try {
		const urlObj = new URL(url);
		const familyParams = urlObj.searchParams.getAll('family');

		for (const param of familyParams) {
			const [namePart, specPart] = param.split(':');
			const family = namePart.replace(/\+/g, ' ').trim();
			if (!family) continue;

			let weights = '';
			let italic = false;

			if (specPart) {
				const [axes, values] = specPart.split('@');
				const axesList = axes ? axes.split(',') : [];
				const hasItal = axesList.includes('ital');

				if (values) {
					const variants = values.split(';');
					const weightSet = new Set();
					const wghtIndex = axesList.indexOf('wght');
					const italIndex = axesList.indexOf('ital');

					for (const variant of variants) {
						const parts = variant.split(',');

						// Detect italic
						if (hasItal && italIndex >= 0 && italIndex < parts.length) {
							if (parts[italIndex] === '1') italic = true;
						}

						// Extract weight using axis position
						if (wghtIndex >= 0 && wghtIndex < parts.length) {
							weightSet.add(parts[wghtIndex]);
						} else if (
							axesList.length === 0 ||
							(axesList.length === 1 && axesList[0] === 'wght')
						) {
							weightSet.add(parts[0]);
						}
					}

					weights = formatWeights([...weightSet]);
				} else if (axes) {
					// No @ sign, just axes declaration
					if (hasItal) italic = true;
				}
			}

			fonts.push({ family, weights, italic, varname: '' });
		}
	} catch (e) {
		// Fallback: try regex extraction for malformed URLs
		const matches = url.matchAll(/family=([^&]+)/g);
		for (const match of matches) {
			const namePart = match[1].split(':')[0];
			const family = decodeURIComponent(namePart.replace(/\+/g, ' ')).trim();
			if (family) {
				fonts.push({ family, weights: '', italic: false, varname: '' });
			}
		}
	}

	return fonts;
}

/**
 * Format weight values into a readable string.
 * "200..800" → "200–800"
 * ["400", "700"] → "400, 700"
 * ["200..800"] → "200–800"
 */
function formatWeights(weights) {
	if (weights.length === 0) return '';

	// Check for range format (200..800)
	const ranges = [];
	const singles = [];

	for (const w of weights) {
		if (w.includes('..')) {
			ranges.push(w.replace('..', '–'));
		} else {
			singles.push(w);
		}
	}

	// If we have a range, prefer showing it
	if (ranges.length > 0) return ranges.join(', ');

	// Sort numeric singles
	singles.sort((a, b) => parseInt(a) - parseInt(b));

	// Collapse consecutive into range if possible
	if (singles.length > 3) {
		const min = singles[0];
		const max = singles[singles.length - 1];
		return `${min}–${max}`;
	}

	return singles.join(', ');
}
